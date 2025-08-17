; (function ($) {
    $(document).ready(function () {
        let selectedDistricts = {};
        let map = L.map('rs-heatmap-bd').setView([23.685, 90.3563], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let riskColors = {
            high: '#e74c3c',
            medium: '#f39c12',
            low: '#2ecc71'
        };

        let selectedStyle = {
            weight: 2,
            color: '#2b2b2bff',
            fillOpacity: 0.95
        };

        let riskMap = rsData.riskMap;

        $.getJSON(rsData.geoJsonUrl, function (data) {
            let geojson = L.geoJson(data, {
                style: function (feature) {
                    let district = feature.properties.ADM2_EN || feature.properties.name;
                    let risk = riskMap[district] || 'low';
                    return {
                        fillColor: riskColors[risk],
                        weight: 1,
                        color: '#ddd',
                        fillOpacity: 0.7
                    };
                },
                onEachFeature: function (feature, layer) {
                    const name = feature.properties.ADM2_EN || feature.properties.name;
                    const stats = rsData.districtStats[name] || {};
                    const risk = stats.risk || 'low';
                    const color = risk === 'high' ? 'red' : (risk === 'medium' ? 'orange' : 'green');

                    const tooltipHtml = `
                        <div class="district-hover">
                            <strong>${name}</strong> - Risk: <b style="color:${color}">${risk.toUpperCase()}</b><br>
                            ———————————————<br>
                            📦 Total Orders: ${stats.total_orders || 0}<br>
                            ❌ Failed Orders: ${stats.failed_orders || 0}<br>
                            📉 Return Rate: ${(stats.return_rate || 0).toFixed(1)}%<br>
                            ———————————————<br>
                            🚨 Most Abused Products: ${(stats.most_abused_products || []).join(', ') || 'None'}<br>
                            👥 Total Customers: ${stats.total_customers || 0}
                        </div>`;

                    layer.bindTooltip(tooltipHtml, {
                        sticky: true,
                        direction: 'top',
                        offset: [5, -25],
                        className: 'rs-tooltip'
                    });

                    layer.on('mouseover', function () {
                        if (!selectedDistricts[name]) {
                            layer.setStyle({
                                weight: 2,
                                color: '#444',
                                fillOpacity: 0.95
                            });
                        }
                    });

                    layer.on('mouseout', function () {
                        const id = name.replace(/\s+/g, '-');
                        if (!selectedDistricts[id]) {
                            geojson.resetStyle(layer);
                        }
                    });

                    layer.on('click', function () {
                        const id = name.replace(/\s+/g, '-');
                        if (!selectedDistricts[id]) {
                            layer.setStyle(selectedStyle);
                            selectedDistricts[id] = layer;

                            const cardId = 'rs-card-' + id;
                            const cardHtml = `
                                <div id="${cardId}" class="rs-info-card">
                                    <div class="rs-info-card-header">
                                        <strong>${name}</strong> 
                                        <p><strong>Risk:</strong> <span style="color:${color}">${risk.toUpperCase()}</span></p>
                                        <button class="rs-close-card" data-id="${cardId}" data-district="${id}">×</button>
                                    </div>
                                    <div class="rs-info-card-body">
                                        <p>📦 Total Orders: ${stats.total_orders || 0}</p>
                                        <p>❌ Failed Orders: ${stats.failed_orders || 0}</p>
                                        <p>📉 Return Rate: ${(stats.return_rate || 0).toFixed(1)}%</p>
                                        <p>🚨 Most Abused Products: ${(stats.most_abused_products || []).join(', ') || 'None'}</p>
                                        <p>👥 Total Customers: ${stats.total_customers || 0}</p>
                                    </div>
                                </div>`;
                            $('#rs-info-cards').append(cardHtml);
                        }
                    });

                    layer.bindPopup('', { autoClose: true }).unbindPopup();
                }
            }).addTo(map);

            $(document).on('click', '.rs-close-card', function () {
                const id = $(this).data('id');
                const districtKey = $(this).data('district');
                if (selectedDistricts[districtKey]) {
                    geojson.resetStyle(selectedDistricts[districtKey]);
                    delete selectedDistricts[districtKey];
                }
                $('#' + id).fadeOut(200, function () {
                    $(this).remove();
                });
            });
        });



//Tab load without page refresh
        // jQuery(document).ready(function ($) {
        //     $('.nav-tab-wrapper a').on('click', function (e) {
        //         e.preventDefault();
        //         var tab = $(this).attr('href').split('tab=')[1];
        //         history.replaceState(null, null, $(this).attr('href'));
        //         $('.nav-tab').removeClass('nav-tab-active');
        //         $(this).addClass('nav-tab-active');
        //         $('#tab-content').hide().load(window.location.href + ' #tab-content > *', function () {
        //             $(this).fadeIn();
        //         });
        //     });
        // });


    });
})(jQuery);
