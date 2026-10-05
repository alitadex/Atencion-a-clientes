/**
 * assets/app.js
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: comportamientos generales de la interfaz,
 * disponibles en todas las páginas.
 * ------------------------------------------------------------
 */
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-entering');

    // Elementos con data-confirm="mensaje": piden confirmación antes de actuar.
    document.querySelectorAll('[data-confirm]').forEach(el =>
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        })
    );

    // Selects que envían su formulario automáticamente.
    document.querySelectorAll('.auto-submit').forEach(el =>
        el.addEventListener('change', () => el.form.submit())
    );

    // Transición entre módulos. Se aplica únicamente a enlaces internos de
    // navegación para no interferir con formularios, descargas o logout.
    const navLinks = document.querySelectorAll('.nav-item[href]');
    if (navLinks.length) {
        const overlay = document.createElement('div');
        overlay.className = 'nav-transition';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.appendChild(overlay);

        navLinks.forEach(link => {
            link.addEventListener('click', event => {
                const href = link.getAttribute('href');
                if (!href || href === '#' || link.target === '_blank' || link.hasAttribute('download')) return;

                // Dejar que el navegador gestione Ctrl/Cmd/Shift/Alt + clic.
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;

                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin || url.href === window.location.href) return;

                event.preventDefault();
                link.classList.add('nav-leaving');
                overlay.classList.add('is-visible');

                window.setTimeout(() => {
                    window.location.href = url.href;
                }, 190);
            });
        });

        // Al volver con Atrás/Adelante, el navegador puede restaurar la página
        // desde caché; quitamos cualquier overlay que haya quedado visible.
        window.addEventListener('pageshow', () => {
            overlay.classList.remove('is-visible');
            navLinks.forEach(link => link.classList.remove('nav-leaving'));
        });
    }
});
