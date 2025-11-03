<?php
/**
 * Till Payments Receipt Email Template (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/till-payments-receipt.php
 *
 * @package TillPayments
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get gateway settings for branding
$order = $order ?? null;
$receipt_data = $receipt_data ?? array();

if (!$order) {
    return;
}

$gateway = wc_get_payment_gateway_by_order($order);
$business_name = get_bloginfo('name');
$contact_email = get_option('admin_email');
$footer_text = 'Thank you for your payment!';

if ($gateway && isset($gateway->settings)) {
    $business_name = !empty($gateway->settings['receipt_business_name']) ? $gateway->settings['receipt_business_name'] : $business_name;
    $contact_email = !empty($gateway->settings['receipt_contact_email']) ? $gateway->settings['receipt_contact_email'] : $contact_email;
    $footer_text = !empty($gateway->settings['receipt_footer_text']) ? $gateway->settings['receipt_footer_text'] : $footer_text;
}

echo "= " . wp_strip_all_tags($email_heading) . " =\n\n";

?>
========================================
<?php echo strtoupper(__('PAYMENT RECEIPT', 'woocommerce')); ?>

========================================

<?php echo sprintf(__('Amount Paid: %s', 'woocommerce'), wp_strip_all_tags($receipt_data['formatted_amount'])); ?>

<?php echo sprintf(__('Date: %s', 'woocommerce'), $receipt_data['payment_date']); ?>


----------------------------------------
<?php echo strtoupper(__('ORDER INFORMATION', 'woocommerce')); ?>

----------------------------------------
<?php echo sprintf(__('Order Number: %s', 'woocommerce'), $receipt_data['order_number']); ?>


----------------------------------------
<?php echo strtoupper(__('PAYMENT METHOD', 'woocommerce')); ?>

----------------------------------------
<?php echo $receipt_data['payment_method_details']; ?>


----------------------------------------
<?php echo strtoupper(__('TRANSACTION DETAILS', 'woocommerce')); ?>

----------------------------------------
<?php if (!empty($receipt_data['reference_id'])) : ?>
<?php echo sprintf(__('Transaction ID: %s', 'woocommerce'), $receipt_data['reference_id']); ?>

<?php endif; ?>
<?php echo sprintf(__('Status: %s', 'woocommerce'), $receipt_data['status']); ?>


----------------------------------------
<?php echo strtoupper(__('BILLING INFORMATION', 'woocommerce')); ?>

----------------------------------------
<?php echo $receipt_data['customer_name']; ?>

<?php echo $receipt_data['customer_email']; ?>

<?php echo wp_strip_all_tags(str_replace('<br/>', "\n", $receipt_data['billing_address'])); ?>


----------------------------------------

<?php echo __('View your receipt online:', 'woocommerce'); ?>

<?php echo $receipt_data['receipt_view_url']; ?>


<?php echo __('View your order:', 'woocommerce'); ?>

<?php echo $receipt_data['order_view_url']; ?>


----------------------------------------
<?php echo $footer_text; ?>


<?php echo sprintf(__('Questions? Contact %s', 'woocommerce'), $contact_email); ?>

<?php echo sprintf(__('© %s %s', 'woocommerce'), date('Y'), $business_name); ?>


<?php
echo "\n\n" . apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
