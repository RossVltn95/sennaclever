/**
 * Dashboard Charts Module
 * Institutional Design System - Clean, Corporate Aesthetic
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

(function(window, $) {
    'use strict';

    /**
     * Dashboard Charts Module - Institutional Design
     */
    window.SFFCCharts = {

        // Chart instances storage
        instances: {},

        // Institutional Color Palette - Modern Corporate
        colors: {
            // Primary palette - Clean, subtle
            primary: '#2563eb',      // Blue accent
            secondary: '#4f46e5',    // Indigo
            tertiary: '#0891b2',     // Cyan
            accent: '#6366f1',       // Violet

            // Neutral palette
            dark: '#020617',         // Slate 950
            text: '#374151',         // Gray 700
            muted: '#6b7280',        // Gray 500
            soft: '#9ca3af',         // Gray 400
            border: 'rgba(15, 23, 42, 0.08)',

            // Status colors - Muted, professional
            success: '#22c55e',
            warning: '#f59e0b',
            danger: '#ef4444',

            // Chart-specific
            gridLine: 'rgba(148, 163, 184, 0.15)',
            gridLineDark: 'rgba(148, 163, 184, 0.3)',

            // Series colors - Institutional gradient
            series: [
                '#2563eb',  // Blue
                '#6366f1',  // Indigo
                '#0891b2',  // Cyan
                '#8b5cf6',  // Purple
                '#14b8a6',  // Teal
                '#f59e0b'   // Amber
            ],

            // Bar chart value-based colors
            high: '#22c55e',
            medium: '#2563eb',
            low: '#9ca3af'
        },

        // Default chart options - Institutional styling
        defaultOptions: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 600,
                easing: 'easeOutQuart'
            },
            layout: {
                padding: {
                    top: 10,
                    right: 10,
                    bottom: 10,
                    left: 10
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    align: 'start',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        font: {
                            family: "system-ui, -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', sans-serif",
                            size: 11,
                            weight: '400'
                        },
                        color: '#6b7280'
                    }
                },
                tooltip: {
                    backgroundColor: '#020617',
                    titleFont: {
                        family: "system-ui, -apple-system, sans-serif",
                        size: 12,
                        weight: '500'
                    },
                    bodyFont: {
                        family: "system-ui, -apple-system, sans-serif",
                        size: 11,
                        weight: '400'
                    },
                    titleColor: '#ffffff',
                    bodyColor: '#e5e7eb',
                    padding: { x: 12, y: 10 },
                    cornerRadius: 8,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    boxPadding: 4,
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    caretSize: 6
                }
            }
        },

        /**
         * Initialize the charts module
         */
        init: function() {
            if (typeof Chart !== 'undefined') {
                // Set global defaults
                Chart.defaults.font.family = "system-ui, -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', sans-serif";
                Chart.defaults.font.size = 11;
                Chart.defaults.font.weight = '400';
                Chart.defaults.color = '#6b7280';

                // Disable default animations for cleaner look
                Chart.defaults.animation.duration = 600;
            }
        },

        /**
         * Create institutional line chart
         */
        createLineChart: function(canvasId, data, options) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            var ctx = canvas.getContext('2d');
            var self = this;

            var opts = $.extend(true, {}, this.defaultOptions, {
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10, weight: '400' },
                            color: '#9ca3af',
                            padding: 8
                        },
                        border: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: this.colors.gridLine,
                            drawBorder: false,
                            lineWidth: 1
                        },
                        ticks: {
                            font: { size: 10, weight: '400' },
                            color: '#9ca3af',
                            padding: 12,
                            maxTicksLimit: 6
                        },
                        border: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    point: {
                        radius: 0,
                        hoverRadius: 5,
                        hoverBorderWidth: 2,
                        hoverBackgroundColor: '#ffffff'
                    },
                    line: {
                        tension: 0.35,
                        borderWidth: 2.5
                    }
                }
            }, options);

            // Process datasets with gradient fills
            if (data.datasets) {
                data.datasets = data.datasets.map(function(dataset, index) {
                    var gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
                    var color = dataset.borderColor || self.colors.series[index] || self.colors.primary;
                    gradient.addColorStop(0, self.hexToRgba(color, 0.15));
                    gradient.addColorStop(1, self.hexToRgba(color, 0.01));

                    return $.extend({}, dataset, {
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: color,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: color,
                        pointBorderWidth: 2,
                        tension: 0.35
                    });
                });
            }

            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: opts
            });

            this.instances[canvasId] = chart;
            return chart;
        },

        /**
         * Create institutional bar chart
         */
        createBarChart: function(canvasId, data, options) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            var ctx = canvas.getContext('2d');
            var self = this;
            var isHorizontal = options && options.horizontal;

            var opts = $.extend(true, {}, this.defaultOptions, {
                indexAxis: isHorizontal ? 'y' : 'x',
                scales: {
                    x: {
                        grid: {
                            display: isHorizontal,
                            color: this.colors.gridLine,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10, weight: '400' },
                            color: '#9ca3af',
                            padding: 8
                        },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: !isHorizontal,
                            color: this.colors.gridLine,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10, weight: '400' },
                            color: '#9ca3af',
                            padding: 8,
                            maxTicksLimit: 6
                        },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                },
                barThickness: isHorizontal ? 12 : 'flex',
                maxBarThickness: isHorizontal ? 16 : 40,
                borderRadius: 4,
                borderSkipped: false
            }, options);

            // Color bars based on value
            if (data.datasets && data.datasets[0]) {
                var values = data.datasets[0].data;
                data.datasets[0].backgroundColor = values.map(function(value) {
                    if (value >= 70) return self.colors.high;
                    if (value >= 40) return self.colors.medium;
                    return self.colors.low;
                });
                data.datasets[0].borderRadius = 4;
                data.datasets[0].borderSkipped = false;
            }

            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chart = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: opts
            });

            this.instances[canvasId] = chart;
            return chart;
        },

        /**
         * Create horizontal bar chart
         */
        createHorizontalBarChart: function(canvasId, data, options) {
            return this.createBarChart(canvasId, data, $.extend({}, options, { horizontal: true }));
        },

        /**
         * Create institutional donut chart
         */
        createDonutChart: function(canvasId, value, options) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            var ctx = canvas.getContext('2d');
            var opts = $.extend(true, {}, {
                color: this.colors.primary,
                backgroundColor: 'rgba(15, 23, 42, 0.06)',
                cutout: '78%'
            }, options);

            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [value, 100 - value],
                        backgroundColor: [opts.color, opts.backgroundColor],
                        borderWidth: 0,
                        cutout: opts.cutout
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    animation: { duration: 800, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });

            this.instances[canvasId] = chart;
            return chart;
        },

        /**
         * Create institutional pie/doughnut chart for distributions
         */
        createPieChart: function(canvasId, data, options) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            var ctx = canvas.getContext('2d');
            var self = this;

            var opts = $.extend(true, {}, this.defaultOptions, {
                cutout: options && options.donut ? '55%' : 0,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        align: 'center',
                        labels: {
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            font: { size: 11 }
                        }
                    }
                }
            }, options);

            // Default colors
            if (data.datasets && data.datasets[0] && !data.datasets[0].backgroundColor) {
                data.datasets[0].backgroundColor = this.colors.series;
            }

            if (data.datasets && data.datasets[0]) {
                data.datasets[0].borderWidth = 0;
                data.datasets[0].hoverOffset = 4;
            }

            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chart = new Chart(ctx, {
                type: options && options.donut ? 'doughnut' : 'pie',
                data: data,
                options: opts
            });

            this.instances[canvasId] = chart;
            return chart;
        },

        /**
         * Create institutional radar chart
         */
        createRadarChart: function(canvasId, data, options) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            var ctx = canvas.getContext('2d');
            var self = this;

            var opts = $.extend(true, {}, this.defaultOptions, {
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 25,
                            font: { size: 9 },
                            color: '#9ca3af',
                            backdropColor: 'transparent',
                            showLabelBackdrop: false
                        },
                        pointLabels: {
                            font: { size: 10, weight: '500' },
                            color: '#374151'
                        },
                        grid: {
                            color: this.colors.gridLine,
                            circular: true
                        },
                        angleLines: {
                            color: this.colors.gridLine
                        }
                    }
                }
            }, options);

            // Style datasets
            if (data.datasets) {
                data.datasets = data.datasets.map(function(dataset, index) {
                    var color = self.colors.series[index] || self.colors.primary;
                    return $.extend({}, dataset, {
                        backgroundColor: self.hexToRgba(color, 0.15),
                        borderColor: color,
                        borderWidth: 2,
                        pointBackgroundColor: color,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    });
                });
            }

            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
            }

            var chart = new Chart(ctx, {
                type: 'radar',
                data: data,
                options: opts
            });

            this.instances[canvasId] = chart;
            return chart;
        },

        /**
         * Update chart data
         */
        updateChart: function(canvasId, newData, animate) {
            var chart = this.instances[canvasId];
            if (!chart) return false;

            if (newData.labels) chart.data.labels = newData.labels;
            if (newData.datasets) chart.data.datasets = newData.datasets;

            chart.update(animate === false ? 'none' : undefined);
            return true;
        },

        /**
         * Destroy chart
         */
        destroyChart: function(canvasId) {
            if (this.instances[canvasId]) {
                this.instances[canvasId].destroy();
                delete this.instances[canvasId];
                return true;
            }
            return false;
        },

        /**
         * Destroy all charts
         */
        destroyAll: function() {
            var self = this;
            Object.keys(this.instances).forEach(function(canvasId) {
                self.destroyChart(canvasId);
            });
        },

        /**
         * Export chart
         */
        exportChart: function(canvasId, filename, format) {
            var chart = this.instances[canvasId];
            if (!chart) return false;

            format = format || 'png';
            filename = filename || 'chart-export';

            var link = document.createElement('a');
            link.download = filename + '.' + format;
            link.href = chart.canvas.toDataURL('image/' + format);
            link.click();

            return true;
        },

        /**
         * Hex to RGBA conversion
         */
        hexToRgba: function(hex, alpha) {
            var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (!result) return hex;

            return 'rgba(' +
                parseInt(result[1], 16) + ', ' +
                parseInt(result[2], 16) + ', ' +
                parseInt(result[3], 16) + ', ' +
                (alpha || 1) + ')';
        },

        /**
         * Generate color palette
         */
        generatePalette: function(count) {
            var palette = [];
            for (var i = 0; i < count; i++) {
                palette.push(this.colors.series[i % this.colors.series.length]);
            }
            return palette;
        },

        /**
         * Resize all charts
         */
        resizeAll: function() {
            Object.values(this.instances).forEach(function(chart) {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }
    };

    // Initialize
    $(document).ready(function() {
        SFFCCharts.init();
    });

    // Handle resize
    var resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            SFFCCharts.resizeAll();
        }, 250);
    });

})(window, jQuery);
