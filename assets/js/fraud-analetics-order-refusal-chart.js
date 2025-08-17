; (function ($) {

    $(document).ready(function () {
        // Toggle total orders visibility
        $('#toggle-total-orders').on('change', function () {
            const visible = $(this).is(':checked');
            chart.data.datasets[1].hidden = !visible;
            chart.update();
        });

        let chart;
        let filteredChartData = {
            labels: rsOrderRefusalChartData.labels.slice(),
            refusedOrders: rsOrderRefusalChartData.refusedOrders.slice(),
            totalOrders: rsOrderRefusalChartData.totalOrders.slice()
        };

        function renderChart(labels, refusedData, totalData) {
            const ctx = document.getElementById('rs-order-refusal-chart').getContext('2d');
            if (chart) chart.destroy();

            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Refused Orders',
                            data: refusedData,
                            backgroundColor: '#e74c3c'
                        },
                        {
                            label: 'Total Orders',
                            data: totalData,
                            backgroundColor: '#3498db',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return tooltipItem.dataset.label + ': ' + tooltipItem.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Orders'
                            }
                        }
                    }
                }

            });




        }

        // Initial chart load
        renderChart(
            filteredChartData.labels,
            filteredChartData.refusedOrders,
            filteredChartData.totalOrders
        );

        // Chart filter by month
        $('#rs-filter-month-btn').on('click', function () {
            const from = $('#rs-month-from').val();
            const to = $('#rs-month-to').val();

            if (!from || !to) {
                alert('Please select both From and To months.');
                return;
            }

            $.post(rsChartAjax.ajax_url, {
                action: 'rs_get_refusal_chart_data',
                from,
                to,
                _ajax_nonce: rsChartAjax.nonce
            }, function (res) {
                if (res.success) {
                    const data = res.data;

                    // Update filtered data reference
                    filteredChartData.labels = data.labels;
                    filteredChartData.refusedOrders = data.refusedOrders;
                    filteredChartData.totalOrders = data.totalOrders;

                    renderChart(data.labels, data.refusedOrders, data.totalOrders);
                } else {
                    alert('Failed to load data.');
                }
            });
        });


        // CSV Export - uses filteredChartData instead of the default
        $('#export-refusal-csv').on('click', function () {
            let csv = 'Month,Refused Orders,Total Orders\n';

            filteredChartData.labels.forEach((label, i) => {
                const cleanLabel = label.replace(/\u00A0/g, ' '); // remove non-breaking space if any
                csv += `"${cleanLabel}",${filteredChartData.refusedOrders[i]},${filteredChartData.totalOrders[i]}\n`;
            });

            const bom = '\uFEFF'; // UTF-8 BOM
            const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = $('<a></a>').attr({
                href: url,
                download: 'order-refusals.csv'
            }).appendTo('body');
            link[0].click();
            link.remove();
        });

    });


})(jQuery);