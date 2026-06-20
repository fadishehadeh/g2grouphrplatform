<?php declare(strict_types=1); ?>

<div class="card content-card mb-3">
    <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Organisational Chart</h5>
            <p class="text-muted mb-0">Live hierarchy built from manager assignments. Click any node to visit the employee profile.</p>
        </div>
        <div class="mobile-action-group org-chart-toolbar">
            <input type="text" id="orgSearch" class="form-control org-chart-control" placeholder="Search name or title...">
            <select id="orgDeptFilter" class="form-select org-chart-control">
                <option value="">All departments</option>
            </select>
            <button class="btn btn-outline-secondary" id="orgExpandAll"><i class="bi bi-arrows-fullscreen"></i> Expand</button>
            <button class="btn btn-outline-secondary" id="orgCollapseAll"><i class="bi bi-arrows-angle-contract"></i> Collapse</button>
            <button class="btn btn-outline-primary" id="orgExport"><i class="bi bi-download"></i> PNG</button>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div id="orgChartContainer" class="org-chart-container"></div>
    </div>
</div>

<?php
$nodesJson = json_encode($nodes ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<style>
    .org-chart-toolbar { align-items: stretch; justify-content: flex-end; }
    .org-chart-control { width: min(100%, 220px); min-width: 0; }
    #orgDeptFilter.org-chart-control { width: min(100%, 200px); }
    .org-chart-container { overflow: auto; min-height: 500px; max-width: 100%; background: #f8fafc; border-radius: 0 0 1rem 1rem; position: relative; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; touch-action: pan-x pan-y; }
    #orgChartContainer svg { display: block; }
    .org-link { fill: none; stroke: #cbd5e1; stroke-width: 1.5px; }
    .org-node rect { transition: opacity .15s; }
    .org-node:hover rect { opacity: .88; }
    .org-node.highlighted rect { stroke: #f59e0b !important; stroke-width: 3px !important; }
    #orgChartContainer::-webkit-scrollbar { height: 6px; width: 6px; }
    #orgChartContainer::-webkit-scrollbar-track { background: #f1f5f9; }
    #orgChartContainer::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    @media (max-width: 991.98px) {
        .org-chart-toolbar { width: 100%; justify-content: stretch; }
        .org-chart-toolbar .btn { flex: 1 1 calc(50% - .5rem); }
    }
    @media (max-width: 767.98px) {
        .org-chart-toolbar { width: 100%; }
        .org-chart-toolbar > * { width: 100%; }
        .org-chart-control,
        #orgDeptFilter.org-chart-control { max-width: none; }
        .org-chart-toolbar .btn { flex: 1 1 100%; }
        .org-chart-container { min-height: 360px; border-radius: 0 0 .9rem .9rem; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script>
<script>
(function () {
    const rawNodes = <?= $nodesJson ?>;

    if (!rawNodes.length) {
        document.getElementById('orgChartContainer').innerHTML =
            '<div class="p-5 text-center text-muted">No employees found. Add employees and assign managers to build the chart.</div>';
        return;
    }

    const depts = [...new Set(rawNodes.map(n => n.department).filter(Boolean))].sort();
    const deptSel = document.getElementById('orgDeptFilter');
    depts.forEach(d => {
        const option = document.createElement('option');
        option.value = d;
        option.textContent = d;
        deptSel.appendChild(option);
    });

    const NW = 175;
    const NH = 62;
    const HGAP = 18;
    const VGAP = 55;
    const BRAND = getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim() || '#e63946';
    const ROOT_COLOR = '#475569';

    function buildDS(nodes) {
        const map = {};
        nodes.forEach(n => {
            map[n.id] = { ...n, children: [] };
        });

        const roots = [];
        nodes.forEach(n => {
            if (n.pid && map[n.pid]) {
                map[n.pid].children.push(map[n.id]);
            } else {
                roots.push(map[n.id]);
            }
        });

        if (roots.length === 1) {
            return roots[0];
        }

        return {
            id: 0,
            name: '<?= e(\App\Support\Branding::name()); ?>',
            title: 'Organisation',
            department: '',
            status: 'active',
            photo: null,
            profileUrl: '#',
            children: roots
        };
    }

    function truncate(str, max) {
        return str && str.length > max ? str.slice(0, max - 1) + '...' : (str || '');
    }

    let svgEl = null;

    function render(nodes) {
        const container = document.getElementById('orgChartContainer');
        container.innerHTML = '';

        const ds = buildDS(nodes);
        const root = d3.hierarchy(ds);

        const tree = d3.tree()
            .nodeSize([NW + HGAP, NH + VGAP])
            .separation((a, b) => a.parent === b.parent ? 1 : 1.2);

        tree(root);

        let xMin = Infinity;
        let xMax = -Infinity;
        root.each(d => {
            if (d.x < xMin) xMin = d.x;
            if (d.x > xMax) xMax = d.x;
        });

        const totalW = (xMax - xMin) + NW + 60;
        const totalH = (root.height + 1) * (NH + VGAP) + 40;
        const containerW = container.clientWidth || 900;
        const svgW = Math.max(containerW, totalW);

        const svg = d3.select(container).append('svg')
            .attr('width', svgW)
            .attr('height', totalH + 20)
            .attr('id', 'orgSvg');

        svgEl = svg.node();

        const g = svg.append('g').attr('id', 'orgG');
        const initialTx = svgW / 2 - (xMin + xMax) / 2;
        g.attr('transform', `translate(${initialTx}, 20)`);

        const zoom = d3.zoom()
            .scaleExtent([0.15, 3])
            .on('zoom', e => g.attr('transform', e.transform));

        svg.call(zoom);
        svg.call(zoom.transform, d3.zoomIdentity.translate(initialTx, 20));

        g.selectAll('path.org-link')
            .data(root.links())
            .join('path')
            .attr('class', 'org-link')
            .attr('d', d => {
                const sx = d.source.x;
                const sy = d.source.y + NH;
                const tx = d.target.x;
                const ty = d.target.y;
                const my = sy + (ty - sy) * 0.5;
                return `M${sx},${sy} C${sx},${my} ${tx},${my} ${tx},${ty}`;
            });

        const nodeG = g.selectAll('g.org-node')
            .data(root.descendants())
            .join('g')
            .attr('class', 'org-node')
            .attr('data-empid', d => d.data.id)
            .attr('transform', d => `translate(${d.x - NW / 2}, ${d.y})`)
            .style('cursor', d => (d.data.profileUrl && d.data.profileUrl !== '#') ? 'pointer' : 'default')
            .on('click', (e, d) => {
                if (d.data.profileUrl && d.data.profileUrl !== '#') {
                    window.location.href = d.data.profileUrl;
                }
            });

        const defs = svg.append('defs');
        const filter = defs.append('filter')
            .attr('id', 'nodeDropShadow')
            .attr('x', '-10%')
            .attr('y', '-10%')
            .attr('width', '120%')
            .attr('height', '130%');
        filter.append('feDropShadow')
            .attr('dx', 0)
            .attr('dy', 2)
            .attr('stdDeviation', 3)
            .attr('flood-color', 'rgba(0,0,0,0.12)');

        nodeG.append('rect')
            .attr('width', NW)
            .attr('height', NH)
            .attr('rx', 9)
            .attr('fill', d => d.data.id === 0 ? ROOT_COLOR : BRAND)
            .attr('filter', 'url(#nodeDropShadow)');

        nodeG.append('rect')
            .attr('y', NH - 18)
            .attr('width', NW)
            .attr('height', 18)
            .attr('rx', 9)
            .attr('fill', 'rgba(0,0,0,0.18)');

        nodeG.append('rect')
            .attr('y', NH - 18)
            .attr('width', NW)
            .attr('height', 9)
            .attr('fill', 'rgba(0,0,0,0.18)');

        nodeG.append('circle')
            .attr('cx', 14)
            .attr('cy', 20)
            .attr('r', 4)
            .attr('fill', d => d.data.status === 'active' ? '#22c55e' : '#94a3b8');

        nodeG.append('text')
            .attr('x', 26)
            .attr('y', 24)
            .attr('fill', '#fff')
            .attr('font-size', 12)
            .attr('font-weight', 700)
            .attr('font-family', 'inherit')
            .text(d => truncate(d.data.name, 22));

        nodeG.append('text')
            .attr('x', 26)
            .attr('y', 38)
            .attr('fill', 'rgba(255,255,255,0.75)')
            .attr('font-size', 10)
            .attr('font-family', 'inherit')
            .text(d => truncate(d.data.title || '', 26));

        nodeG.append('text')
            .attr('x', NW / 2)
            .attr('y', NH - 5)
            .attr('fill', 'rgba(255,255,255,0.65)')
            .attr('font-size', 9)
            .attr('font-family', 'inherit')
            .attr('text-anchor', 'middle')
            .text(d => truncate(d.data.department || '', 28));
    }

    render(rawNodes);

    document.getElementById('orgSearch').addEventListener('input', applyFilters);
    deptSel.addEventListener('change', applyFilters);

    function applyFilters() {
        const q = document.getElementById('orgSearch').value.toLowerCase().trim();
        const dept = deptSel.value;
        let filtered = rawNodes;

        if (dept) {
            filtered = filtered.filter(n => n.department === dept);
        }

        if (q) {
            filtered = filtered.filter(n =>
                n.name.toLowerCase().includes(q) || (n.title || '').toLowerCase().includes(q)
            );
        }

        if (q || dept) {
            const keep = new Set(filtered.map(n => n.id));
            rawNodes.forEach(n => {
                if (keep.has(n.id)) {
                    let cur = n;
                    while (cur.pid) {
                        keep.add(cur.pid);
                        cur = rawNodes.find(x => x.id === cur.pid) || { pid: null };
                    }
                }
            });
            filtered = rawNodes.filter(n => keep.has(n.id));
        }

        render(filtered);

        if (q) {
            document.querySelectorAll('#orgChartContainer .org-node').forEach(el => {
                const empId = parseInt(el.dataset.empid || 0, 10);
                const match = rawNodes.find(n => n.id === empId);
                if (match && (match.name.toLowerCase().includes(q) || (match.title || '').toLowerCase().includes(q))) {
                    el.classList.add('highlighted');
                }
            });
        }
    }

    document.getElementById('orgExpandAll').addEventListener('click', function () {
        if (!svgEl) return;
        const svg = d3.select(svgEl);
        const g = svg.select('#orgG');
        const bbox = g.node().getBBox();
        const cw = svgEl.clientWidth;
        const ch = svgEl.clientHeight;
        const scale = Math.min(0.95, Math.min(cw / bbox.width, ch / bbox.height));
        const tx = (cw - bbox.width * scale) / 2 - bbox.x * scale;
        const ty = (ch - bbox.height * scale) / 2 - bbox.y * scale;
        svg.transition().duration(400)
            .call(d3.zoom().transform, d3.zoomIdentity.translate(tx, ty).scale(scale));
    });

    document.getElementById('orgCollapseAll').addEventListener('click', function () {
        if (!svgEl) return;
        const svg = d3.select(svgEl);
        svg.transition().duration(400)
            .call(d3.zoom().transform, d3.zoomIdentity.translate(
                svgEl.clientWidth / 2 - (parseFloat(d3.select('#orgChartContainer .org-node').attr('transform')?.match(/translate\(([^,]+)/)?.[1] || 0) + NW / 2),
                20
            ).scale(1));
    });

    document.getElementById('orgExport').addEventListener('click', function () {
        if (!svgEl) return;
        const svgClone = svgEl.cloneNode(true);
        svgClone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        const serializer = new XMLSerializer();
        const svgStr = serializer.serializeToString(svgClone);
        const canvas = document.createElement('canvas');
        const scale = 2;
        canvas.width = svgEl.clientWidth * scale;
        canvas.height = svgEl.clientHeight * scale;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const img = new Image();
        img.onload = () => {
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            const link = document.createElement('a');
            link.download = 'Org-Chart-<?= date('Y-m-d'); ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgStr);
    });
}());
</script>
