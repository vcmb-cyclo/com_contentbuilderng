(() => {
    'use strict';

    const valueLabels = {
        id: 'cbstatsBarValueLabels',
        afterDatasetsDraw(chart) {
            const items = chart.options.plugins.cbstatsBarValueLabels.items;
            const context = chart.ctx;

            chart.getDatasetMeta(0).data.forEach((bar, index) => {
                const item = items[index];

                if (!item) {
                    return;
                }

                const label = String(item.value);
                context.save();
                context.font = '600 12px system-ui, sans-serif';

                if (Math.abs(bar.x - bar.base) < context.measureText(label).width + 16) {
                    context.restore();
                    return;
                }

                context.fillStyle = '#ffffff';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText(label, (bar.x + bar.base) / 2, bar.y);
                context.restore();
            });
        },
    };

    const initialise = (root) => {
        if (root.dataset.cbstatsInitialised === 'true') {
            return;
        }

        let payload;

        try {
            payload = JSON.parse(root.dataset.cbstatsBar || '{"items":[]}');
        } catch (error) {
            return;
        }

        const items = Array.isArray(payload.items) ? payload.items : [];
        const canvas = root.querySelector('.cbstats-bar-canvas');

        if (!canvas || items.length === 0 || typeof Chart === 'undefined') {
            return;
        }

        root.dataset.cbstatsInitialised = 'true';

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: items.map((item) => item.label),
                datasets: [{
                    data: items.map((item) => item.value),
                    backgroundColor: items.map((item) => item.color),
                    borderColor: items.map((item) => item.color),
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }],
            },
            plugins: [valueLabels],
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 450,
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                    },
                    y: {
                        grid: {
                            display: false,
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    cbstatsBarValueLabels: {
                        items,
                    },
                    tooltip: {
                        titleAlign: 'center',
                        bodyAlign: 'left',
                        displayColors: true,
                        boxPadding: 6,
                        callbacks: {
                            title: (tooltipItems) => tooltipItems[0]?.label || '',
                            label: (context) => {
                                const item = items[context.dataIndex];
                                return item ? `${item.value} (${item.percentageLabel} %)` : '';
                            },
                        },
                    },
                },
            },
        });
    };

    const initialiseAll = () => {
        document.querySelectorAll('[data-cbstats-bar]').forEach(initialise);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseAll, { once: true });
    } else {
        initialiseAll();
    }
})();
