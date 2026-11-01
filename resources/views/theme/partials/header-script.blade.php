{{--
    How the header behaves as the page scrolls (Templates\HeaderBehaviour; resources/css/header.css draws it), for each
    [data-sb-header]. Included once, by a header with any behaviour on.
     - Once the page has scrolled (a header that stays at the top): .sb-header-scrolled, for shrinking, the shadow and
       turning solid.
     - Only when scrolling back up: .sb-header-hidden while scrolling down past it, never while focus is inside it
       (an open menu, or someone tabbing through its links).
     - Over the first section: its height in --sb-header-height (measured before it shrinks), so the opening banner
       leaves room for it, and the other logo (data-over-logo) while it's see-through.
--}}
<script>
    (() => {
        if (window.siteBuilderHeader) {
            return;
        }

        window.siteBuilderHeader = true;

        const start = (header) => {
            if (header.hasAttribute('data-sb-started')) {
                return;
            }

            header.setAttribute('data-sb-started', '');

            const stays = header.classList.contains('sb-header-sticky');
            const hides = header.classList.contains('sb-header-hides');
            const isOver = header.classList.contains('sb-header-over');
            const overLogo = header.dataset.overLogo;
            let lastY = window.scrollY;

            const measure = () => {
                if (! header.classList.contains('sb-header-scrolled')) {
                    document.documentElement.style.setProperty('--sb-header-height', `${header.offsetHeight}px`);
                }
            };

            // The header's own logos (not the mobile menu's, on its own background). Found again each time: the visual
            // builder's canvas swaps a template's blocks as they change.
            const swapLogos = (seeThrough) => header.querySelectorAll('img.sb-logo').forEach((logo) => {
                if (logo.closest('.sb-mobile-menu')) {
                    return;
                }

                logo.dataset.sbLogo ??= logo.getAttribute('src');
                const src = seeThrough ? overLogo : logo.dataset.sbLogo;

                if (logo.getAttribute('src') !== src) {
                    logo.setAttribute('src', src);
                }
            });

            const update = () => {
                const y = Math.max(0, window.scrollY);
                const scrolled = stays && y > 8;

                header.classList.toggle('sb-header-scrolled', scrolled);

                if (hides && Math.abs(y - lastY) > 4) {
                    header.classList.toggle('sb-header-hidden', y > lastY && y > header.offsetHeight && ! header.contains(document.activeElement));
                }

                if (hides && y <= header.offsetHeight) {
                    header.classList.remove('sb-header-hidden');
                }

                if (Math.abs(y - lastY) > 4 || y === 0) {
                    lastY = y;
                }

                if (isOver && overLogo) {
                    swapLogos(! scrolled);
                }
            };

            if (isOver) {
                measure();
                new ResizeObserver(measure).observe(header);
            }

            header.addEventListener('focusin', () => header.classList.remove('sb-header-hidden'));
            window.addEventListener('scroll', update, { passive: true });
            update();
        };

        const startAll = () => document.querySelectorAll('[data-sb-header]').forEach(start);

        startAll();
        document.addEventListener('DOMContentLoaded', startAll);
    })();
</script>
