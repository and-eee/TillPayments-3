<?php
/**
 * Till Payments Receipt Email Template (HTML)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/till-payments-receipt.php
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
$email = $email ?? null;

if (!$order) {
    return;
}

$gateway = wc_get_payment_gateway_by_order($order);
$brand_color = '#635bff'; // Default Stripe blue
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

do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="margin: 0 auto; max-width: 600px; font-family: Arial, Helvetica, sans-serif;">

    <!-- Header Section -->
    <div style="background-color: <?php echo esc_attr($brand_color); ?>; padding: 40px 20px; text-align: center;">
        <?php if (!empty($logo_url)) : ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($business_name); ?>" style="max-height: 60px; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">
        <?php endif; ?>

        <h1 style="color: #ffffff; font-size: 18px; margin: 0 0 5px 0; font-weight: normal;">
            <?php echo esc_html__('Payment Receipt', 'woocommerce'); ?>
        </h1>

        <div style="font-size: 36px; font-weight: bold; color: #ffffff; margin: 15px 0;">
            <?php echo wp_kses_post($receipt_data['formatted_amount']); ?>
        </div>

        <div style="color: #ffffff; font-size: 14px; opacity: 0.9;">
            <?php echo esc_html(sprintf(__('Paid on %s', 'woocommerce'), $receipt_data['payment_date'])); ?>
        </div>
    </div>

    <!-- Content Section -->
    <div style="padding: 30px 20px; background: #ffffff;">

        <!-- Order Info Section -->
        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
            <h3 style="font-size: 12px; text-transform: uppercase; color: #999; margin: 0 0 10px 0; letter-spacing: 0.5px;">
                <?php echo esc_html__('Order Information', 'woocommerce'); ?>
            </h3>
            <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px 0; color: #666;">
                        <?php echo esc_html__('Order Number:', 'woocommerce'); ?>
                    </td>
                    <td style="padding: 5px 0; text-align: right; font-weight: bold; color: #333;">
                        <a href="<?php echo esc_url($receipt_data['order_view_url']); ?>" style="color: #333; text-decoration: underline;">
                            <?php echo esc_html($receipt_data['order_number']); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Payment Method Section -->
        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
            <h3 style="font-size: 12px; text-transform: uppercase; color: #999; margin: 0 0 10px 0; letter-spacing: 0.5px;">
                <?php echo esc_html__('Payment Method', 'woocommerce'); ?>
            </h3>
            <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px 0; color: #666;">
                        <?php echo esc_html__('Method:', 'woocommerce'); ?>
                    </td>
                    <td style="padding: 5px 0; text-align: right; font-weight: bold; color: #333;">
                        <?php echo esc_html($receipt_data['payment_method_details']); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Transaction Details Section -->
        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
            <h3 style="font-size: 12px; text-transform: uppercase; color: #999; margin: 0 0 10px 0; letter-spacing: 0.5px;">
                <?php echo esc_html__('Transaction Details', 'woocommerce'); ?>
            </h3>
            <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                <?php if (!empty($receipt_data['reference_id'])) : ?>
                <tr>
                    <td style="padding: 5px 0; color: #666;">
                        <?php echo esc_html__('Transaction ID:', 'woocommerce'); ?>
                    </td>
                    <td style="padding: 5px 0; text-align: right; font-weight: bold; color: #333; font-family: 'Courier New', monospace; font-size: 13px;">
                        <?php echo esc_html($receipt_data['reference_id']); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 5px 0; color: #666;">
                        <?php echo esc_html__('Status:', 'woocommerce'); ?>
                    </td>
                    <td style="padding: 5px 0; text-align: right; font-weight: bold; color: #28a745;">
                        <?php echo esc_html($receipt_data['status']); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Billing Information Section -->
        <div style="margin-bottom: 30px;">
            <h3 style="font-size: 12px; text-transform: uppercase; color: #999; margin: 0 0 10px 0; letter-spacing: 0.5px;">
                <?php echo esc_html__('Billing To', 'woocommerce'); ?>
            </h3>
            <div style="font-size: 14px; color: #333;">
                <div style="font-weight: bold; margin-bottom: 5px;">
                    <?php echo esc_html($receipt_data['customer_name']); ?>
                </div>
                <div style="color: #666; margin-bottom: 3px;">
                    <?php echo esc_html($receipt_data['customer_email']); ?>
                </div>
                <div style="color: #666; line-height: 1.5;">
                    <?php echo wp_kses_post(nl2br($receipt_data['billing_address'])); ?>
                </div>
            </div>
        </div>

        <!-- View Receipt Link -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?php echo esc_url($receipt_data['receipt_view_url']); ?>" style="display: inline-block; padding: 12px 30px; background: <?php echo esc_attr($brand_color); ?>; color: #ffffff; text-decoration: none; border-radius: 3px; font-size: 14px; font-weight: bold;">
                <?php echo esc_html__('View Receipt Online', 'woocommerce'); ?>
            </a>
        </div>

    </div>

    <!-- Footer Section -->
    <div style="background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; line-height: 1.6;">
        <?php if (!empty($footer_text)) : ?>
            <?php echo wp_kses_post(nl2br(esc_html($footer_text))); ?>
            <br><br>
        <?php endif; ?>
        <?php echo esc_html(sprintf(__('Questions? Contact %s', 'woocommerce'), $contact_email)); ?>
        <br>
        <?php echo esc_html(sprintf(__('© %s %s', 'woocommerce'), date('Y'), $business_name)); ?>
    </div>

</div>

<?php
do_action('woocommerce_email_footer', $email);
