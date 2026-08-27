document.addEventListener('DOMContentLoaded', () => {
    const requested = new URLSearchParams(window.location.search).get('section') ?? 'overview';
    const section = ['overview', 'classes', 'stats', 'reports', 'profile', 'password'].includes(requested)
        ? requested
        : 'overview';

    showSection(section);

    if (typeof applyChartDefaults === 'function') {
        applyChartDefaults();
    }

    if (section === 'stats' && typeof loadTeacherStats === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => loadTeacherStats()));
    }
});
