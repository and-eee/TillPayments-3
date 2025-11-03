<?php
/**
 * Plugin Name: WooCommerce Till Payments Extension
 * Description: Till Payments for WooCommerce
 * Version: 1.10.5
 * Author: Till Payments
 */

use TillPayments\Client\Transaction\Capture;
use TillPayments\Client\Transaction\Result as TransactionResult;

if (!defined('ABSPATH')) {
    exit;
}

define('TILL_PAYMENTS_EXTENSION_URL', 'https://gateway.tillpayments.com/');
define('TILL_PAYMENTS_EXTENSION_URL_TEST', 'https://test-gateway.tillpayments.com/');
define('TILL_PAYMENTS_EXTENSION_NAME', 'Till Payments');
define('TILL_PAYMENTS_EXTENSION_VERSION', '1.10.5');
define('TILL_PAYMENTS_EXTENSION_UID_PREFIX', 'till_payments_');
define('TILL_PAYMENTS_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-provider.php';
    require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-creditcard.php';
    require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-googlepay.php';
    require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-applepay.php';

    add_filter('woocommerce_payment_gateways', function ($methods) {
        foreach (WC_TillPayments_Provider::paymentMethods() as $paymentMethod) {
            $methods[] = $paymentMethod;
        }
        return $methods;
    }, 0);

    // Register Till Payments receipt email
    add_filter('woocommerce_email_classes', function($emails) {
        require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-receipt-email.php';
        $emails['WC_TillPayments_Receipt_Email'] = new WC_TillPayments_Receipt_Email();
        return $emails;
    });

    // Trigger receipt email on payment completion
    add_action('woocommerce_payment_complete', function($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if Till Payments gateway
        $payment_method = $order->get_payment_method();
        if (!in_array($payment_method, array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
            return;
        }

        // Trigger custom hook for receipt email
        do_action('tillpayments_send_receipt', $order_id);
    }, 10, 1);

    // Register receipt view endpoint
    add_action('init', function() {
        add_rewrite_endpoint('view-receipt', EP_ROOT | EP_PAGES);
    });

    // Handle receipt view page
    add_action('woocommerce_account_view-receipt_endpoint', function($order_id) {
        if (!$order_id) {
            return;
        }
        include TILL_PAYMENTS_EXTENSION_BASEDIR . 'templates/receipt/receipt-view.php';
    });

    // Add "View Receipt" button to order view page
    add_action('woocommerce_order_details_after_order_table', function($order) {
        $payment_method = $order->get_payment_method();
        if (!in_array($payment_method, array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
            return;
        }

        $receipt_url = wc_get_endpoint_url('view-receipt', $order->get_id(), wc_get_page_permalink('myaccount'));
        echo '<p><a href="' . esc_url($receipt_url) . '" class="button">' . esc_html__('View Payment Receipt', 'woocommerce') . '</a></p>';
    }, 10, 1);

    // Handle PDF download
    add_action('template_redirect', function() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'download_receipt') {
            return;
        }

        if (!isset($_GET['order_id']) || !isset($_GET['_wpnonce'])) {
            wp_die(__('Invalid request', 'woocommerce'));
        }

        $order_id = absint($_GET['order_id']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'download_receipt_' . $order_id)) {
            wp_die(__('Security check failed', 'woocommerce'));
        }

        if (!is_user_logged_in()) {
            wp_die(__('Please log in to download receipt', 'woocommerce'));
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_customer_id() !== get_current_user_id()) {
            wp_die(__('Receipt not found', 'woocommerce'));
        }

        // Check if Till Payments order
        if (!in_array($order->get_payment_method(), array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
            wp_die(__('Receipt not available for this payment method', 'woocommerce'));
        }

        // Generate PDF
        require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-receipt-pdf.php';
        $pdf_content = WC_TillPayments_Receipt_PDF::generate_pdf($order_id, false);

        if ($pdf_content === false) {
            wp_die(__('Failed to generate PDF receipt', 'woocommerce'));
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="receipt-' . $order->get_order_number() . '.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        echo $pdf_content;
        exit;
    });

    // Schedule PDF cleanup on plugin activation
    register_activation_hook(__FILE__, function() {
        if (!wp_next_scheduled('tillpayments_cleanup_pdfs')) {
            wp_schedule_event(time(), 'daily', 'tillpayments_cleanup_pdfs');
        }
        // Flush rewrite rules for new endpoint
        flush_rewrite_rules();
    });

    // PDF cleanup action
    add_action('tillpayments_cleanup_pdfs', function() {
        require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-receipt-pdf.php';
        WC_TillPayments_Receipt_PDF::cleanup_old_pdfs(30);
    });

    // add_filter('woocommerce_before_checkout_form', function(){
    add_filter('the_content', function($content){
        if(is_checkout_pay_page() || is_checkout()) {
            if(!empty($_GET['gateway_return_result']) && $_GET['gateway_return_result'] == 'error') {
                wc_print_notice(__('Payment failed or was declined', 'woocommerce'), 'error');
            }
        }
        return $content;
    }, 0, 1);

    add_action( 'init', 'woocommerce_clear_cart_url' );
    function woocommerce_clear_cart_url() {
        if (isset( $_GET['clear-cart']) && is_order_received_page()) {
            global $woocommerce;

            $woocommerce->cart->empty_cart();
        }
    }

    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook === 'post.php') {
            wp_enqueue_script('tillpayments_capture_script', plugins_url("/tillpayments/assets/js/capture-payments.js"), ['jquery'], TILL_PAYMENTS_EXTENSION_VERSION, false);
            wp_localize_script('tillpayments_capture_script', 'tp_capture', ['security' => wp_create_nonce('tillpayments_capture_payment')]);
        }
    });

    add_action('wp_ajax_tillpayments_capture_payment', function () {
        check_ajax_referer('tillpayments_capture_payment', 'security');

        if (!current_user_can( 'edit_shop_orders')) {
            wp_die(-1);
        }

        $payment_method_code = $_POST['payment_method'];
        $gateway = WC()->payment_gateways()->payment_gateways()[$payment_method_code];

        $gateway->log('Processing new '.$gateway->method_title.' capture...');

        $orderId = !empty($_POST['order_id']) ? $_POST['order_id'] : null;
        if (!$orderId) {
            $gateway->log('  > missing order ID!', WC_Log_Levels::ERROR);
            wp_send_json(['error' => 1, 'msg' => 'Missing order ID!']);
        }

        /**
         * order & user
         */
        $order = new WC_Order($orderId);

        /**
         * gateway client
         */
        WC_TillPayments_Provider::autoloadClient();
        TillPayments\Client\Client::setApiUrl($gateway->get_option('apiHost'));
        $client = new TillPayments\Client\Client(
            $gateway->get_option('apiUser'),
            htmlspecialchars_decode($gateway->get_option('apiPassword')),
            $gateway->get_option('apiKey'),
            $gateway->get_option('sharedSecret')
        );

        /**
         * transaction
         */
        $transaction = new Capture();
        $captureTxId = $orderId . '-capture-' . date('YmdHis') . substr(sha1(uniqid()), 0, 10);
        $transaction->setTransactionId($captureTxId)
            ->setAmount(floatval($order->get_total('')))
            ->setCurrency($order->get_currency())
            ->setReferenceTransactionId($order->get_meta('paymentUuid'));

        /**
         * transaction
         */
        $gateway->log('  > sending capture transaction request...');
        $result = $client->capture($transaction);

        if ($result->isSuccess()) {
            switch ($result->getReturnType()) {
                case TransactionResult::RETURN_TYPE_ERROR:
                    $errors = $result->getErrors();
                    $gateway->log('  > return type: ERROR', WC_Log_Levels::ERROR);
                    $gateway->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);

                    if (empty($errors)) {
                        wp_send_json(['error' => 1, 'msg' => 'Capture request failed!']);
                    }

                    $errorMsg = '';
                    foreach ($errors as $error) {
                        $errorMsg .= $error->getMessage() . PHP_EOL;
                    }

                    $order->add_order_note('TillPayments capture error: ' . $errorMsg, false);

                    wp_send_json(['error' => 1, 'msg' => $errorMsg]);
                case TransactionResult::RETURN_TYPE_PENDING:
                    $gateway->log('  > return type: PENDING');
                case TransactionResult::RETURN_TYPE_FINISHED:
                    $gateway->log('  > return type: FINISHED');
                    $order->add_order_note('TillPayments capture ID: ' . $result->getReferenceId(), false);

                    $order->update_meta_data('paymentUuid', $result->getReferenceId());
                    $order->update_meta_data('pending_capture', 'no');
                    $order->save_meta_data();

                    $order->payment_complete();

                    $gateway->log('  > result data: '.print_r($result->toArray(), true));

                    wp_send_json(['error' => 0]);
            }
        } else {
            $errors = $result->getErrors();

            if (empty($errors)) {
                wp_send_json(['error' => 1, 'msg' => 'Capture request failed!']);
            }

            $gateway->log('  > request failed', WC_Log_Levels::ERROR);
            $gateway->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);

            $errorMsg = '';
            foreach ($errors as $error) {
                $errorMsg .= $error->getMessage().PHP_EOL;
            }

            $order->add_order_note('TillPayments capture error: ' . $errorMsg, false);

            wp_send_json(['error' => 1, 'msg' => $errorMsg]);
        }

        /**
         * something went wrong
         */
        $gateway->log('  > fallback return point reached. something went wrong?', WC_Log_Levels::ERROR);
        wp_send_json(['error' => 1, 'msg' => 'Capture request failed!']);
    });
});
