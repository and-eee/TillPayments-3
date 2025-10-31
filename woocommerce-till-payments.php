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

if (!defined('TILL_PAYMENTS_EXTENSION_URL')) {
    define('TILL_PAYMENTS_EXTENSION_URL', 'https://gateway.tillpayments.com/');
}
if (!defined('TILL_PAYMENTS_EXTENSION_URL_TEST')) {
    define('TILL_PAYMENTS_EXTENSION_URL_TEST', 'https://test-gateway.tillpayments.com/');
}
if (!defined('TILL_PAYMENTS_EXTENSION_NAME')) {
    define('TILL_PAYMENTS_EXTENSION_NAME', 'Till Payments');
}
if (!defined('TILL_PAYMENTS_EXTENSION_VERSION')) {
    define('TILL_PAYMENTS_EXTENSION_VERSION', '1.10.5');
}
if (!defined('TILL_PAYMENTS_EXTENSION_UID_PREFIX')) {
    define('TILL_PAYMENTS_EXTENSION_UID_PREFIX', 'till_payments_');
}
if (!defined('TILL_PAYMENTS_EXTENSION_BASEDIR')) {
    define('TILL_PAYMENTS_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));
}
if (!defined('TILL_PAYMENTS_EXTENSION_VERSION_ID')) {
    define('TILL_PAYMENTS_EXTENSION_VERSION_ID', str_replace('.', '_', TILL_PAYMENTS_EXTENSION_VERSION));
}

// Hard-coded integration key for this plugin instance
if (!defined('TILL_PAYMENTS_INTEGRATION_KEY')) {
    define('TILL_PAYMENTS_INTEGRATION_KEY', 'exGKVg98OepQoyTzZTEz');
}

// Define global function at plugin load time (before plugins_loaded hook)
if (!function_exists('woocommerce_clear_cart_url')) {
    function woocommerce_clear_cart_url() {
        if (isset($_GET['clear-cart']) && is_order_received_page()) {
            global $woocommerce;
            $woocommerce->cart->empty_cart();
        }
    }
    add_action('init', 'woocommerce_clear_cart_url');
}

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

    // add_filter('woocommerce_before_checkout_form', function(){
    add_filter('the_content', function($content){
        if(is_checkout_pay_page() || is_checkout()) {
            if(!empty($_GET['gateway_return_result']) && $_GET['gateway_return_result'] == 'error') {
                wc_print_notice(__('Payment failed or was declined', 'woocommerce'), 'error');
            }
        }
        return $content;
    }, 0, 1);

    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook === 'post.php') {
            wp_enqueue_script('tillpayments_capture_script_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, plugins_url("/tillpayments/assets/js/capture-payments.js"), ['jquery'], TILL_PAYMENTS_EXTENSION_VERSION, false);
            wp_localize_script('tillpayments_capture_script_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, 'tp_capture_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, ['security' => wp_create_nonce('tillpayments_capture_payment_' . TILL_PAYMENTS_EXTENSION_VERSION_ID)]);
        }
    });

    add_action('wp_ajax_tillpayments_capture_payment_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, function () {
        check_ajax_referer('tillpayments_capture_payment_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, 'security');

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
            ->setReferenceTransactionId($order->get_meta('paymentUuid_' . TILL_PAYMENTS_EXTENSION_VERSION_ID));

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

                    $order->update_meta_data('paymentUuid_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, $result->getReferenceId());
                    $order->update_meta_data('pending_capture_' . TILL_PAYMENTS_EXTENSION_VERSION_ID, 'no');
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
