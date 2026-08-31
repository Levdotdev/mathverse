document.addEventListener('DOMContentLoaded', () => {
    const roots = [...document.querySelectorAll('[data-notification-root]')];

    function closeAll(except = null) {
        roots.forEach(root => {
            if (root === except) return;
            root.querySelector('[data-notification-menu]')?.classList.add('hidden');
            root.querySelector('[data-notification-toggle]')?.setAttribute('aria-expanded', 'false');
        });
    }

    roots.forEach(root => {
        const toggle = root.querySelector('[data-notification-toggle]');
        const menu = root.querySelector('[data-notification-menu]');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', event => {
            event.stopPropagation();
            const willOpen = menu.classList.contains('hidden');
            closeAll(root);
            menu.classList.toggle('hidden', !willOpen);
            toggle.setAttribute('aria-expanded', String(willOpen));
        });
        menu.addEventListener('click', event => event.stopPropagation());
    });

    document.addEventListener('click', () => closeAll());
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeAll();
    });
});
