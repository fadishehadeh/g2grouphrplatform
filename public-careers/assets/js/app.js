document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-auto-focus="true"]').forEach(function (element) {
        element.focus();
    });

    var careersNav = document.getElementById('careersNav');
    var careersToggler = document.querySelector('.careers-topbar .navbar-toggler');

    function isMobileNavMode() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function collapseCareersNav() {
        if (!careersNav || !isMobileNavMode()) {
            return;
        }

        var collapse = bootstrap.Collapse.getInstance(careersNav);
        if (collapse) {
            collapse.hide();
        }
    }

    if (careersNav) {
        careersNav.querySelectorAll('a, button[type="submit"]').forEach(function (element) {
            element.addEventListener('click', function () {
                if (element === careersToggler) {
                    return;
                }
                collapseCareersNav();
            });
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileNavMode() && careersNav) {
            careersNav.classList.remove('show');
        }
    });
});
