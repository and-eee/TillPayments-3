<?php
/**
 * Till Payments Receipt PDF Generation
 *
 * Handles PDF generation for payment receipts using Dompdf
 *
 * @package TillPayments
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_TillPayments_Receipt_PDF Class
 */
class WC_TillPayments_Receipt_PDF {

    /**
     * Generate PDF receipt for an order
     *
     * @param int $order_id WooCommerce order ID
     * @param bool $save_to_filesystem If true, save to file; if false, return PDF string
     * @return string|false File path if saved, PDF content if not saved, false on failure
     */
    public static function generate_pdf($order_id, $save_to_filesystem = true) {
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log('Till Payments: Cannot generate PDF - order not found: ' . $order_id);
            return false;
        }

        // Verify order was paid via Till Payments
        $payment_method = $order->get_payment_method();
        if (!in_array($payment_method, array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
            error_log('Till Payments: Cannot generate PDF - not a Till Payments order: ' . $order_id);
            return false;
        }

        try {
            // Get receipt HTML content
            $html_content = self::get_receipt_html($order_id);

            if (empty($html_content)) {
                throw new Exception('Failed to generate receipt HTML');
            }

            // Load Dompdf
            require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/vendor/autoload.php';

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html_content);
            $dompdf->render();

            if ($save_to_filesystem) {
                self::ensure_receipt_directory_exists();
                $file_path = self::get_pdf_file_path($order_id);
                $output = $dompdf->output();

                if (file_put_contents($file_path, $output) === false) {
                    throw new Exception('Failed to save PDF to filesystem');
                }

                return $file_path;
            } else {
                return $dompdf->output();
            }

        } catch (Exception $e) {
            error_log('Till Payments: PDF generation failed for order ' . $order_id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get HTML content for PDF receipt
     *
     * @param int $order_id
     * @return string
     */
    public static function get_receipt_html($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return '';
        }

        // Get receipt data using email class
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

        // Start building HTML
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html(sprintf(__('Receipt for Order %s', 'woocommerce'), $receipt_data['order_number'])); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            background-color: <?php echo esc_attr($brand_color); ?>;
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header img {
            max-height: 60px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            font-weight: normal;
        }
        .header .amount {
            font-size: 36px;
            font-weight: bold;
            margin: 15px 0;
        }
        .header .date {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px 20px;
            background: #ffffff;
        }
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .section:last-child {
            border-bottom: none;
        }
        .section h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: #999;
            margin: 0 0 10px 0;
            letter-spacing: 0.5px;
        }
        .section table {
            width: 100%;
            font-size: 14px;
            border-collapse: collapse;
        }
        .section table td {
            padding: 5px 0;
        }
        .section table td:first-child {
            color: #666;
        }
        .section table td:last-child {
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        .section .status {
            color: #28a745;
        }
        .section .transaction-id {
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .billing-info {
            font-size: 14px;
            color: #333;
        }
        .billing-info .name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .billing-info .email {
            color: #666;
            margin-bottom: 3px;
        }
        .billing-info .address {
            color: #666;
            line-height: 1.5;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <?php if (!empty($logo_url)) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($business_name); ?>">
            <?php endif; ?>

            <h1><?php echo esc_html__('Payment Receipt', 'woocommerce'); ?></h1>

            <div class="amount">
                <?php echo wp_kses_post($receipt_data['formatted_amount']); ?>
            </div>

            <div class="date">
                <?php echo esc_html(sprintf(__('Paid on %s', 'woocommerce'), $receipt_data['payment_date'])); ?>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content">

            <!-- Order Info Section -->
            <div class="section">
                <h3><?php echo esc_html__('Order Information', 'woocommerce'); ?></h3>
                <table>
                    <tr>
                        <td><?php echo esc_html__('Order Number:', 'woocommerce'); ?></td>
                        <td><?php echo esc_html($receipt_data['order_number']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Payment Method Section -->
            <div class="section">
                <h3><?php echo esc_html__('Payment Method', 'woocommerce'); ?></h3>
                <table>
                    <tr>
                        <td><?php echo esc_html__('Method:', 'woocommerce'); ?></td>
                        <td><?php echo esc_html($receipt_data['payment_method_details']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Transaction Details Section -->
            <div class="section">
                <h3><?php echo esc_html__('Transaction Details', 'woocommerce'); ?></h3>
                <table>
                    <?php if (!empty($receipt_data['reference_id'])) : ?>
                    <tr>
                        <td><?php echo esc_html__('Transaction ID:', 'woocommerce'); ?></td>
                        <td class="transaction-id"><?php echo esc_html($receipt_data['reference_id']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php echo esc_html__('Status:', 'woocommerce'); ?></td>
                        <td class="status"><?php echo esc_html($receipt_data['status']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Billing Information Section -->
            <div class="section">
                <h3><?php echo esc_html__('Billing To', 'woocommerce'); ?></h3>
                <div class="billing-info">
                    <div class="name"><?php echo esc_html($receipt_data['customer_name']); ?></div>
                    <div class="email"><?php echo esc_html($receipt_data['customer_email']); ?></div>
                    <div class="address"><?php echo wp_kses_post(nl2br($receipt_data['billing_address'])); ?></div>
                </div>
            </div>

        </div>

        <!-- Footer Section -->
        <div class="footer">
            <?php if (!empty($footer_text)) : ?>
                <?php echo wp_kses_post(nl2br(esc_html($footer_text))); ?>
                <br><br>
            <?php endif; ?>
            <?php echo esc_html(sprintf(__('Questions? Contact %s', 'woocommerce'), $contact_email)); ?>
            <br>
            <?php echo esc_html(sprintf(__('© %s %s', 'woocommerce'), date('Y'), $business_name)); ?>
        </div>
    </div>
</body>
</html>
        <?php
        $html = ob_get_clean();
        return $html;
    }

    /**
     * Get expected file path for order's PDF receipt
     *
     * @param int $order_id
     * @return string
     */
    public static function get_pdf_file_path($order_id) {
        $upload_dir = wp_upload_dir();
        $receipt_dir = $upload_dir['basedir'] . '/tillpayments-receipts/';
        return $receipt_dir . 'receipt-' . $order_id . '.pdf';
    }

    /**
     * Ensure receipts directory exists
     *
     * @return bool
     */
    public static function ensure_receipt_directory_exists() {
        $upload_dir = wp_upload_dir();
        $receipt_dir = $upload_dir['basedir'] . '/tillpayments-receipts/';

        if (!file_exists($receipt_dir)) {
            if (!wp_mkdir_p($receipt_dir)) {
                error_log('Till Payments: Failed to create receipts directory: ' . $receipt_dir);
                return false;
            }

            // Create .htaccess to prevent direct access
            $htaccess_file = $receipt_dir . '.htaccess';
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        return true;
    }

    /**
     * Cleanup old PDF receipts
     *
     * @param int $days Delete PDFs older than this many days (default 30)
     * @return int Number of files deleted
     */
    public static function cleanup_old_pdfs($days = 30) {
        $upload_dir = wp_upload_dir();
        $receipt_dir = $upload_dir['basedir'] . '/tillpayments-receipts/';

        if (!file_exists($receipt_dir)) {
            return 0;
        }

        $files = glob($receipt_dir . 'receipt-*.pdf');
        $deleted_count = 0;
        $cutoff_time = time() - ($days * DAY_IN_SECONDS);

        // Safety: Never delete PDFs less than 7 days old
        $safety_cutoff = time() - (7 * DAY_IN_SECONDS);
        $actual_cutoff = max($cutoff_time, $safety_cutoff);

        foreach ($files as $file) {
            $file_time = filemtime($file);
            if ($file_time && $file_time < $actual_cutoff) {
                if (@unlink($file)) {
                    $deleted_count++;
                } else {
                    error_log('Till Payments: Failed to delete old PDF: ' . $file);
                }
            }
        }

        if ($deleted_count > 0) {
            error_log('Till Payments: Cleaned up ' . $deleted_count . ' old PDF receipts');
        }

        return $deleted_count;
    }
}
