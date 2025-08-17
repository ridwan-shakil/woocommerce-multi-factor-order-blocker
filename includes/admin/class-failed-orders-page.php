<?php

namespace RS\OrderBlocker\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class FailedOrdersPage {

    /**
     * Render the incomplete orders admin page
     */
    public static function render() {
        $table = new FailedOrdersTable();

        // Handle bulk actions before rendering
        self::process_bulk_actions();

        echo '<div class="wrap">';
        self::render_summary();

        echo '<div class="incomplete-order-table">';
        echo '<h1>' . esc_html__('Incomplete Orders List', 'wcorder-blocker') . '</h1>';

        // Prepare the table items
        $table->prepare_items();

        echo '<form method="post">';
        $table->search_box(__('Search Orders', 'wcorder-blocker'), 'failed-orders');
        $table->display();
        wp_nonce_field('rs_failed_orders_bulk_action', 'rs_failed_orders_nonce');
        echo '</form>';

        // Export button (outside the table form)
        $export_url = wp_nonce_url(
            add_query_arg(['rs_export_csv' => 1], admin_url('admin.php?page=rs-failed-orders')),
            'rs_export_csv'
        );
        echo '<div class="export-csv">';
        echo '<a href="' . esc_url($export_url) . '" class="button button-secondary export-failed-orderlist">' . esc_html__('⬇️ Export CSV', 'wcorder-blocker') . '</a>';
        echo '</div>';

        echo '</div>'; // .incomplete-order-table
        echo '</div>'; // .wrap

        // JS: Confirm before delete
        add_action('admin_footer', function () {
?>
            <script>
                jQuery(function($) {
                    $(document).on('click', '.rs-delete-link', function(e) {
                        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this record?', 'wcorder-blocker')); ?>')) {
                            e.preventDefault();
                        }
                    });
                });
            </script>
        <?php
        });
    }

    /**
     * Process bulk actions from the WP_List_Table
     */
    private static function process_bulk_actions() {
        if (
            !current_user_can('manage_woocommerce') ||
            empty($_POST['rs_failed_orders_nonce']) ||
            !check_admin_referer('rs_failed_orders_bulk_action', 'rs_failed_orders_nonce')
        ) {
            return;
        }

        $action = $_POST['action'] ?? $_POST['action2'] ?? '';
        $action = sanitize_text_field($action);

        if ($action !== 'delete' || empty($_POST['selected_ids']) || !is_array($_POST['selected_ids'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';
        $ids = array_map('absint', $_POST['selected_ids']);

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query(
                $wpdb->prepare("DELETE FROM $table_name WHERE id IN ($placeholders)", ...$ids)
            );

            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>' . esc_html__('Selected records deleted.', 'wcorder-blocker') . '</p></div>';
            });
        }
    }

    /**
     * Export failed/incomplete orders to CSV
     */
    public static function maybe_export_csv() {
        if (
            !is_admin() ||
            !isset($_GET['rs_export_csv']) ||
            !current_user_can('manage_woocommerce') ||
            !check_admin_referer('rs_export_csv')
        ) {
            return;
        }

        self::export_failed_orders_to_csv();
    }

    private static function export_failed_orders_to_csv() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';

        $where = [];
        $params = [];

        // Filters
        if (!empty($_GET['status_filter']) && in_array($_GET['status_filter'], ['cart', 'checkout', 'payment_failed'], true)) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($_GET['status_filter']);
        }

        if (!empty($_GET['s'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = "(customer_name LIKE %s OR mobile LIKE %s OR email LIKE %s)";
            $params = array_merge($params, [$like, $like, $like]);
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC";
        $results = $params ? $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A) : $wpdb->get_results($query, ARRAY_A);

        // Output CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="failed-orders-export.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Status', 'Name', 'Mobile', 'Email', 'Product Info', 'Cart Total', 'Last Visited']);

        foreach ($results as $row) {
            fputcsv($output, [
                $row['id'],
                $row['status'],
                $row['customer_name'],
                $row['mobile'],
                $row['email'],
                self::format_cart_contents_for_csv($row['cart_contents']),
                $row['total'],
                $row['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Convert serialized cart data to readable string for CSV
     */
    private static function format_cart_contents_for_csv($serialized) {
        $contents = maybe_unserialize($serialized);
        if (!is_array($contents)) {
            return 'N/A';
        }

        $items = [];
        foreach ($contents as $item) {
            $product_id = $item['product_id'] ?? 0;
            $qty = $item['quantity'] ?? 1;
            $product = wc_get_product($product_id);
            $name = $product ? $product->get_name() : 'Product #' . $product_id;
            $items[] = sprintf('%d × %s (ID: %d)', $qty, $name, $product_id);
        }

        return implode('; ', $items);
    }

    /**
     * Display summary stats for top of admin page
     */
    private static function render_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $revenue = (float) $wpdb->get_var("SELECT SUM(total) FROM $table");
        $cart = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'cart'");
        $checkout = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'checkout'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'payment_failed'");
        ?>
        <div class="rs-ob-summary-boxes">
            <div class="rs-ob-box status-total">
                <h3><?php esc_html_e('Total Incomplete Orders', 'wcorder-blocker'); ?></h3>
                <p><?php echo esc_html(number_format_i18n($total)); ?></p>
            </div>
            <div class="rs-ob-box status-revenue">
                <h3><?php esc_html_e('Potential Sales Missed', 'wcorder-blocker'); ?></h3>
                <p><?php echo wc_price($revenue); ?></p>
            </div>
            <div class="rs-ob-box status-cart">
                <h3><?php esc_html_e('Cart Abandons', 'wcorder-blocker'); ?></h3>
                <p><?php echo esc_html(number_format_i18n($cart)); ?></p>
            </div>
            <div class="rs-ob-box status-checkout">
                <h3><?php esc_html_e('Checkout Abandons', 'wcorder-blocker'); ?></h3>
                <p><?php echo esc_html(number_format_i18n($checkout)); ?></p>
            </div>
            <div class="rs-ob-box status-failed">
                <h3><?php esc_html_e('Payment Failures', 'wcorder-blocker'); ?></h3>
                <p><?php echo esc_html(number_format_i18n($failed)); ?></p>
            </div>
        </div>
<?php
    }
}
