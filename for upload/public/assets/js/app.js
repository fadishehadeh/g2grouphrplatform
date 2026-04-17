document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('[data-auto-focus="true"]').forEach(function (element) {
        element.focus();
    });

    // Mobile sidebar toggle
    var sidebar   = document.getElementById('appSidebar');
    var overlay   = document.getElementById('sidebarOverlay');
    var toggleBtn = document.getElementById('sidebarToggle');
    var closeBtn  = document.getElementById('sidebarClose');

    function openSidebar(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (sidebar) sidebar.classList.add('sidebar-open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (sidebar) sidebar.classList.remove('sidebar-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', openSidebar);
        toggleBtn.addEventListener('touchend', openSidebar);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
        closeBtn.addEventListener('touchend', closeSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
});
