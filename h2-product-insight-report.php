<?php
/*
File Name: h2-product-insight-report.php
Plugin Name: H2 Product Insight Report
Description: Provides a report of subscriptions for registered users via shortcode.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class H2_Product_Insight_Report {
    private static $instance = null;
    private static $table_name = 'my_first_plugin_subscriptions';

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcode
        add_shortcode('h2_product_insight_report', array($this, 'render_report'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_update_customer_domain', array($this, 'update_customer_domain'));
        add_action('wp_ajax_nopriv_update_customer_domain', array($this, 'no_priv_ajax'));
    }

    public function enqueue_scripts() {
        // Only enqueue if shortcode is present
        if (is_page() && has_shortcode(get_post()->post_content, 'h2_product_insight_report')) {
            // Enqueue jQuery (if not already)
            wp_enqueue_script('jquery');

            // Enqueue custom JS
            wp_enqueue_script(
                'h2-product-insight-report-js',
                plugin_dir_url(__FILE__) . 'js/h2-product-insight-report.js',
                array('jquery'),
                '1.0',
                true
            );

            // Localize script with AJAX URL and nonce
            wp_localize_script('h2-product-insight-report-js', 'h2_report_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('h2_report_nonce'),
            ));

            // Enqueue custom CSS
            wp_enqueue_style(
                'h2-product-insight-report-css',
                plugin_dir_url(__FILE__) . 'css/h2-product-insight-report.css',
                array(),
                '1.0'
            );
        }
    }

    public function render_report($atts) {
        if (!is_user_logged_in()) {
            return '<p>You need to be logged in to view this report.</p>';
        }

        $current_user = wp_get_current_user();
        // $customer_id = get_user_meta($current_user->ID, 'customer_id', true);
        $customer_id = $current_user->ID;

        if (empty($customer_id)) {
            return '<p>No customer ID found for the current user.</p>';
        }

        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;

        // Prepare and execute the query
        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE customer_id = %d",
                $customer_id
            ),
            ARRAY_A
        );

        if (empty($subscriptions)) {
            return '<p>No subscriptions found for your account.</p>';
        }

        // Start building the HTML table
        ob_start();
        ?>
        <div class="h2-report-container">
            <h2>Subscription Report</h2>
            <table class="h2-report-table">
                <thead>
                    <tr>
                        <th>Subscription Plan</th>
                        <th>Plugin Type</th>
                        <th>Status</th>
                        <th>Customer Domain</th>
                        <th>API Key</th>
                        <th>Query Count</th>
                        <th>Query Limit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr data-subscription-id="<?php echo esc_attr($subscription['id']); ?>">
                            <td><?php echo esc_html($subscription['subscription_plan']); ?></td>
                            <td><?php echo esc_html($subscription['plugin_type']); ?></td>
                            <td><?php echo esc_html(ucfirst($subscription['subscription_status'])); ?></td>
                            <td class="editable-domain">
                                <span class="domain-text"><?php echo esc_html($subscription['customer_domain']); ?></span>
                                <button class="edit-domain-button" data-domain="<?php echo esc_attr($subscription['customer_domain']); ?>">Edit</button>
                            </td>
                            <td>
                                <span class="api-key-text"><?php echo esc_html($subscription['api_key']); ?></span>
                                <button class="copy-api-key-button">Copy</button>
                            </td>
                            <td><?php echo esc_html($subscription['query_count']); ?></td>
                            <td><?php echo esc_html($subscription['query_limit']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal for editing customer_domain -->
            <div id="h2-domain-modal" class="h2-modal">
                <div class="h2-modal-content">
                    <span class="h2-close">&times;</span>
                    <h2>Edit Customer Domain</h2>
                    <input type="hidden" id="h2-modal-subscription-id" value="">
                    <input type="text" id="h2-modal-new-domain" value="" placeholder="Enter new domain">
                    <button id="h2-save-domain">Save</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function update_customer_domain() {
        // Check nonce
        check_ajax_referer('h2_report_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $new_domain = isset($_POST['new_domain']) ? sanitize_text_field($_POST['new_domain']) : '';

        if (empty($subscription_id) || empty($new_domain)) {
            wp_send_json_error('Invalid data');
        }

        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;

        // Get current user and customer_id
        $current_user = wp_get_current_user();
        // $customer_id = get_user_meta($current_user->ID, 'customer_id', true);
        $customer_id = $current_user->ID;

        if (empty($customer_id)) {
            wp_send_json_error('Customer ID not found');
        }

        // Verify the subscription belongs to the user
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND customer_id = %d",
                $subscription_id,
                $customer_id
            )
        );

        if (!$subscription) {
            wp_send_json_error('Subscription not found');
        }

        // Update the customer_domain
        $updated = $wpdb->update(
            $table,
            array('customer_domain' => $new_domain),
            array('id' => $subscription_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Customer domain updated successfully');
        } else {
            wp_send_json_error('Failed to update customer domain');
        }
    }

    public function no_priv_ajax() {
        wp_send_json_error('No permission');
    }
}

// Initialize the plugin
H2_Product_Insight_Report::get_instance();
