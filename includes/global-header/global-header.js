(function () {
    'use strict';

    var root = document.documentElement;
    var drawer = document.getElementById('aag-site-header-drawer');
    var openButton = document.querySelector('.aag-site-header__menu-toggle');
    var closeButton = document.querySelector('.aag-site-header__drawer-close');
    var detailsElements = Array.prototype.slice.call(document.querySelectorAll('.aag-site-header__details'));

    function closeDetails(except) {
        detailsElements.forEach(function (details) {
            if (details !== except) {
                details.removeAttribute('open');
            }
        });
    }

    detailsElements.forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (details.open) {
                closeDetails(details);
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.aag-site-header__details')) {
            closeDetails();
        }
    });

    function setDrawerOpen(isOpen) {
        if (!drawer || !openButton) {
            return;
        }

        drawer.hidden = !isOpen;
        openButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        root.classList.toggle('aag-site-header-menu-open', isOpen);

        if (isOpen && closeButton) {
            closeButton.focus();
        } else if (!isOpen && document.activeElement === closeButton) {
            openButton.focus();
        }
    }

    if (openButton && drawer) {
        openButton.addEventListener('click', function () {
            setDrawerOpen(true);
        });

        drawer.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setDrawerOpen(false);
            });
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDetails();
            if (drawer && !drawer.hidden) {
                setDrawerOpen(false);
            }
        }

        if (event.key === 'Tab' && drawer && !drawer.hidden) {
            var focusable = drawer.querySelectorAll('a[href], button:not([disabled])');
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 767 && drawer && !drawer.hidden) {
            setDrawerOpen(false);
        }
    });
}());
