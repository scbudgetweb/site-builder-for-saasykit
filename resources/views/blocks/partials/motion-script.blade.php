{{--
    Starts entrance animations as their blocks come into view (Design › Animation): marks <html> with .sb-motion, and
    each animated block (or item, .sb-item) with .sb-in-view once it's a fifth of the way up the window. Included once per page, by the first block that
    animates. Visitors who ask for less motion get nothing, and the blocks simply show.
--}}
<script>
    (() => {
        if (window.siteBuilderMotion || window.matchMedia('(prefers-reduced-motion: reduce)').matches || ! ('IntersectionObserver' in window)) {
            return;
        }

        window.siteBuilderMotion = true;
        document.documentElement.classList.add('sb-motion');

        const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('sb-in-view');
                observer.unobserve(entry.target);
            }
        // Once its top is a fifth of the way up the window, so it plays where it can be seen.
        }), { rootMargin: '0px 0px -20% 0px', threshold: 0 });

        // The block, or with "Each item" its items.
        const watch = () => document.querySelectorAll('.sb-d-animate:not(.sb-d-animate-items):not([data-sb-watched]), .sb-d-animate-items .sb-item:not([data-sb-watched])').forEach((element) => {
            element.setAttribute('data-sb-watched', '');
            observer.observe(element);
        });

        watch();
        document.addEventListener('DOMContentLoaded', watch);
        new MutationObserver(watch).observe(document.documentElement, { childList: true, subtree: true });
    })();
</script>
