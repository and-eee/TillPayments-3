<?php
/**
 * Till Payments Receipt View Template
 *
 * Displays payment receipt in My Account area
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/tillpayments/receipt-view.php
 *
 * @package TillPayments
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verify user is logged in
if (!is_user_logged_in()) {
    echo '<div class="woocommerce-info">' . esc_html__('Please log in to view receipt.', 'woocommerce') . '</div>';
    return;
}

// Get order ID from query var
$order_id = get_query_var('view-receipt', 0);

// Handle both endpoint and direct parameter
if (!$order_id && isset($_GET['order_id'])) {
    $order_id = absint($_GET['order_id']);
}

$order = wc_get_order($order_id);

// Security check: user owns order
if (!$order || $order->get_customer_id() !== get_current_user_id()) {
    echo '<div class="woocommerce-error">' . esc_html__('Receipt not found.', 'woocommerce') . '</div>';
    return;
}

// Check if Till Payments order
if (!in_array($order->get_payment_method(), array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
    echo '<div class="woocommerce-info">' . esc_html__('Receipt not available for this payment method.', 'woocommerce') . '</div>';
    return;
}

// Load receipt email class to get formatted data
require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-receipt-email.php';
$email_class = new WC_TillPayments_Receipt_Email();
$receipt_data = $email_class->get_receipt_data($order);

// Get gateway settings for branding
$gateway = wc_get_payment_gateway_by_order($order);
$brand_color = '#635bff';
$logo_url = '';
$business_name = get_bloginfo('name');
$contact_email = get_option('admin_email');
$footer_text = 'Thank you for your payment!';

if ($gateway && isset($gateway->settings)) {
    $brand_color = !empty($gateway->settings['receipt_brand_color']) ? $gateway->settings['receipt_brand_color'] : $brand_color;
    $logo_url = !empty($gateway->settings['receipt_logo']) ? $gateway->settings['receipt_logo'] : '';
    $business_name = !empty($gateway->settings['receipt_business_name']) ? $gateway->settings['receipt_business_name'] : $business_name;
    $contact_email = !empty($gateway->settings['receipt_contact_email']) ? $gateway->settings['receipt_contact_email'] : $contact_email;
    $footer_text = !empty($gateway->settings['receipt_footer_text']) ? $gateway->settings['receipt_footer_text'] : $footer_text;
}

// PDF download URL
$download_url = wp_nonce_url(
    add_query_arg(array('action' => 'download_receipt', 'order_id' => $order_id), home_url('/')),
    'download_receipt_' . $order_id
);
?>

<style>
.tillpayments-receipt-view {
    max-width: 600px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
}
.tillpayments-receipt-header {
    background-color: <?php echo esc_attr($brand_color); ?>;
    padding: 40px 20px;
    text-align: center;
    color: #ffffff;
    border-radius: 3px 3px 0 0;
}
.tillpayments-receipt-header img {
    max-height: 60px;
    margin-bottom: 15px;
}
.tillpayments-receipt-header h1 {
    font-size: 18px;
    margin: 0 0 5px 0;
    font-weight: normal;
}
.tillpayments-receipt-amount {
    font-size: 36px;
    font-weight: bold;
    margin: 15px 0;
}
.tillpayments-receipt-date {
    font-size: 14px;
    opacity: 0.9;
}
.tillpayments-receipt-content {
    padding: 30px 20px;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-top: none;
}
.tillpayments-receipt-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}
.tillpayments-receipt-section:last-child {
    border-bottom: none;
}
.tillpayments-receipt-section h3 {
    font-size: 12px;
    text-transform: uppercase;
    color: #999;
    margin: 0 0 10px 0;
    letter-spacing: 0.5px;
}
.tillpayments-receipt-section table {
    width: 100%;
    font-size: 14px;
    border-collapse: collapse;
}
.tillpayments-receipt-section table td {
    padding: 5px 0;
}
.tillpayments-receipt-section table td:first-child {
    color: #666;
}
.tillpayments-receipt-section table td:last-child {
    text-align: right;
    font-weight: bold;
    color: #333;
}
.tillpayments-receipt-status {
    color: #28a745 !important;
}
.tillpayments-receipt-transaction-id {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
.tillpayments-billing-info {
    font-size: 14px;
    color: #333;
}
.tillpayments-billing-info .name {
    font-weight: bold;
    margin-bottom: 5px;
}
.tillpayments-billing-info .email {
    color: #666;
    margin-bottom: 3px;
}
.tillpayments-billing-info .address {
    color: #666;
    line-height: 1.5;
}
.tillpayments-receipt-footer {
    background: #f5f5f5;
    padding: 20px;
    text-align: center;
    font-size: 12px;
    color: #666;
    line-height: 1.6;
    border: 1px solid #e0e0e0;
    border-top: none;
    border-radius: 0 0 3px 3px;
}
.tillpayments-receipt-actions {
    text-align: center;
    margin: 20px 0;
}
.tillpayments-receipt-actions .button {
    margin: 0 5px;
}
</style>

<div class="tillpayments-receipt-view">

    <div class="tillpayments-receipt-header">
        <?php if (!empty($logo_url)) : ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($business_name); ?>">
        <?php endif; ?>

        <h1><?php echo esc_html__('Payment Receipt', 'woocommerce'); ?></h1>

        <div class="tillpayments-receipt-amount">
            <?php echo wp_kses_post($receipt_data['formatted_amount']); ?>
        </div>

        <div class="tillpayments-receipt-date">
            <?php echo esc_html(sprintf(__('Paid on %s', 'woocommerce'), $receipt_data['payment_date'])); ?>
        </div>
    </div>

    <div class="tillpayments-receipt-content">

        <!-- Order Info Section -->
        <div class="tillpayments-receipt-section">
            <h3><?php echo esc_html__('Order Information', 'woocommerce'); ?></h3>
            <table>
                <tr>
                    <td><?php echo esc_html__('Order Number:', 'woocommerce'); ?></td>
                    <td>
                        <a href="<?php echo esc_url($receipt_data['order_view_url']); ?>">
                            <?php echo esc_html($receipt_data['order_number']); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Payment Method Section -->
        <div class="tillpayments-receipt-section">
            <h3><?php echo esc_html__('Payment Method', 'woocommerce'); ?></h3>
            <table>
                <tr>
                    <td><?php echo esc_html__('Method:', 'woocommerce'); ?></td>
                    <td><?php echo esc_html($receipt_data['payment_method_details']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Transaction Details Section -->
        <div class="tillpayments-receipt-section">
            <h3><?php echo esc_html__('Transaction Details', 'woocommerce'); ?></h3>
            <table>
                <?php if (!empty($receipt_data['reference_id'])) : ?>
                <tr>
                    <td><?php echo esc_html__('Transaction ID:', 'woocommerce'); ?></td>
                    <td class="tillpayments-receipt-transaction-id"><?php echo esc_html($receipt_data['reference_id']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo esc_html__('Status:', 'woocommerce'); ?></td>
                    <td class="tillpayments-receipt-status"><?php echo esc_html($receipt_data['status']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Billing Information Section -->
        <div class="tillpayments-receipt-section">
            <h3><?php echo esc_html__('Billing To', 'woocommerce'); ?></h3>
            <div class="tillpayments-billing-info">
                <div class="name"><?php echo esc_html($receipt_data['customer_name']); ?></div>
                <div class="email"><?php echo esc_html($receipt_data['customer_email']); ?></div>
                <div class="address"><?php echo wp_kses_post(nl2br($receipt_data['billing_address'])); ?></div>
            </div>
        </div>

    </div>

    <div class="tillpayments-receipt-footer">
        <?php if (!empty($footer_text)) : ?>
            <?php echo wp_kses_post(nl2br(esc_html($footer_text))); ?>
            <br><br>
        <?php endif; ?>
        <?php echo esc_html(sprintf(__('Questions? Contact %s', 'woocommerce'), $contact_email)); ?>
        <br>
        <?php echo esc_html(sprintf(__('© %s %s', 'woocommerce'), date('Y'), $business_name)); ?>
    </div>

    <div class="tillpayments-receipt-actions">
        <a href="<?php echo esc_url($download_url); ?>" class="button">
            <?php echo esc_html__('Download PDF Receipt', 'woocommerce'); ?>
        </a>
        <a href="<?php echo esc_url($receipt_data['order_view_url']); ?>" class="button">
            <?php echo esc_html__('View Order Details', 'woocommerce'); ?>
        </a>
    </div>

</div>
