(function () {
    'use strict';

    var root = document.documentElement;
    var drawer = document.getElementById('aag-site-header-drawer');
    var openButton = document.querySelector('.aag-site-header__menu-toggle');
    var closeButton = document.querySelector('.aag-site-header__drawer-close');
    var mobileDock = document.querySelector('.aag-mobile-dock');
    var dockViewportBaseline = window.visualViewport ? Math.max(window.innerHeight, window.visualViewport.height) : window.innerHeight;
    var dockViewportWidth = window.innerWidth;
    var drawerCloseTimer;
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

        window.clearTimeout(drawerCloseTimer);
        openButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        root.classList.toggle('aag-site-header-menu-open', isOpen);

        if (isOpen) {
            drawer.hidden = false;
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    drawer.classList.add('is-open');
                    if (closeButton) {
                        closeButton.focus();
                    }
                });
            });
        } else {
            drawer.classList.remove('is-open');
            drawerCloseTimer = window.setTimeout(function () {
                drawer.hidden = true;
            }, 180);

            if (document.activeElement === closeButton) {
                openButton.focus();
            }
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

    /*
     * Mobile browsers may move fixed controls above the software keyboard.
     * Hide the dock only for a material visual-viewport reduction; normal
     * Safari/Chrome toolbar animations are much smaller and remain unaffected.
     */
    function syncDockWithVisualViewport() {
        if (!mobileDock || !window.visualViewport || window.innerWidth > 767) {
            return;
        }

        var visualViewport = window.visualViewport;
        var obscuredHeight = Math.max(0, window.innerHeight - visualViewport.height - visualViewport.offsetTop);
        var activeElement = document.activeElement;
        var hasTextInputFocus = activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(activeElement.tagName);

        if (Math.abs(window.innerWidth - dockViewportWidth) > 50) {
            dockViewportWidth = window.innerWidth;
            dockViewportBaseline = Math.max(window.innerHeight, visualViewport.height);
        } else if (!hasTextInputFocus) {
            dockViewportBaseline = Math.max(dockViewportBaseline, window.innerHeight, visualViewport.height);
        }

        var viewportReduction = Math.max(0, dockViewportBaseline - visualViewport.height);
        var keyboardIsOpen = hasTextInputFocus && Math.max(obscuredHeight, viewportReduction) > 120;

        mobileDock.classList.toggle('aag-mobile-dock--keyboard-open', keyboardIsOpen);
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncDockWithVisualViewport);
        window.visualViewport.addEventListener('scroll', syncDockWithVisualViewport);
        document.addEventListener('focusin', syncDockWithVisualViewport);
        document.addEventListener('focusout', function () {
            window.setTimeout(syncDockWithVisualViewport, 0);
        });
        syncDockWithVisualViewport();
    }
}());
