(function () {
    function initProfileLoader() {
        const loader = document.getElementById('profile-loader');
        if (!loader) return;

        const fill = loader.querySelector('.loader-color-fill');
        const percent = loader.querySelector('.loader-percent');
        const start = performance.now();
        const duration = 2300;

        document.documentElement.classList.add('loader-active');
        document.body.classList.add('loader-active');

        function frame(now) {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            const pct = Math.round(eased * 100);
            if (fill) fill.style.clipPath = `inset(${100 - pct}% 0 0 0)`;
            const bar = loader.querySelector('.loader-progress-bar');
            if (bar) bar.style.transform = `scaleX(${eased})`;
            if (percent) percent.textContent = pct + '%';
            if (progress < 1) {
                requestAnimationFrame(frame);
            } else {
                loader.classList.add('loader-complete');
                window.setTimeout(function () {
                    loader.remove();
                    document.documentElement.classList.remove('loader-active');
                    document.body.classList.remove('loader-active');
                }, 450);
            }
        }
        requestAnimationFrame(frame);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileLoader);
    } else {
        initProfileLoader();
    }
})();
