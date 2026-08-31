document.addEventListener('DOMContentLoaded', () => {
    const roots = [...document.querySelectorAll('[data-notification-root]')];

    function closeAll(except = null) {
        roots.forEach(root => {
            if (root === except) return;
            root.querySelector('[data-notification-menu]')?.classList.add('hidden');
            root.querySelector('[data-notification-menu]')?.setAttribute('aria-hidden', 'true');
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
            menu.setAttribute('aria-hidden', String(!willOpen));
            toggle.setAttribute('aria-expanded', String(willOpen));
            if (willOpen) {
                document.dispatchEvent(new CustomEvent('mathverse:header-menu-open', {
                    detail: { kind: 'notifications' },
                }));
            }
        });
        menu.addEventListener('click', event => event.stopPropagation());
    });

    document.addEventListener('click', () => closeAll());
    document.addEventListener('mathverse:header-menu-open', event => {
        if (event.detail?.kind !== 'notifications') closeAll();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const openRoot = roots.find(root => !root.querySelector('[data-notification-menu]')?.classList.contains('hidden'));
        closeAll();
        openRoot?.querySelector('[data-notification-toggle]')?.focus();
    });
    window.addEventListener('resize', () => closeAll());
    window.addEventListener('orientationchange', () => closeAll());
});
