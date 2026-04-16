<?php declare(strict_types=1); ?>

<div class="card content-card mb-3">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h5 class="mb-1">Organisational Chart</h5>
            <p class="text-muted mb-0">Live hierarchy built from manager assignments. Click any node to visit the employee profile.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="orgSearch" class="form-control" placeholder="Search name or title…" style="max-width:220px">
            <select id="orgDeptFilter" class="form-select" style="max-width:200px">
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
        <div id="orgChartContainer" style="overflow:auto; min-height:500px; padding:1.5rem; background:#f8fafc; border-radius:0 0 1rem 1rem;"></div>
    </div>
</div>

<?php
$nodesJson = json_encode($nodes ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@dabeng/orgchart@3.7.0/dist/css/orgchart.min.css">
<style>
    .orgchart { background: transparent !important; }
    .orgchart .node { cursor: pointer; }
    .orgchart .node .title {
        background: var(--brand-primary, #e63946);
        border-radius: .6rem .6rem 0 0;
        font-size: .78rem;
        font-weight: 600;
        padding: .35rem .6rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 160px;
    }
    .orgchart .node .content {
        border: 2px solid var(--brand-primary, #e63946);
        border-top: none;
        border-radius: 0 0 .6rem .6rem;
        font-size: .75rem;
        padding: .4rem .6rem;
        background: #fff;
        min-width: 140px;
        max-width: 160px;
    }
    .orgchart .node:hover > .title { opacity: .88; }
    .org-node-inner { display:flex; align-items:center; gap:.5rem; }
    .org-avatar { width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid rgba(255,255,255,.5); }
    .org-avatar-placeholder { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.95rem; color:#fff; }
    .org-name { font-weight:600; color:#fff; font-size:.8rem; line-height:1.2; }
    .org-dept { color: #64748b; font-size:.7rem; margin-top:.1rem; }
    .org-status-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:.3rem; }
    .org-status-active { background:#22c55e; }
    .org-status-other { background:#94a3b8; }
    .orgchart .node.highlighted > .title { outline: 3px solid #f59e0b; outline-offset:1px; }
    #orgChartContainer::-webkit-scrollbar { height:6px; width:6px; }
    #orgChartContainer::-webkit-scrollbar-track { background:#f1f5f9; }
    #orgChartContainer::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/@dabeng/orgchart@3.7.0/dist/js/orgchart.min.js"></script>
<script>
$(function () {
    const rawNodes = <?= $nodesJson ?>;

    if (!rawNodes.length) {
        document.getElementById('orgChartContainer').innerHTML =
            '<div class="empty-state p-5 text-center text-muted">No employees found. Add employees and assign managers to build the chart.</div>';
        return;
    }

    // ---------- build department filter ----------
    const depts = [...new Set(rawNodes.map(n => n.department).filter(Boolean))].sort();
    const deptSel = document.getElementById('orgDeptFilter');
    depts.forEach(d => {
        const o = document.createElement('option');
        o.value = d; o.textContent = d;
        deptSel.appendChild(o);
    });

    // ---------- build OrgChart datasource ----------
    function buildDS(nodes) {
        const map = {};
        nodes.forEach(n => { map[n.id] = { ...n, children: [] }; });
        const roots = [];
        nodes.forEach(n => {
            if (n.pid && map[n.pid]) {
                map[n.pid].children.push(map[n.id]);
            } else {
                roots.push(map[n.id]);
            }
        });
        if (roots.length === 1) return roots[0];
        return { id: 0, name: '<?= e((string) config('app.brand.display_name', config('app.name'))); ?>', title: '', department: '', status: 'active', photo: null, profileUrl: '#', children: roots };
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function nodeTemplate(data) {
        const avatar = data.photo
            ? `<img class="org-avatar" src="${escHtml(data.photo)}" alt="">`
            : `<div class="org-avatar-placeholder"><i class="bi bi-person-fill"></i></div>`;
        const dot = `<span class="org-status-dot ${data.status === 'active' ? 'org-status-active' : 'org-status-other'}"></span>`;
        return `<div class="org-node-inner">${avatar}<div><div class="org-name">${dot}${escHtml(data.name)}</div></div></div>`;
    }

    let chart = null;

    function render(nodes) {
        const $container = $('#orgChartContainer');
        $container.empty();
        const ds = buildDS(nodes);

        $container.orgchart({
            data:               ds,
            nodeContent:        'department',
            nodeTemplate:       nodeTemplate,
            pan:                true,
            zoom:               true,
            toggleSiblingsResp: false,
            createNode: function ($node, data) {
                if (data.profileUrl && data.profileUrl !== '#') {
                    $node.css('cursor', 'pointer').on('click', function () {
                        window.location.href = data.profileUrl;
                    });
                }
                if (data.id) $node.attr('data-empid', data.id);
            }
        });

        chart = $container.data('orgchart');
    }

    render(rawNodes);

    // ---------- search ----------
    document.getElementById('orgSearch').addEventListener('input', applyFilters);
    deptSel.addEventListener('change', applyFilters);

    function applyFilters() {
        const q    = document.getElementById('orgSearch').value.toLowerCase().trim();
        const dept = deptSel.value;
        let filtered = rawNodes;
        if (dept) filtered = filtered.filter(n => n.department === dept);
        if (q)    filtered = filtered.filter(n =>
            n.name.toLowerCase().includes(q) || (n.title || '').toLowerCase().includes(q)
        );

        // Keep ancestors so tree stays connected
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

        // highlight matched nodes after render
        if (q) {
            document.querySelectorAll('#orgChartContainer .node').forEach(el => {
                const empId = parseInt(el.dataset.empid || 0);
                const match = rawNodes.find(n => n.id === empId);
                if (match && (match.name.toLowerCase().includes(q) || (match.title || '').toLowerCase().includes(q))) {
                    el.classList.add('highlighted');
                }
            });
        }
    }

    // ---------- expand / collapse all ----------
    document.getElementById('orgExpandAll').addEventListener('click', function () {
        $('#orgChartContainer .node.collapsed').each(function () { $(this).find('> .edge.bottomEdge').trigger('click'); });
    });
    document.getElementById('orgCollapseAll').addEventListener('click', function () {
        $('#orgChartContainer .node:not(.collapsed) > .edge.bottomEdge').trigger('click');
    });

    // ---------- export PNG ----------
    document.getElementById('orgExport').addEventListener('click', function () {
        if (chart) chart.export('Org-Chart-<?= date('Y-m-d'); ?>');
    });
});
</script>
