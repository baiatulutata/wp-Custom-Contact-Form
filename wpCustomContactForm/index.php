<?php
/**
 * Plugin Name: Custom Contact Form
 * Plugin URI:  https://woomag.ro
 * Description: A WordPress plugin that provides a customizable contact form via a shortcode and a block editor component, with reCAPTCHA integration.
 * Version: 1.2
 * Author: Ionut Baldazar
 * Author URI: https://woomag.ro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue scripts and styles
function ccf_enqueue_scripts() {
    $recaptcha_site_key = get_option('ccf_recaptcha_site_key', '');
    if (!empty($recaptcha_site_key)) {
        wp_enqueue_script('ccf-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
    }
}
add_action('wp_enqueue_scripts', 'ccf_enqueue_scripts');

// Create database table on activation
function ccf_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ccf_messages';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'ccf_create_table');

// Save form submission to database
function ccf_handle_form_submission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ccf-email'])) {
        global $wpdb;
        $recaptcha_secret_key = get_option('ccf_recaptcha_secret_key', '');
        if (!empty($recaptcha_secret_key) && isset($_POST['g-recaptcha-response'])) {
            $recaptcha_response = $_POST['g-recaptcha-response'];
            $verify_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$recaptcha_response}";
            $response = wp_remote_get($verify_url);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body);
            if (!$result->success) {
                wp_die('reCAPTCHA verification failed.');
            }
        }

        $name = sanitize_text_field($_POST['ccf-name']);
        $email = sanitize_email($_POST['ccf-email']);
        $message = sanitize_textarea_field($_POST['ccf-message']);

        $wpdb->insert(
            $wpdb->prefix . 'ccf_messages',
            [
                'name' => $name,
                'email' => $email,
                'message' => $message,
            ],
            ['%s', '%s', '%s']
        );

        wp_mail(get_option('admin_email'), 'New Contact Form Message', "From: $name ($email)\n\n$message");
        echo '<p>Thank you for your message!</p>';
        exit;
    }
}
add_action('init', 'ccf_handle_form_submission');

// Admin menu for viewing messages
function ccf_add_admin_menu() {
    add_menu_page('Contact Messages', 'Contact Messages', 'manage_options', 'ccf_messages', 'ccf_messages_page');
}
add_action('admin_menu', 'ccf_add_admin_menu');

function ccf_messages_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ccf_messages';
    $messages = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>Contact Messages</h1>
        <table class="widefat fixed">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Message</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $message) : ?>
                <tr>
                    <td><?php echo esc_html($message->name); ?></td>
                    <td><?php echo esc_html($message->email); ?></td>
                    <td><?php echo esc_html($message->message); ?></td>
                    <td><?php echo esc_html($message->created_at); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
