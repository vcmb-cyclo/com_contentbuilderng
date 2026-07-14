(() => {
    'use strict';

    const percentageLabels = {
        id: 'cbstatsPercentageLabels',
        afterDatasetsDraw(chart) {
            const items = chart.options.plugins.cbstatsPercentageLabels.items;
            const context = chart.ctx;

            chart.getDatasetMeta(0).data.forEach((arc, index) => {
                const item = items[index];

                if (!item || item.percentage < 8 || arc.circumference < 0.45) {
                    return;
                }

                const position = arc.tooltipPosition();
                context.save();
                context.fillStyle = '#ffffff';
                context.font = '600 13px system-ui, sans-serif';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.shadowColor = 'rgba(0, 0, 0, 0.55)';
                context.shadowBlur = 3;
                context.fillText(`${item.percentageLabel} %`, position.x, position.y);
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
            payload = JSON.parse(root.dataset.cbstatsPie || '{"items":[]}');
        } catch (error) {
            return;
        }

        const items = Array.isArray(payload.items) ? payload.items : [];
        const canvas = root.querySelector('.cbstats-pie-canvas');

        if (!canvas || items.length === 0 || typeof Chart === 'undefined') {
            return;
        }

        root.dataset.cbstatsInitialised = 'true';

        new Chart(canvas, {
            type: 'pie',
            data: {
                labels: items.map((item) => item.label),
                datasets: [{
                    data: items.map((item) => item.value),
                    backgroundColor: items.map((item) => item.color),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 6,
                }],
            },
            plugins: [percentageLabels],
            options: {
                responsive: true,
                maintainAspectRatio: true,
                animation: {
                    duration: 450,
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    cbstatsPercentageLabels: {
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
        document.querySelectorAll('[data-cbstats-pie]').forEach(initialise);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseAll, { once: true });
    } else {
        initialiseAll();
    }
})();
