<?php

namespace RS\OrderBlocker\Admin;

if (!defined('ABSPATH')) exit;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Class FraudAnalyticsPage
 *
 * Handles the rendering of the fraud analytics dashboard page in the admin area.
 * Provides tabs for different fraud analytics sections like heatmap, order refusal,
 * product risk, and customer risk.
 */

class FraudAnalyticsPage {




    /**
     * Renders the main fraud analytics dashboard page. with tabs for different sections.
     */

    public static function render() {
        $tabs = [
            'heatmap'      => __('🗺️ Heatmap', 'wcorder-blocker'),
            'orders'       => __('🔁 Order Refusal', 'wcorder-blocker'),
            'product_risk' => __('📦 Product Risk', 'wcorder-blocker'),
            'customer'     => __('🕵️‍♂️ Customer Risk', 'wcorder-blocker'),
        ];
        $current = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_key($_GET['tab']) : 'heatmap';

        echo '<div class="wrap">';
        echo '<h1>🕵️‍♂️ ' . esc_html__('Fraud Analytics Dashboard', 'wcorder-blocker') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active = $current === $key ? ' nav-tab-active' : '';
            $url = esc_url(add_query_arg(['page' => 'rs-fraud-analytics', 'tab' => $key], admin_url('admin.php')));
            printf('<a href="%s" class="nav-tab%s">%s</a>', $url, $active, esc_html($label));
        }
        echo '</h2>';

        echo '<div id="tab-content">';
        switch ($current) {
            case 'orders':
                self::render_order_refusal_chart();
                break;
            case 'product_risk':
                self::render_product_risk_insights();
                break;
            case 'customer':
                self::render_customer_risk_profiles();
                break;
            case 'heatmap':
            default:
                self::render_heatmap_section();
        }
        echo '</div>';
        echo '</div>';
    }






    // 1. Overview metrics block
    private static function render_overview_cards() {
        // Fake example for now – real data in later steps
?>
        <!-- <div class="rs-metrics-row">
            <div class="rs-metric-card">
                <h4>Total Blocked Orders</h4>
                <p>127</p>
            </div>
            <div class="rs-metric-card">
                <h4>Fraud Score 7+</h4>
                <p>19</p>
            </div>
            <div class="rs-metric-card">
                <h4>High COD Refusal Zones</h4>
                <p>4</p>
            </div>
            <div class="rs-metric-card">
                <h4>Flagged Customers</h4>
                <p>23</p>
            </div>
        </div> -->
    <?php
    }


    /**
     *  ================== 2. Heatmap UI block ===================
     */
    public static function render_heatmap_section() {
        global $wpdb;

        $is_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $district_stats = [];
        $total_orders = 0;
        $total_failed = 0;
        $all_customers = [];
        $all_failed_products = [];

        $wp_ = $wpdb->prefix;
        $from = !empty($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = !empty($_GET['to']) ? sanitize_text_field($_GET['to']) : '';

        $date_clause = '';
        if ($from && $to) {
            $from_sql = esc_sql($from . ' 00:00:00');
            $to_sql   = esc_sql($to . ' 23:59:59');
            $column = $is_hpos ? 's.date_created_gmt' : 'p.post_date_gmt';
            $date_clause = "AND {$column} BETWEEN '{$from_sql}' AND '{$to_sql}'";
        }


        // BD State code to readable district name
        $bd_states = [
            'BD-03' => 'Bagerhat',
            'BD-18' => 'Gazipur',
            'BD-13' => 'Dhaka',
            'BD-34' => 'Chattogram',
            'BD-10' => 'Comilla',
            'BD-20' => 'Kishoreganj',
            'BD-32' => 'Sylhet',
            'BD-01' => 'Bandarban',
            'BD-02' => 'Barguna',
            'BD-04' => 'Barisal',
            'BD-05' => 'Bhola',
            'BD-06' => 'Bogra',
            'BD-07' => 'Brahmanbaria',
            'BD-08' => 'Chandpur',
            'BD-09' => 'Chapai Nawabganj',
            'BD-11' => 'Cox’s Bazar',
            'BD-12' => 'Chuadanga',
            'BD-14' => 'Dinajpur',
            'BD-15' => 'Faridpur',
            'BD-16' => 'Feni',
            'BD-17' => 'Gaibandha',
            'BD-19' => 'Gopalganj',
            'BD-21' => 'Jamalpur',
            'BD-22' => 'Jashore',
            'BD-23' => 'Jhalokati',
            'BD-24' => 'Jhenaidah',
            'BD-25' => 'Joypurhat',
            'BD-26' => 'Khagrachari',
            'BD-27' => 'Khulna',
            'BD-28' => 'Kurigram',
            'BD-29' => 'Kushtia',
            'BD-30' => 'Lakshmipur',
            'BD-31' => 'Lalmonirhat',
            'BD-33' => 'Madaripur',
            'BD-35' => 'Magura',
            'BD-36' => 'Manikganj',
            'BD-37' => 'Meherpur',
            'BD-38' => 'Moulvibazar',
            'BD-39' => 'Munshiganj',
            'BD-40' => 'Mymensingh',
            'BD-41' => 'Naogaon',
            'BD-42' => 'Narail',
            'BD-43' => 'Narayanganj',
            'BD-44' => 'Narsingdi',
            'BD-45' => 'Natore',
            'BD-46' => 'Netrokona',
            'BD-47' => 'Nilphamari',
            'BD-48' => 'Noakhali',
            'BD-49' => 'Pabna',
            'BD-50' => 'Panchagarh',
            'BD-51' => 'Patuakhali',
            'BD-52' => 'Pirojpur',
            'BD-53' => 'Rajbari',
            'BD-54' => 'Rajshahi',
            'BD-55' => 'Rangamati',
            'BD-56' => 'Rangpur',
            'BD-57' => 'Satkhira',
            'BD-58' => 'Shariatpur',
            'BD-59' => 'Sherpur',
            'BD-60' => 'Sirajganj',
            'BD-61' => 'Sunamganj',
            'BD-62' => 'Tangail',
            'BD-63' => 'Thakurgaon'
        ];

        if ($is_hpos) {
            $orders = $wpdb->get_results(" SELECT o.id, a.state, s.status, s.total_sales AS total, a.email, a.phone, s.date_created_gmt
            FROM {$wp_}wc_orders o
            JOIN {$wp_}wc_order_stats s ON o.id = s.order_id
            JOIN {$wp_}wc_order_addresses a ON o.id = a.order_id
            WHERE type = 'shop_order' AND s.status IN ('wc-completed', 'wc-processing', 'wc-cancelled', 'wc-refunded', 'wc-failed')
            AND a.address_type = 'billing'
            {$date_clause}
        ", ARRAY_A);
        } else {
            $orders = $wpdb->get_results(" SELECT p.ID, pm1.meta_value AS state, pm2.meta_value AS total,
                   pm3.meta_value AS email, pm4.meta_value AS phone, p.post_status, p.post_date_gmt
            FROM {$wp_}posts p
            LEFT JOIN {$wp_}postmeta pm1 ON pm1.post_id = p.ID AND pm1.meta_key = '_billing_state'
            LEFT JOIN {$wp_}postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_order_total'
            LEFT JOIN {$wp_}postmeta pm3 ON pm3.post_id = p.ID AND pm3.meta_key = '_billing_email'
            LEFT JOIN {$wp_}postmeta pm4 ON pm4.post_id = p.ID AND pm4.meta_key = '_billing_phone'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-cancelled', 'wc-refunded', 'wc-failed')
            {$date_clause}
        ", ARRAY_A);
        }

        foreach ($orders as $order) {
            $state_code = trim($order['state']);
            $district = $bd_states[$state_code] ?? 'Unknown';

            $email = sanitize_email($order['email']);
            $phone = sanitize_text_field($order['phone']);
            $status = $is_hpos ? $order['status'] : $order['post_status'];
            $total = floatval($order['total']);
            $id = intval($order['id'] ?? $order['ID']);

            if (!isset($district_stats[$district])) {
                $district_stats[$district] = [
                    'total_orders' => 0,
                    'failed_orders' => 0,
                    'emails' => [],
                    'phones' => [],
                    'product_ids' => [],
                    'failed_product_ids' => [],
                ];
            }

            $district_stats[$district]['total_orders']++;
            $total_orders++;

            if (in_array($status, ['wc-cancelled', 'wc-failed', 'wc-refunded'])) {
                $district_stats[$district]['failed_orders']++;
                $total_failed++;
            }

            if ($email) $district_stats[$district]['emails'][] = $email;
            if ($phone) $district_stats[$district]['phones'][] = $phone;

            if (!$is_hpos && function_exists('wc_get_order')) {
                $items = wc_get_order($id)->get_items();
                foreach ($items as $item) {
                    $pid = $item->get_product_id();
                    $district_stats[$district]['product_ids'][] = $pid;
                    if (in_array($status, ['wc-cancelled', 'wc-failed', 'wc-refunded'])) {
                        $district_stats[$district]['failed_product_ids'][] = $pid;
                        $all_failed_products[] = $pid;
                    }
                }
            }

            $all_customers[] = $email . $phone;
        }

        // Per-district final stats
        foreach ($district_stats as $district => &$data) {
            $data['return_rate'] = $data['total_orders'] > 0
                ? round($data['failed_orders'] / $data['total_orders'] * 100, 1) : 0;
            $data['risk'] = $data['return_rate'] >= 30 ? 'high' : ($data['return_rate'] >= 10 ? 'medium' : 'low');

            $abuse_counts = array_count_values($data['failed_product_ids']);
            arsort($abuse_counts);
            $data['most_abused_products'] = array_slice(array_keys($abuse_counts), 0, 3);
            $data['total_customers'] = count(array_unique($data['emails'] + $data['phones']));

            unset($data['emails'], $data['phones'], $data['product_ids'], $data['failed_product_ids']);
        }

        // Heatmap color
        $risk_map = [];
        foreach ($district_stats as $district => $data) {
            $risk_map[$district] = $data['risk'];
        }

        // Global stat summary
        $total_return_rate = $total_orders > 0 ? round($total_failed / $total_orders * 100, 1) : 0;
        $global_abuse = array_count_values($all_failed_products);
        arsort($global_abuse);
        $most_abused_global = array_slice(array_keys($global_abuse), 0, 5);
        $total_customers = count(array_unique($all_customers));

        echo '<script>window.rsDistrictStats = ' . wp_json_encode($district_stats) . ';</script>';
    ?>

        <div style="display: flex; gap: 30px;" class="Heatmap-section">
            <div style="width: 100%;">
                <!-- Map filter  -->
                <div class="map-header">
                    <h2>🗺️ Regional Fraud Heatmap (Bangladesh)</h2>
                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                        <label for="from">From</label>
                        <input type="date" id="from" name="from" value="<?php echo esc_attr($from); ?>">
                        <label for="to">To</label>
                        <input type="date" id="to" name="to" value="<?php echo esc_attr($to); ?>">
                        <input type="submit" class="button" value="Filter">
                    </form>
                </div>

                <!-- Map of Bangladesh -->
                <div id="rs-heatmap-bd" style="height: 450px; border: 1px solid #ccc;"></div>
                <!-- Map bottom statistic  -->
                <div class="total-stats">
                    <p>📦 Total Orders: <span><?php echo esc_html($total_orders); ?> </span> </p>
                    <p>❌ Failed Orders: <span> <?php echo esc_html($total_failed); ?> </span> </p>
                    <p>📉 Return Rate: <span> <?php echo esc_html($total_return_rate); ?>% </span> </p>
                    <p>🚨 Most Abused Products: <span> <?php echo implode(', ', $most_abused_global); ?> </span> </p>
                    <p>👥 Total Customers: <span> <?php echo esc_html($total_customers); ?> </span> </p>
                </div>
            </div>

            <!-- <div class="fraud-risk-summery" style="width: 30%;">
                <h3>📊 Risk Summary</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>District</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($risk_map as $district => $risk): ?>
                            <tr>
                                <td><?php echo esc_html($district); ?></td>
                                <td style="color:<?php echo $risk === 'high' ? 'red' : ($risk === 'medium' ? 'orange' : 'green'); ?>">
                                    <?php echo ucfirst($risk); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div> -->

        </div>

        <div id="rs-info-cards-container" style="position: relative; margin-top: 20px;">
            <div id="rs-info-cards"></div>
        </div>
    <?php
        wp_enqueue_script('rs-fraud-analetics-js');
        wp_localize_script('rs-fraud-analetics-js', 'rsData', [
            'riskMap'       => $risk_map,
            'geoJsonUrl'    => plugin_dir_url(__FILE__) . '../../assets/js/bd-districts.geojson',
            'districtStats' => $district_stats,
        ]);
    }




    /**
     * === 3. Order‑refusal chart (By months, all payment methods) ====
     */

    public static function render_order_refusal_chart() {
        // default range = last 12 months
        $default_from = gmdate('Y-m', strtotime('-11 months'));
        $default_to   = gmdate('Y-m');

        $chart = self::get_refusal_chart_data($default_from, $default_to);

        /*  HTML output ↓  */
    ?>
        <div class="order_graph_head">

            <div>
                <h2>📉 Order Refusals by Month</h2>

            </div>

            <div class="filter">
                <label>From:
                    <input type="month" id="rs-month-from" value="<?php echo esc_attr($default_from); ?>">
                </label>
                <label>To:
                    <input type="month" id="rs-month-to" value="<?php echo esc_attr($default_to); ?>">
                </label>
                <button id="rs-filter-month-btn" class="button">🔍 Filter</button>
            </div>
        </div>

        <div class="fraud_graph">
            <canvas id="rs-order-refusal-chart" height="110"></canvas>

            <div class="export-container">
                <label style="margin-right: 10px;"><input type="checkbox" id="toggle-total-orders" checked> Show Total Orders</label>
                <button id="export-refusal-csv" class="button">⬇️ Export CSV</button>
            </div>
        </div>


        <script>
            window.rsOrderRefusalChartData = <?php echo wp_json_encode($chart); ?>;
        </script>
<?php

        /* enqueue scripts */
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            RS_ORDER_BLOCKER_VERSION,
            true
        );

        wp_enqueue_script(
            'rs-order-refusal-render',
            plugin_dir_url(__FILE__) . '../../assets/js/fraud-analetics-order-refusal-chart.js',
            ['jquery', 'chart-js'],
            RS_ORDER_BLOCKER_VERSION,
            true
        );
        wp_localize_script('rs-order-refusal-render', 'rsChartAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rs_chart_data'),
        ]);
    }


    /**
     * Helper that returns month‑wise totals + refusals for a given range.
     *
     * @param string $from  YYYY‑MM  (e.g. '2024-01')
     * @param string $to    YYYY‑MM  (e.g. '2025-07')
     * @return array [
     *      'labels'        => [ 'Jan 2024', ... ],
     *      'refusedOrders' => [12, 9, …],
     *      'totalOrders'   => [80, 75, …]
     * ]
     */
    public static function get_refusal_chart_data($from, $to) {
        global $wpdb;

        $is_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $wp_ = $wpdb->prefix;

        // Sanitise + convert to date range
        $from  = preg_replace('/[^0-9\-]/', '', $from ?: gmdate('Y-m', strtotime('-11 months')));
        $to    = preg_replace('/[^0-9\-]/', '', $to   ?: gmdate('Y-m'));

        $from_date = $from . '-01';
        $to_date   = date('Y-m-t', strtotime($to . '-01')); // last day of that month

        /* 1. build empty monthly arrays (oldest ➜ newest) */
        $labels   = [];
        $refuse   = [];
        $totals   = [];

        $startTS  = strtotime($from_date);
        $endTS    = strtotime($to_date);
        $months   = [];

        while ($startTS <= $endTS) {
            $ym = gmdate('Y-m', $startTS);
            $months[]  = $ym;
            $labels[]  = date_i18n('M Y', $startTS);
            $refuse[$ym] = 0;
            $totals[$ym] = 0;
            $startTS = strtotime('+1 month', $startTS);
        }

        /* 2. fetch total orders */
        if ($is_hpos) {
            $total_rows = $wpdb->get_results($wpdb->prepare(" SELECT DATE_FORMAT(date_created_gmt,'%Y-%m') month, COUNT(*) total_cnt
            FROM {$wp_}wc_orders
            WHERE type = 'shop_order' AND status LIKE 'wc-%%'
              AND date_created_gmt BETWEEN %s AND %s
            GROUP BY month
        ", $from_date, $to_date), OBJECT_K);
        } else {
            $total_rows = $wpdb->get_results($wpdb->prepare(" SELECT DATE_FORMAT(post_date_gmt,'%Y-%m') month, COUNT(*) total_cnt
            FROM {$wp_}posts
            WHERE post_type = 'shop_order'
              AND post_status LIKE 'wc-%%'
              AND post_date_gmt BETWEEN %s AND %s
            GROUP BY month
        ", $from_date, $to_date), OBJECT_K);
        }
        foreach ($total_rows as $m => $row) {
            $totals[$m] = (int) $row->total_cnt;
        }

        /* 3. fetch refused orders */
        $status_in = "'wc-cancelled','wc-failed','wc-refunded'";
        if ($is_hpos) {
            $refusal_rows = $wpdb->get_results($wpdb->prepare(" SELECT DATE_FORMAT(date_created_gmt,'%Y-%m') month, COUNT(*) refuse_cnt
            FROM {$wp_}wc_orders
            WHERE type = 'shop_order' AND status IN ($status_in)
              AND date_created_gmt BETWEEN %s AND %s
            GROUP BY month
        ", $from_date, $to_date), OBJECT_K);
        } else {
            $refusal_rows = $wpdb->get_results($wpdb->prepare("  SELECT DATE_FORMAT(post_date_gmt,'%Y-%m') month, COUNT(*) refuse_cnt
            FROM {$wp_}posts
            WHERE post_type = 'shop_order'
              AND post_status IN ($status_in)
              AND post_date_gmt BETWEEN %s AND %s
            GROUP BY month
        ", $from_date, $to_date), OBJECT_K);
        }
        foreach ($refusal_rows as $m => $row) {
            $refuse[$m] = (int) $row->refuse_cnt;
        }

        /* 4. flatten to arrays in chronological order */
        $refusal_vals = [];
        $total_vals   = [];
        foreach ($months as $ym) {
            $refusal_vals[] = $refuse[$ym];
            $total_vals[]   = $totals[$ym];
        }

        return [
            'labels'        => $labels,
            'refusedOrders' => $refusal_vals,
            'totalOrders'   => $total_vals,
        ];
    }



    /**
     *  ================== 4. Product-level risk insights ====================
     */

    private static function render_product_risk_insights() {
        require_once plugin_dir_path(__FILE__) . 'class-product-risk-list-table.php';

        $table = new Product_Risk_List_Table();
        $table->prepare_items();

        echo '<div class="product_risk_wrap">';
        echo '<h2>📦 Product-Level Risk Insights</h2>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        echo '<input type="hidden" name="tab" value="product_risk">';
        $table->search_box('Search Products', 'product_risk'); // ← This must be echoed, not interpolated
        echo '</form>';
        $table->display();

        echo '</div>';
    }






    // 5. Customer risk profiling
    // private static function render_customer_risk_profiles() {
    //     echo '<h2 >👤 Customer Risk Profiles</h2>';
    //     echo '<p>Recent customers flagged for risky behavior:</p>';
    //     echo '<table class="widefat striped">';
    //     echo '<thead><tr><th>Name</th><th>Email</th><th>Return Rate</th><th>Returned/Cancelled</th><th>Orders</th><th>Blocked</th><th>Action</th></tr></thead>';
    //     echo '<tbody>';
    //     echo '<tr>
    //     <td><image></image> <p>Rafiq Hasan</p> <span>Customer ID: 12345</span></td>
    //     <td>rafiq@example.com</td>
    //     <td>10%</td>
    //     <td>10</td>
    //     <td>100</td>
    //     <td>Yes</td>
    //     <td>Unblock</td>
    //     </tr>';
    //     echo '<tr>
    //     <td><image></image> <p>Rafiq Hasan</p> <span>Customer ID: 12345</span></td>
    //     <td>rafiq@example.com</td>
    //     <td>5%</td>
    //     <td>5</td>
    //     <td>100</td>
    //     <td>No</td>
    //     <td>Block</td>
    //     </tr>';
    //     echo '</tbody></table>';
    // }

    private static function render_customer_risk_profiles() {
        require_once plugin_dir_path(__FILE__) . 'class-customer-risk-list-table.php';
        // Render the customer risk profiles section with a table
        echo '<div class="customer-risk-wrap">';
        echo '<h2>🕵️‍♂️ Customer Risk Profiles</h2>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="rs-fraud-analytics">';
        $table = new Customer_Risk_List_Table();
        $table->prepare_items();
        $table->search_box(__('Search Customers'), 'customer_search');
        $table->display();
        echo '</form>';
        echo '</div>';
    }
}
