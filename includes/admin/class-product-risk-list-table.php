<?php

namespace RS\OrderBlocker\Admin;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WP_List_Table;

class Product_Risk_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Product',
            'plural'   => 'Products',
            'ajax'     => false,
        ]);


    }

    /**
     * Get column headers
     */
    public function get_columns() {
        return [
            'product'     => __('Product', 'rs-order-blocker'),
            'return_rate' => __('Return Rate', 'rs-order-blocker'),
            'returns'     => __('Returns', 'rs-order-blocker'),
            'total'       => __('Total Ordered (unit)', 'rs-order-blocker'),
            'action'      => __('Action', 'rs-order-blocker'),
        ];
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'total'       => ['total_orders', false],
            'returns'     => ['failed_orders', false],
            'return_rate' => ['fraud_rate', false],
        ];
    }

    /**
     * Render extra controls above the table
     */
    public function extra_tablenav($which) {
        if ($which === 'bottom') {
            $export_url = add_query_arg([
                'page'   => $_GET['page'] ?? '',
                'action' => 'export_product_risk',
                '_wpnonce' => wp_create_nonce('export_product_risk_nonce'),
            ]);

            echo '<div class="alignleft actions">';
            echo '<a href="' . esc_url($export_url) . '" class="button-primary">' . __('Export to CSV', 'rs-order-blocker') . '</a>';
            echo '</div>';
        }
    }

    /**
     * Prepare and fetch data
     */
    public function prepare_items() {
        global $wpdb;

        $is_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $wp_prefix     = $wpdb->prefix;
        $per_page      = 10;
        $current_page  = $this->get_pagenum();
        $offset        = ($current_page - 1) * $per_page;

        $search        = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $search_ids    = [];

        if (!empty($search)) {
            $search_ids = get_posts([
                's'              => $search,
                'post_type'      => 'product',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);

            if (empty($search_ids)) {
                $this->items = [];
                $this->set_pagination_args([
                    'total_items' => 0,
                    'per_page'    => $per_page,
                    'total_pages' => 0,
                ]);
                return;
            }

            $search_ids = implode(',', array_map('intval', $search_ids));
        }

        // Sorting
        $orderby = $_GET['orderby'] ?? 'failed_orders';
        $order   = strtoupper($_GET['order'] ?? 'DESC');
        $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $sort_map = [
            'return_rate' => 'fraud_rate',
            'returns'     => 'failed_orders',
            'total'       => 'total_orders',
        ];

        $sort_field = $sort_map[$orderby] ?? 'failed_orders';

        $rows = $this->get_product_risk_data(false);
        $total_items = 0;

        // Query data using HPOS or legacy method
        if ($is_hpos) {
            $where = "WHERE s.status LIKE 'wc-%'";
            if (!empty($search_ids)) {
                $where .= " AND pl.product_id IN ($search_ids)";
            }

            $rows = $wpdb->get_results("
                SELECT 
                    pl.product_id,
                    SUM(pl.product_qty) AS total_orders,
                    SUM(CASE WHEN s.status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN pl.product_qty ELSE 0 END) AS failed_orders,
                    ROUND(
                        IF(SUM(pl.product_qty) > 0,
                            (SUM(CASE WHEN s.status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN pl.product_qty ELSE 0 END) / SUM(pl.product_qty)) * 100,
                            0
                        ), 1
                    ) AS fraud_rate
                FROM {$wp_prefix}wc_order_product_lookup pl
                JOIN {$wp_prefix}wc_order_stats s ON pl.order_id = s.order_id
                $where
                GROUP BY pl.product_id
                ORDER BY $sort_field $order
                LIMIT $per_page OFFSET $offset
            ", ARRAY_A);

            $total_items = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT pl.product_id)
                FROM {$wp_prefix}wc_order_product_lookup pl
                JOIN {$wp_prefix}wc_order_stats s ON pl.order_id = s.order_id
                $where
            ");
        } else {
            $where = "
                WHERE meta.meta_key = '_product_id'
                AND p.post_type = 'shop_order'
                AND p.post_status LIKE 'wc-%'
            ";

            if (!empty($search_ids)) {
                $where .= " AND meta.meta_value IN ($search_ids)";
            }

            $rows = $wpdb->get_results("
                SELECT 
                    meta.meta_value AS product_id,
                    COUNT(*) AS total_orders,
                    SUM(CASE WHEN p.post_status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) AS failed_orders,
                    ROUND(
                        IF(COUNT(*) > 0,
                            (SUM(CASE WHEN p.post_status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                            0
                        ), 1
                    ) AS fraud_rate
                FROM {$wp_prefix}woocommerce_order_items oi
                JOIN {$wp_prefix}woocommerce_order_itemmeta meta ON oi.order_item_id = meta.order_item_id
                JOIN {$wp_prefix}posts p ON oi.order_id = p.ID
                $where
                GROUP BY meta.meta_value
                ORDER BY $sort_field $order
                LIMIT $per_page OFFSET $offset
            ", ARRAY_A);

            $total_items = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT meta.meta_value)
                FROM {$wp_prefix}woocommerce_order_items oi
                JOIN {$wp_prefix}woocommerce_order_itemmeta meta ON oi.order_item_id = meta.order_item_id
                JOIN {$wp_prefix}posts p ON oi.order_id = p.ID
                $where
            ");
        }

        // Format rows
        $this->items = [];

        foreach ($rows as $row) {
            $product_id = (int) $row['product_id'];
            $product    = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $this->items[] = [
                'product_id'    => $product_id,
                'product_name'  => $product->get_name(),
                'product_image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'total_orders'  => (int) $row['total_orders'],
                'failed_orders' => (int) $row['failed_orders'],
                'fraud_rate'    => $row['fraud_rate'],
                'edit_url'      => get_edit_post_link($product_id),
            ];
        }

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Render product column with image and ID
     */
    public function column_product($item) {
        $img = $item['product_image']
            ? "<img src='" . esc_url($item['product_image']) . "' width='32' height='32' style='border-radius:4px;margin-right:8px;vertical-align:middle;' />"
            : '';

        return $img . '<strong>' . esc_html($item['product_name']) . '</strong><br><small style="color:#666">ID: ' . esc_html($item['product_id']) . '</small>';
    }

    /**
     * Render return rate column with color coding
     */
    public function column_return_rate($item) {
        $rate = (float) $item['fraud_rate'];
        $color = match (true) {
            $rate >= 50 => 'red',
            $rate >= 20 => 'orange',
            default     => 'green',
        };

        return "<strong style='color:{$color}'>" . esc_html($rate) . "%</strong>";
    }

    /**
     * Render failed orders column
     */
    public function column_returns($item) {
        return esc_html((int) $item['failed_orders']);
    }

    /**
     * Render total orders column
     */
    public function column_total($item) {
        return esc_html((int) $item['total_orders']);
    }

    /**
     * Render view action column
     */
    public function column_action($item) {
        return '<a href="' . esc_url($item['edit_url']) . '" class="button" target="_blank">' . __('View', 'rs-order-blocker') . '</a>';
    }

    /**
     * Fallback for undefined columns
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name])
            ? esc_html($item[$column_name])
            : '<em>' . __('N/A', 'rs-order-blocker') . '</em>';
    }

    /**
     * Export the data to CSV if requested
     */
    public static function handle_export() {
        if (
            isset($_GET['action'], $_GET['_wpnonce']) &&
            $_GET['action'] === 'export_product_risk' &&
            wp_verify_nonce($_GET['_wpnonce'], 'export_product_risk_nonce')
        ) {
            $instance = new self();
            $rows = $instance->get_product_risk_data(true);

            if (empty($rows)) {
                wp_die(__('No data available for export.', 'rs-order-blocker'));
            }

            // Send CSV headers
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=product-risk-export.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            fputcsv($output, ['Product Name', 'Product ID', 'Return Rate (%)', 'Returns', 'Total Ordered']);

            foreach ($rows as $row) {
                $product_id = (int) $row['product_id'];
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                fputcsv($output, [
                    $product->get_name(),
                    $product_id,
                    $row['fraud_rate'],
                    $row['failed_orders'],
                    $row['total_orders'],
                ]);
            }

            fclose($output);
            exit;
        }
    }



    /**
     * Export: Retrieve all product risk data For export
     *
     * @param bool $all Whether to retrieve all rows (no pagination).
     * @return array
     */
    private function get_product_risk_data($all = false): array {
        global $wpdb;

        $is_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $wp_prefix   = $wpdb->prefix;
        $per_page    = 10;
        $offset      = ($this->get_pagenum() - 1) * $per_page;
        $limit_clause = $all ? '' : "LIMIT $per_page OFFSET $offset";

        $search     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $search_ids = [];

        if (!empty($search)) {
            $search_ids = get_posts([
                's'              => $search,
                'post_type'      => 'product',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);

            if (empty($search_ids)) {
                return [];
            }

            $search_ids = implode(',', array_map('intval', $search_ids));
        }

        $orderby = $_GET['orderby'] ?? 'failed_orders';
        $order   = strtoupper($_GET['order'] ?? 'DESC');
        $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $sort_map = [
            'return_rate' => 'fraud_rate',
            'returns'     => 'failed_orders',
            'total'       => 'total_orders',
        ];

        $sort_field = $sort_map[$orderby] ?? 'failed_orders';

        $where = $is_hpos
            ? "WHERE s.status LIKE 'wc-%'" . (!empty($search_ids) ? " AND pl.product_id IN ($search_ids)" : '')
            : "WHERE meta.meta_key = '_product_id' AND p.post_type = 'shop_order' AND p.post_status LIKE 'wc-%'" . (!empty($search_ids) ? " AND meta.meta_value IN ($search_ids)" : '');

        if ($is_hpos) {
            return $wpdb->get_results("
            SELECT 
                pl.product_id,
                SUM(pl.product_qty) AS total_orders,
                SUM(CASE WHEN s.status IN ('wc-cancelled','wc-failed','wc-refunded') THEN pl.product_qty ELSE 0 END) AS failed_orders,
                ROUND(
                    IF(SUM(pl.product_qty) > 0,
                        (SUM(CASE WHEN s.status IN ('wc-cancelled','wc-failed','wc-refunded') THEN pl.product_qty ELSE 0 END) / SUM(pl.product_qty)) * 100,
                        0
                    ), 1
                ) AS fraud_rate
            FROM {$wp_prefix}wc_order_product_lookup pl
            JOIN {$wp_prefix}wc_order_stats s ON pl.order_id = s.order_id
            $where
            GROUP BY pl.product_id
            ORDER BY $sort_field $order
            $limit_clause
        ", ARRAY_A);
        }

        return $wpdb->get_results("
        SELECT 
            meta.meta_value AS product_id,
            COUNT(*) AS total_orders,
            SUM(CASE WHEN p.post_status IN ('wc-cancelled','wc-failed','wc-refunded') THEN 1 ELSE 0 END) AS failed_orders,
            ROUND(
                IF(COUNT(*) > 0,
                    (SUM(CASE WHEN p.post_status IN ('wc-cancelled','wc-failed','wc-refunded') THEN 1 ELSE 0 END) / COUNT(*)) * 100,
                    0
                ), 1
            ) AS fraud_rate
        FROM {$wp_prefix}woocommerce_order_items oi
        JOIN {$wp_prefix}woocommerce_order_itemmeta meta ON oi.order_item_id = meta.order_item_id
        JOIN {$wp_prefix}posts p ON oi.order_id = p.ID
        $where
        GROUP BY meta.meta_value
        ORDER BY $sort_field $order
        $limit_clause
    ", ARRAY_A);
    }
}
