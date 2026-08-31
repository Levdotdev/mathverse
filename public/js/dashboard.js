(() => {
    const profileMenu = document.getElementById('profileMenu');
    const profileArrow = document.getElementById('profileArrow');
    const profileButton = document.querySelector('[data-profile-toggle]');

    function closeProfileMenu() {
        if (!profileMenu || !profileButton) return;
        profileMenu.classList.remove('open');
        profileArrow?.classList.remove('rotate-180');
        profileButton.setAttribute('aria-expanded', 'false');
    }

    window.toggleProfileMenu = event => {
        event?.stopPropagation();
        if (!profileMenu || !profileButton) return;
        const willOpen = !profileMenu.classList.contains('open');
        profileMenu.classList.toggle('open', willOpen);
        profileArrow?.classList.toggle('rotate-180', willOpen);
        profileButton.setAttribute('aria-expanded', String(willOpen));
    };

    document.addEventListener('click', event => {
        if (!profileMenu?.contains(event.target) && !profileButton?.contains(event.target)) {
            closeProfileMenu();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeProfileMenu();
    });
})();
