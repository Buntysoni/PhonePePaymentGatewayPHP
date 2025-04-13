<?php
/**
 * Plugin Name: PhonePe Payments
 * Description: Custom Plugin By UNLEIN Private Limited (Guruji Gyan) for Managing PhonePe Payments History.
 * Version: 1.1
 * Author: Unlein Private Limited (Guruji Gyan)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Function to create the database table if it does not exist
function custom_api_create_orders_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_orders';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) NOT NULL UNIQUE,
        state VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        udf1 TEXT NULL,
        udf2 TEXT NULL,
        error_code VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Hook to run the function when the plugin is activated
register_activation_hook(__FILE__, 'custom_api_create_orders_table');

// Register the API route
add_action('rest_api_init', function () {
    register_rest_route('custom-api/v1', '/save-order/', array(
        'methods' => 'POST',
        'callback' => 'custom_api_save_or_update_order',
        'permission_callback' => '__return_true' // Adjust permissions if needed
    ));
});

// Function to handle API request and save/update data
function custom_api_save_or_update_order(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_orders';

    // Get JSON data from request
    $data = $request->get_json_params();
    
    $order_id = sanitize_text_field($data['orderId']);
    $state = sanitize_text_field($data['state']);
    $amount = floatval($data['amount']);
    $udf1 = sanitize_text_field($data['metaInfo']['udf1'] ?? '');
    $udf2 = sanitize_text_field($data['metaInfo']['udf2'] ?? '');
    $error_code = sanitize_text_field($data['errorCode'] ?? '');

    // Check if order_id already exists
    $existing_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %s", $order_id));

    if ($existing_order) {
        // Update existing record
        $wpdb->update(
            $table_name,
            array(
                'state' => $state,
                'error_code' => $error_code,
                'updated_at' => current_time('mysql'),
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        return new WP_REST_Response(array('message' => 'Order updated successfully!', 'order_id' => $order_id), 200);
    } else {
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'state' => $state,
                'amount' => $amount,
                'udf1' => $udf1,
                'udf2' => $udf2,
                'error_code' => $error_code,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_REST_Response(array('error' => 'Failed to save order data'), 500);
        }

        return new WP_REST_Response(array('message' => 'Order data saved successfully!', 'order_id' => $order_id, 'state' => $state), 201);
    }
}

// ===========================
// ✅ ADMIN PAGE TO VIEW ORDERS
// ===========================

// Add admin menu for PhonePe Orders
add_action('admin_menu', 'custom_api_add_admin_menu');

function custom_api_add_admin_menu() {
    add_menu_page(
        'PhonePe Orders',
        'PhonePe Orders',
        'manage_options',
        'phonepe-orders',
        'custom_api_display_orders',
        'dashicons-list-view',
        25
    );
}

// Function to display order data in the admin panel
function custom_api_display_orders() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_orders';

    // Fetch orders from the database
    $orders = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    echo '<div class="wrap"><h1>PhonePe Orders</h1>';

    if (empty($orders)) {
        echo '<p>No orders found.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>
                <tr>
                    <th>Order ID</th>
                    <th>State</th>
                    <th>Amount</th>
                    <th>User Name</th>
                    <th>Phone</th>
                    <th>Error Code</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
              </thead>';
        echo '<tbody>';
        foreach ($orders as $order) {
            echo "<tr>
                    <td>{$order->order_id}</td>
                    <td>{$order->state}</td>
                    <td>" . ($order->amount / 100) . "</td>
                    <td>{$order->udf1}</td>
                    <td>{$order->udf2}</td>
                    <td>{$order->error_code}</td>
                    <td>{$order->created_at}</td>
                    <td>{$order->updated_at}</td>
                  </tr>";
        }
        echo '</tbody></table>';
    }
    
    echo '</div>';
}
?>
