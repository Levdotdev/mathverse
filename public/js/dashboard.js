(() => {
    const roots = [...document.querySelectorAll('[data-profile-root]')];

    function closeAll(except = null) {
        roots.forEach(root => {
            if (root === except) return;
            root.querySelector('[data-profile-menu]')?.classList.remove('open');
            root.querySelector('[data-profile-menu]')?.setAttribute('aria-hidden', 'true');
            root.querySelector('[data-profile-toggle]')?.setAttribute('aria-expanded', 'false');
        });
    }

    roots.forEach(root => {
        const toggle = root.querySelector('[data-profile-toggle]');
        const menu = root.querySelector('[data-profile-menu]');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', event => {
            event.stopPropagation();
            const willOpen = !menu.classList.contains('open');
            closeAll(root);
            menu.classList.toggle('open', willOpen);
            menu.setAttribute('aria-hidden', String(!willOpen));
            toggle.setAttribute('aria-expanded', String(willOpen));
            if (willOpen) {
                document.dispatchEvent(new CustomEvent('mathverse:header-menu-open', {
                    detail: { kind: 'profile' },
                }));
            }
        });
        menu.addEventListener('click', event => {
            event.stopPropagation();
            if (event.target.closest('a, button')) closeAll();
        });
    });

    document.addEventListener('mathverse:header-menu-open', event => {
        if (event.detail?.kind !== 'profile') closeAll();
    });
    document.addEventListener('click', () => closeAll());

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const openRoot = roots.find(root => root.querySelector('[data-profile-menu]')?.classList.contains('open'));
        closeAll();
        openRoot?.querySelector('[data-profile-toggle]')?.focus();
    });
    window.addEventListener('resize', () => closeAll());
    window.addEventListener('orientationchange', () => closeAll());
})();
