(function () {
    'use strict';

    function continueImport() {
        var form = document.getElementById('car-json-import-continue');
        if (!form || form.dataset.autoSubmitted === '1') {
            return;
        }

        form.dataset.autoSubmitted = '1';
        form.setAttribute('aria-busy', 'true');

        var button = form.querySelector('[type="submit"]');
        if (button) {
            button.disabled = true;
        }

        window.HTMLFormElement.prototype.submit.call(form);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.setTimeout(continueImport, 250);
        });
    } else {
        window.setTimeout(continueImport, 250);
    }
}());
