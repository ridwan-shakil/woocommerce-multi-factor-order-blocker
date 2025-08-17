<?php

namespace RS\OrderBlocker\Admin;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class FailedOrdersTable
 * Displays a custom admin table for failed/incomplete WooCommerce orders.
 */
class FailedOrdersTable extends \WP_List_Table {

    /**
     * Database table name with prefix
     * @var string
     */
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wc_order_blocker_incomplete_orders';

        parent::__construct([
            'singular' => 'failed_order',
            'plural'   => 'failed_orders',
            'ajax'     => false,
        ]);
    }

    /**
     * Define the table columns
     */
    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'id'             => __('Order ID', 'wcorder-blocker'),
            'status'         => __('Status', 'wcorder-blocker'),
            'customer_name'  => __('Name', 'wcorder-blocker'),
            'mobile'         => __('Phone', 'wcorder-blocker'),
            'email'          => __('Email', 'wcorder-blocker'),
            'cart_contents'  => __('Product info', 'wcorder-blocker'),
            'total'          => __('Cart Total', 'wcorder-blocker'),
            'created_at'     => __('Last Visited', 'wcorder-blocker'),
        ];
    }



    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'id'         => ['id', false],
            'status'     => ['status', false],
            'total'      => ['total', false],
            'created_at' => ['created_at', true],
        ];
    }

    /**
     * Define bulk actions available for this table
     */
    public function get_bulk_actions() {
        return [
            'delete'           => __('Delete', 'wcorder-blocker'),

        ];
    }

    /**
     * Render the checkbox column
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="selected_ids[]" value="%d" />', absint($item->id));
    }

    /**
     * Render the ID column with formatting
     */
    // public function column_id($item) {
    //     return sprintf('<strong>#%d</strong>', absint($item->id));
    // }

    /**
     * Render the total column using WooCommerce formatting
     */
    public function column_total($item) {
        return function_exists('wc_price') ? wc_price($item->total) : number_format($item->total, 2);
    }



    /**
     * Render the created_at column as formatted date
     */
    public function column_created_at($item) {
        return esc_html(date_i18n('Y-m-d H:i', strtotime($item->created_at)));
    }

    /**
     * Default column rendering fallback
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) && is_scalar($item->$column_name)
            ? esc_html($item->$column_name)
            : '<em>N/A</em>';
    }



    /**
     * Prepare items for display (pagination, filtering, sorting, search)
     */
    public function prepare_items() {
        global $wpdb;

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;
        $table        = $this->table;

        // Sortable columns
        $sortable_columns = ['id', 'status', 'total', 'created_at'];
        $orderby = isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $sortable_columns, true)
            ? $_REQUEST['orderby']
            : 'id';
        $order = (isset($_REQUEST['order']) && strtolower($_REQUEST['order']) === 'asc') ? 'ASC' : 'DESC';

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        $product_filter = isset($_GET['product_filter']) ? sanitize_text_field($_GET['product_filter']) : '';

        $where_clauses = [];
        $params = [];

        if (in_array($status_filter, ['cart', 'checkout', 'payment_failed'], true)) {
            $where_clauses[] = 'status = %s';
            $params[] = $status_filter;
        }

        if (!empty($search)) {
            $where_clauses[] = "(LOWER(status) LIKE %s OR LOWER(customer_name) LIKE %s OR LOWER(mobile) LIKE %s OR LOWER(email) LIKE %s OR total LIKE %s OR LOWER(cart_contents) LIKE %s OR created_at LIKE %s)";

            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Fetch all matching rows (for product filtering in PHP)
        $base_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order}";
        $all_data = !empty($params) ? $wpdb->get_results($wpdb->prepare($base_sql, ...$params)) : $wpdb->get_results($base_sql);

        // Filter by product in PHP
        if (!empty($product_filter)) {
            $product_filter_lower = strtolower($product_filter);
            $all_data = array_filter($all_data, function ($item) use ($product_filter_lower) {
                $cart_items = maybe_unserialize($item->cart_contents);
                if (!is_array($cart_items)) return false;

                foreach ($cart_items as $cart_item) {
                    $name = $cart_item['data']->get_name() ?? '';
                    $id   = $cart_item['product_id'] ?? 0;

                    if (
                        strpos(strtolower($name), $product_filter_lower) !== false ||
                        strpos((string)$id, $product_filter_lower) !== false
                    ) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Final pagination based on filtered data
        $all_data = array_values($all_data); // Re-index
        $total_items = count($all_data);
        $paged_data = array_slice($all_data, $offset, $per_page);

        // Assign
        $this->items = $paged_data;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->maybe_handle_row_actions();

        // Pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }



    // -------------------- ADD Row actions  ---------------------


    public function column_id($item) {
        $delete_url = add_query_arg([
            'action'    => 'rs_delete',
            'id'        => absint($item->id),
            '_wpnonce'  => wp_create_nonce('rs_delete_' . $item->id),
        ]);

        // Add JS confirm dialog
        $delete_link = sprintf(
            '<a href="%s" class="rs-delete-link" data-id="%d" style="color:red">%s</a>',
            esc_url($delete_url),
            absint($item->id),
            esc_html__('Delete', 'wcorder-blocker')
        );

        $actions = [
            'delete' => $delete_link,
        ];

        return sprintf('<strong>#%d</strong> %s', $item->id, $this->row_actions($actions));
    }


    protected function maybe_handle_row_actions() {
        global $wpdb;

        if (
            !isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) ||
            !is_admin() ||
            !current_user_can('manage_woocommerce')
        ) {
            return;
        }

        $id     = absint($_GET['id']);
        $action = sanitize_key($_GET['action']);
        $nonce  = $_GET['_wpnonce'];

        if ($action === 'rs_delete' && wp_verify_nonce($nonce, 'rs_delete_' . $id)) {
            $wpdb->delete($this->table, ['id' => $id]);
            wp_redirect(remove_query_arg(['action', 'id', '_wpnonce']));
            exit;
        }
    }



    /**
     * Add filter dropdown above the table
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') return;

        $status  = $_GET['status_filter'] ?? '';
        $product = sanitize_text_field($_GET['product_filter'] ?? '');
?>
        <div class="alignleft actions">
            <select name="status_filter">
                <option value=""><?php esc_html_e('All Statuses', 'wcorder-blocker'); ?></option>
                <option value="cart" <?php selected($status, 'cart'); ?>><?php esc_html_e('Cart', 'wcorder-blocker'); ?></option>
                <option value="checkout" <?php selected($status, 'checkout'); ?>><?php esc_html_e('Checkout', 'wcorder-blocker'); ?></option>
                <option value="payment_failed" <?php selected($status, 'payment_failed'); ?>> <?php esc_html_e('Payment Failed', 'wcorder-blocker'); ?></option>
            </select>

            <input type="text" id="product_filter" name="product_filter" value="<?php echo esc_attr($product); ?>" placeholder="Product name or ID" />

            <?php submit_button(esc_html('Filter'), '', 'filter_action', false); ?>
        </div>
<?php
    }


    // To show the "Others info" column data in human redable format (it was serialized) 
    public function column_cart_contents($item) {
        if (empty($item->cart_contents)) {
            return '<em>N/A</em>';
        }

        $contents = maybe_unserialize($item->cart_contents);
        if (!is_array($contents)) {
            return '<em>Invalid format</em>';
        }

        $output = [];

        foreach ($contents as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'] ?? 0;
            $quantity   = $cart_item['quantity'] ?? 0;

            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : esc_html__('(Product not found)', 'wcorder-blocker');

            $output[] = sprintf('%d × %s (ID: %d)', $quantity, esc_html($product_name), $product_id);
        }

        return implode('<br>', $output);
    }
}
