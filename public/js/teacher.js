document.addEventListener('DOMContentLoaded', () => {
    const requested = new URLSearchParams(window.location.search).get('section') ?? 'overview';
    const normalized = requested === 'password' ? 'security' : requested;
    const section = ['overview', 'classes', 'stats', 'profile', 'security'].includes(normalized)
        ? normalized
        : 'overview';

    showSection(section);

    if (typeof applyChartDefaults === 'function') {
        applyChartDefaults();
    }

    if (section === 'stats' && typeof loadTeacherStats === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => loadTeacherStats()));
    }
});
