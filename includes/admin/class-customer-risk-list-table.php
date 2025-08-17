<?php

namespace RS\OrderBlocker\Admin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Customer_Risk_List_Table
 *
 * Displays customer risk insights using WP_List_Table.
 */
class Customer_Risk_List_Table extends \WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Customer',
            'plural'   => 'Customers',
            'ajax'     => false,
        ]);
    }

    /**
     * Define table columns
     */
    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" />',
            'name'            => __('Name', 'rs-order-blocker'),
            'email'           => __('Email', 'rs-order-blocker'),
            'return_rate'     => __('Return Rate', 'rs-order-blocker'),
            'returned_count'  => __('Returned/Cancelled', 'rs-order-blocker'),
            'orders'          => __('Total Orders', 'rs-order-blocker'),
            'blocked'         => __('Blocked', 'rs-order-blocker'),
            'action'          => __('Action', 'rs-order-blocker'),
        ];
    }

    /**
     * Define sortable columns
     */
    protected function get_sortable_columns() {
        return [
            'name'            => ['name', true],
            'return_rate'     => ['return_rate', false],
            'returned_count'  => ['returned_count', false],
            'orders'          => ['orders', false],
        ];
    }

    /**
     * Render checkbox column
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="customer[]" value="%s" />', esc_attr($item['id']));
    }

    /**
     * Render return rate column with color indicators
     */
    public function column_return_rate($item) {
        $rate  = (float) $item['return_rate'];
        $color = match (true) {
            $rate >= 50 => 'red',
            $rate >= 20 => 'orange',
            default     => 'green',
        };

        return "<strong style='color:{$color}'>" . esc_html($rate) . "%</strong>";
    }

    /**
     * Render action column
     */
    public function column_action($item) {
        $url = esc_url(admin_url("user-edit.php?user_id={$item['id']}"));
        return '<a href="' . $url . '" class="button" target="_blank">' . __('View', 'rs-order-blocker') . '</a>';
    }

    /**
     * Fallback renderer
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '<em>' . __('N/A', 'rs-order-blocker') . '</em>';
    }

    /**
     * Add export and bulk block controls
     */
    public function extra_tablenav($which) {
        if ($which === 'bottom') {
            $export_url = add_query_arg([
                'page'     => $_GET['page'] ?? '',
                'action'   => 'export_customer_risk',
                '_wpnonce' => wp_create_nonce('export_customer_risk_nonce'),
            ]);

            echo '<div class="alignleft actions">';
            echo '<button type="submit" name="block_action" class="button">' . __('Block Selected', 'rs-order-blocker') . '</button>';
            echo '<a href="' . esc_url($export_url) . '" class="button-primary">' . __('Export to CSV', 'rs-order-blocker') . '</a>';
            echo '</div>';
        }
    }

    /**
     * Prepare customer data and pagination
     */
    public function prepare_items() {
        global $wpdb;

        $is_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = $_GET['orderby'] ?? 'return_rate';
        $order   = strtoupper($_GET['order'] ?? 'DESC');
        $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $sort_field_map = [
            'name'           => 'u.display_name',
            'return_rate'    => 'return_rate',
            'returned_count' => 'returned_count',
            'orders'         => 'total_orders',
        ];
        $sort_field = $sort_field_map[$orderby] ?? 'return_rate';

        $rows = [];
        $total_items = 0;

        if ($is_hpos) {
            // HPOS
            $table_orders = "{$wpdb->prefix}wc_orders";
            $table_stats  = "{$wpdb->prefix}wc_order_stats";

            $sql = $wpdb->prepare(" SELECT s.customer_id AS id,
                u.display_name AS name,
                u.user_email AS email,
                COUNT(s.order_id) AS total_orders,
                SUM(CASE WHEN s.status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) AS returned_count,
                ROUND(
                    IF(COUNT(s.order_id) > 0,
                        (SUM(CASE WHEN s.status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) / COUNT(s.order_id)) * 100,
                        0
                    ), 1
                ) AS return_rate
                FROM {$wpdb->prefix}wc_order_stats s
                JOIN {$wpdb->users} u ON s.customer_id = u.ID
                WHERE s.status LIKE 'wc-%'
                GROUP BY s.customer_id
                HAVING returned_count > 0
                ORDER BY {$sort_field} {$order}
                LIMIT %d OFFSET %d
            ", $per_page, $offset);

            $rows = $wpdb->get_results($sql, ARRAY_A);


            // $total_items = $wpdb->get_var("SELECT COUNT(DISTINCT customer_id)
            // FROM {$table_stats}
            // WHERE status LIKE 'wc-%' ");

            $total_items = $wpdb->get_var(" SELECT COUNT(*) FROM (
                SELECT COUNT(*) AS returned_count
                FROM {$wpdb->prefix}wc_order_stats
                WHERE status LIKE 'wc-%'
                GROUP BY customer_id
                HAVING returned_count > 0
                ) AS subquery"
            );
        } else {
            // Legacy
            $sql = $wpdb->prepare(" SELECT 
                u.ID AS id,
                u.display_name AS name,
                u.user_email AS email,
                COUNT(p.ID) AS total_orders,
                SUM(CASE WHEN p.post_status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) AS returned_count,
                ROUND(
                    IF(COUNT(p.ID) > 0,
                        (SUM(CASE WHEN p.post_status IN ('wc-cancelled', 'wc-failed', 'wc-refunded') THEN 1 ELSE 0 END) / COUNT(p.ID)) * 100,
                        0
                    ), 1
                ) AS return_rate
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
            JOIN {$wpdb->users} u ON pm.meta_value = u.ID
            WHERE p.post_type = 'shop_order'
            AND p.post_status LIKE 'wc-%'
            GROUP BY u.ID
            HAVING returned_count > 0
            ORDER BY {$sort_field} {$order}
            LIMIT %d OFFSET %d
        ", $per_page, $offset);

            $rows = $wpdb->get_results($sql, ARRAY_A);


            // $total_items = $wpdb->get_var("SELECT COUNT(DISTINCT pm.meta_value)
            //     FROM {$wpdb->posts} p
            //     JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
            //     WHERE p.post_type = 'shop_order'
            //     AND p.post_status LIKE 'wc-%'
            // ");

            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM (
                SELECT COUNT(*) AS returned_count
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
                WHERE p.post_type = 'shop_order' AND p.post_status LIKE 'wc-%'
                GROUP BY pm.meta_value
                HAVING returned_count > 0
                ) AS subquery
            ");
        }

        // Final formatting
        $this->items = [];

        foreach ($rows as $row) {
            $user_id = (int) $row['id'];
            $is_blocked = get_user_meta($user_id, '_is_blocked_customer', true) === 'yes';

            $this->items[] = [
                'id'             => $user_id,
                'name'           => $row['name'],
                'email'          => $row['email'],
                'return_rate'    => $row['return_rate'],
                'returned_count' => (int) $row['returned_count'],
                'orders'         => (int) $row['total_orders'],
                'blocked'        => $is_blocked,
            ];
        }

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }


    /**
     * Handle CSV export
     */
    public static function handle_export() {
        if (
            isset($_GET['action'], $_GET['_wpnonce']) &&
            $_GET['action'] === 'export_customer_risk' &&
            wp_verify_nonce($_GET['_wpnonce'], 'export_customer_risk_nonce')
        ) {
            $instance = new self();
            $data = $instance->get_customer_risk_data();

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=customer-risk-export.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            fputcsv($output, ['Customer ID', 'Name', 'Email', 'Return Rate (%)', 'Returned Count', 'Orders', 'Blocked']);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['return_rate'],
                    $row['returned_count'],
                    $row['orders'],
                    $row['blocked'] ? 'Yes' : 'No',
                ]);
            }

            fclose($output);
            exit;
        }
    }

    /**
     * Sample/mock customer risk data
     * Replace this with real query later.
     */
    private function get_customer_risk_data(): array {
        return [
            [
                'id'             => 1,
                'name'           => 'Alice Smith',
                'email'          => 'alice@example.com',
                'return_rate'    => 60.5,
                'returned_count' => 5,
                'orders'         => 8,
                'blocked'        => true,
            ],
            [
                'id'             => 2,
                'name'           => 'Bob Johnson',
                'email'          => 'bob@example.com',
                'return_rate'    => 12.0,
                'returned_count' => 1,
                'orders'         => 9,
                'blocked'        => false,
            ],
            // ... add more rows or fetch from database
        ];
    }
}
