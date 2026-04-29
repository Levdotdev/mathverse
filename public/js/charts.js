// public/js/charts.js

const CHART_DEFAULTS = {
    color: {
        cyan:   '#00f2ff',
        purple: '#bc13fe',
        pink:   '#ff00de',
        green:  '#22c55e',
        orange: '#f97316',
        red:    '#ef4444',
        grid:   'rgba(255,255,255,0.05)',
        text:   'rgba(255,255,255,0.5)',
    }
};

// Shared Chart.js global defaults for dark theme
function applyChartDefaults() {
    Chart.defaults.color          = CHART_DEFAULTS.color.text;
    Chart.defaults.borderColor    = CHART_DEFAULTS.color.grid;
    Chart.defaults.font.family    = 'Rajdhani, sans-serif';
    Chart.defaults.font.size      = 11;
}

// ── Reusable chart builders ───────────────────────────────

function buildLineChart(canvasId, labels, data, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label,
                data,
                borderColor:     color,
                backgroundColor: color + '20',
                borderWidth:     2,
                pointBackgroundColor: color,
                pointRadius:     3,
                pointHoverRadius: 5,
                fill:            true,
                tension:         0.4,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(5,5,5,0.9)',
                    borderColor:     color,
                    borderWidth:     1,
                    titleColor:      color,
                    bodyColor:       '#fff',
                }
            },
            scales: {
                x: {
                    grid:  { color: CHART_DEFAULTS.color.grid },
                    ticks: { color: CHART_DEFAULTS.color.text, maxRotation: 45 }
                },
                y: {
                    beginAtZero: true,
                    grid:  { color: CHART_DEFAULTS.color.grid },
                    ticks: { color: CHART_DEFAULTS.color.text, stepSize: 1 }
                }
            }
        }
    });
}

function buildBarChart(canvasId, labels, data, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label,
                data,
                backgroundColor: color + '80',
                borderColor:     color,
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(5,5,5,0.9)',
                    borderColor:     color,
                    borderWidth:     1,
                    titleColor:      color,
                    bodyColor:       '#fff',
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y}%`
                    }
                }
            },
            scales: {
                x: {
                    grid:  { color: CHART_DEFAULTS.color.grid },
                    ticks: {
                        color:       CHART_DEFAULTS.color.text,
                        maxRotation: 45,
                        font:        { size: 10 }
                    }
                },
                y: {
                    beginAtZero: true,
                    max:         100,
                    grid:        { color: CHART_DEFAULTS.color.grid },
                    ticks: {
                        color:    CHART_DEFAULTS.color.text,
                        stepSize: 25,
                        callback: v => v + '%'
                    }
                }
            }
        }
    });
}

function buildDoughnutChart(canvasId, labels, data, colors) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors.map(c => c + '99'),
                borderColor:     colors,
                borderWidth:     2,
                hoverOffset:     8,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: true,
            cutout:              '65%',
            plugins: {
                legend: {
                    display:  true,
                    position: 'bottom',
                    labels: {
                        color:     '#fff',
                        padding:   16,
                        font:      { size: 11 },
                        boxWidth:  12,
                        boxHeight: 12,
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(5,5,5,0.9)',
                    borderWidth:     1,
                    titleColor:      '#fff',
                    bodyColor:       '#fff',
                }
            }
        }
    });
}

// ── Teacher stats loader ──────────────────────────────────

async function loadTeacherStats() {
    try {
        const data = await fetch('/teacher/stats').then(r => r.json());

        // Summary cards
        document.getElementById('stat-total-attempts').innerText = data.totalAttempts;
        document.getElementById('stat-avg-accuracy').innerText   = data.avgAccuracy + '%';
        document.getElementById('stat-total-quizzes').innerText  = data.totalQuizzes;

        // Attempts per day line chart
        buildLineChart(
            'chart-attempts',
            data.attemptsPerDay.map(d => d.date),
            data.attemptsPerDay.map(d => d.count),
            'Attempts',
            CHART_DEFAULTS.color.cyan
        );

        // Score distribution doughnut
        buildDoughnutChart(
            'chart-distribution',
            ['0–25%', '26–50%', '51–75%', '76–100%'],
            data.distribution,
            [
                CHART_DEFAULTS.color.red,
                CHART_DEFAULTS.color.orange,
                CHART_DEFAULTS.color.cyan,
                CHART_DEFAULTS.color.green,
            ]
        );

        // Per-quiz accuracy bar chart
        if (data.quizAccuracy.length > 0) {
            buildBarChart(
                'chart-quiz-accuracy',
                data.quizAccuracy.map(q => q.topic),
                data.quizAccuracy.map(q => q.accuracy),
                'Avg Accuracy %',
                CHART_DEFAULTS.color.purple
            );
        }

    } catch (err) {
        console.error('Failed to load teacher stats:', err);
    }
}

// ── Admin stats loader ────────────────────────────────────

async function loadAdminStats() {
    try {
        const data = await fetch('/admin/stats').then(r => r.json());

        // Summary cards
        document.getElementById('stat-total-attempts').innerText = data.totalAttempts;
        document.getElementById('stat-avg-accuracy').innerText   = data.avgAccuracy + '%';
        document.getElementById('stat-total-users').innerText    = data.totalUsers;

        // Attempts per day
        buildLineChart(
            'chart-attempts',
            data.attemptsPerDay.map(d => d.date),
            data.attemptsPerDay.map(d => d.count),
            'Attempts',
            CHART_DEFAULTS.color.cyan
        );

        // Registrations per day
        buildLineChart(
            'chart-registrations',
            data.registrationsPerDay.map(d => d.date),
            data.registrationsPerDay.map(d => d.count),
            'Registrations',
            CHART_DEFAULTS.color.green
        );

        // Role breakdown doughnut
        buildDoughnutChart(
            'chart-roles',
            ['Students', 'Teachers', 'Pending'],
            [
                data.roleBreakdown.students,
                data.roleBreakdown.teachers,
                data.roleBreakdown.pending,
            ],
            [
                CHART_DEFAULTS.color.cyan,
                CHART_DEFAULTS.color.purple,
                CHART_DEFAULTS.color.orange,
            ]
        );

        // Score distribution doughnut
        buildDoughnutChart(
            'chart-distribution',
            ['0–25%', '26–50%', '51–75%', '76–100%'],
            data.distribution,
            [
                CHART_DEFAULTS.color.red,
                CHART_DEFAULTS.color.orange,
                CHART_DEFAULTS.color.cyan,
                CHART_DEFAULTS.color.green,
            ]
        );

    } catch (err) {
        console.error('Failed to load admin stats:', err);
    }
}