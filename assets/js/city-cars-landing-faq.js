/**
 * FAQ accordion on city cars landing pages.
 */
document.querySelectorAll('body.autoagora-city-cars-landing .faq-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var item = this.closest('.faq-item');
        var isOpen = item.classList.contains('open');
        document.querySelectorAll('body.autoagora-city-cars-landing .faq-item.open').forEach(function (el) {
            el.classList.remove('open');
            el.querySelector('.faq-trigger').setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
            item.classList.add('open');
            this.setAttribute('aria-expanded', 'true');
        }
    });
});
