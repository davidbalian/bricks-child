(function () {
    'use strict';

    function initDescriptionToggle() {
        var description = document.querySelector('[data-single-car-description]');
        var button = document.querySelector('[data-single-car-read-more]');

        if (!description || !button) {
            return;
        }

        description.classList.add('is-collapsible');
        if (description.scrollHeight <= description.clientHeight + 8) {
            description.classList.remove('is-collapsible');
            return;
        }

        button.hidden = false;
        button.addEventListener('click', function () {
            var expanded = description.classList.toggle('is-expanded');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.textContent = expanded ? 'Read Less' : 'Read More';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDescriptionToggle);
    } else {
        initDescriptionToggle();
    }
}());
