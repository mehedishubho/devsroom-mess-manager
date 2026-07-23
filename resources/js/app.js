import './bootstrap';
import Chart from 'chart.js/auto';

// Global init helper for dashboard charts (Phase 4 Plan 4.3).
// Blade passes data via @json into a small inline script that calls this.
// Destroy-before-recreate is MANDATORY (Pitfall 2: canvas reuse leaks memory).
window.initDashboardChart = function (canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) return null;

    if (el.__chart) {
        el.__chart.destroy();
    }

    el.__chart = new Chart(el.getContext('2d'), {
        type: config.type,
        data: config.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { mode: 'index', intersect: false },
            },
            // Doughnut/pie/polarArea charts reject cartesian scales — only emit
            // them for cartesian types (line/bar).
            ...(['line', 'bar'].includes(config.type) ? {
                scales: {
                    x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
                    y: { ticks: { font: { size: 10 } }, beginAtZero: true },
                },
            } : {}),
        },
    });

    return el.__chart;
};
