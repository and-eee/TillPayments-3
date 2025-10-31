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

// This is the v1.10.5 instance - all constants namespaced with V1_10_5 prefix to avoid conflicts
define('TILL_PAYMENTS_V1_10_5_EXTENSION_URL', 'https://gateway.tillpayments.com/');
define('TILL_PAYMENTS_V1_10_5_EXTENSION_URL_TEST', 'https://test-gateway.tillpayments.com/');
define('TILL_PAYMENTS_V1_10_5_EXTENSION_NAME', 'Till Payments v1.10.5');
define('TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION', '1.10.5');
define('TILL_PAYMENTS_V1_10_5_EXTENSION_UID_PREFIX', 'till_payments_v1_10_5_');
define('TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));
define('TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID', str_replace('.', '_', TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION));

// Hard-coded integration key for this plugin instance
define('TILL_PAYMENTS_V1_10_5_INTEGRATION_KEY', 'exGKVg98OepQoyTzZTEz');

/**
 * Automatic Settings Migration & Rewrite Rules Setup
 * When this plugin is activated:
 * 1. Copy settings from the original Till Payments plugin
 * 2. Flush rewrite rules so the saved cards endpoint works
 * This ensures the v1.10.5 plugin has its own independent copy of settings
 */
register_activation_hook(__FILE__, function() {
    // Get settings from the original Till Payments plugin
    $original_settings_key = 'woocommerce_till_payments_creditcard_settings';
    $original_settings = get_option($original_settings_key, []);

    // Settings to migrate
    $settings_to_migrate = [
        'title',
        'apiHost',
        'apiUser',
        'apiPassword',
        'apiKey',
        'sharedSecret',
        'transactionRequest'
    ];

    if (!empty($original_settings)) {
        // Create v1.10.5 settings with migrated values
        $v1_10_5_settings = [];
        foreach ($settings_to_migrate as $setting_key) {
            if (isset($original_settings[$setting_key])) {
                $v1_10_5_settings[$setting_key] = $original_settings[$setting_key];
            }
        }

        // Save to v1.10.5 gateway settings
        if (!empty($v1_10_5_settings)) {
            update_option('woocommerce_till_payments_v1_10_5_creditcard_settings', $v1_10_5_settings);

            // Also migrate GooglePay and ApplePay settings if they exist
            $googlepay_settings = get_option('woocommerce_till_payments_googlepay_settings', []);
            if (!empty($googlepay_settings)) {
                update_option('woocommerce_till_payments_v1_10_5_googlepay_settings', $googlepay_settings);
            }

            $applepay_settings = get_option('woocommerce_till_payments_applepay_settings', []);
            if (!empty($applepay_settings)) {
                update_option('woocommerce_till_payments_v1_10_5_applepay_settings', $applepay_settings);
            }

            // Mark migration as complete
            update_option('till_payments_v1_10_5_settings_migrated', 'yes');
        }
    }

    // Note: Rewrite rules will be automatically flushed by WordPress when needed
    // The endpoint is registered in the init hook, which is the correct place
});

/**
 * Deactivation hook - clean up when plugin is disabled
 */
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules to remove the custom endpoint
    flush_rewrite_rules();
});

// Define global function at plugin load time (before plugins_loaded hook)
if (!function_exists('woocommerce_clear_cart_url_v1_10_5')) {
    function woocommerce_clear_cart_url_v1_10_5() {
        if (isset($_GET['clear-cart']) && is_order_received_page()) {
            global $woocommerce;
            $woocommerce->cart->empty_cart();
        }
    }
    add_action('init', 'woocommerce_clear_cart_url_v1_10_5');
}

/**
 * Register the saved cards endpoint
 * MUST be in init hook - NOT plugins_loaded (wp_rewrite not ready yet)
 */
add_action('init', function () {
    // Register endpoint - wp_rewrite is now initialized
    add_rewrite_endpoint('till-payments-saved-cards', EP_ROOT | EP_PAGES);
});

/**
 * Add "Saved Cards" endpoint to My Account menu
 */
add_filter('woocommerce_account_menu_items', function ($items) {
    // Add saved cards page before Logout
    $logout = $items['customer-logout'];
    unset($items['customer-logout']);
    $items['till-payments-saved-cards'] = 'Saved Cards';
    $items['customer-logout'] = $logout;
    return $items;
});

/**
 * Display saved cards on the My Account page
 */
add_action('woocommerce_account_till-payments-saved-cards_endpoint', function () {
    if (!is_user_logged_in()) {
        return;
    }

    $userId = get_current_user_id();
    $savedCards = get_user_meta($userId, 'till_payments_v1_10_5_saved_cards', true);
    $savedCards = is_array($savedCards) ? $savedCards : [];

    echo '<h2>Saved Payment Cards</h2>';

    if (empty($savedCards)) {
        echo '<p>You have no saved cards. When you make a purchase, you can choose to save your card for future use.</p>';
        return;
    }

    echo '<table class="woocommerce-table woocommerce-table--orders">';
    echo '<thead><tr>';
    echo '<th class="woocommerce-table__heading">Card</th>';
    echo '<th class="woocommerce-table__heading">Expires</th>';
    echo '<th class="woocommerce-table__heading">Saved</th>';
    echo '<th class="woocommerce-table__heading">Action</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($savedCards as $cardId => $card) {
        $cardDisplay = isset($card['brand']) ? $card['brand'] : 'Card';
        $cardDisplay .= ' •••• ' . (isset($card['last_4']) ? $card['last_4'] : '****');

        echo '<tr>';
        echo '<td class="woocommerce-table__cell woocommerce-table__cell-order-number">' . esc_html($cardDisplay) . '</td>';
        echo '<td class="woocommerce-table__cell">' . esc_html(isset($card['expiry']) ? $card['expiry'] : 'N/A') . '</td>';
        echo '<td class="woocommerce-table__cell">' . esc_html(isset($card['saved_date']) ? date('M j, Y', strtotime($card['saved_date'])) : 'N/A') . '</td>';
        echo '<td class="woocommerce-table__cell">';
        echo '<form method="POST" style="display:inline;">';
        wp_nonce_field('till_payments_delete_card');
        echo '<input type="hidden" name="delete_card_id" value="' . esc_attr($cardId) . '">';
        echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this card?\');">Delete</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
});

/**
 * Handle card deletion from My Account page
 * SECURITY: Uses gateway's secure deleteSavedCard() method with HTTPS enforcement and audit logging
 */
add_action('init', function () {
    if (is_user_logged_in() && !empty($_POST['delete_card_id'])) {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'till_payments_delete_card')) {
            wp_die('Security check failed');
        }

        try {
            // Get the credit card gateway instance to use its secure deletion method
            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateway = isset($gateways['till_payments_v1_10_5_creditcard']) ? $gateways['till_payments_v1_10_5_creditcard'] : null;

            if (!$gateway) {
                wc_add_notice('Payment gateway not available. Card deletion failed.', 'error');
                return;
            }

            $cardId = sanitize_text_field($_POST['delete_card_id']);

            // SECURITY: Call gateway's secure deletion method with HTTPS enforcement and audit logging
            $deleted = $gateway->deleteSavedCard($cardId);

            if ($deleted) {
                wc_add_notice('Card has been deleted successfully.', 'success');
                wp_redirect(wc_get_account_endpoint_url('till-payments-saved-cards'));
                exit;
            } else {
                wc_add_notice('Card deletion failed. Card not found or security check failed.', 'error');
            }
        } catch (\Exception $e) {
            wc_add_notice('Error deleting card: ' . esc_html($e->getMessage()), 'error');
        }
    }
});

add_action('plugins_loaded', function () {
    require_once TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR . 'classes/includes/till-payments-provider.php';
    require_once TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR . 'classes/includes/till-payments-creditcard.php';
    require_once TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR . 'classes/includes/till-payments-googlepay.php';
    require_once TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR . 'classes/includes/till-payments-applepay.php';

    add_filter('woocommerce_payment_gateways', function ($methods) {
        foreach (WC_TillPayments_V1_10_5_Provider::paymentMethods() as $paymentMethod) {
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
            wp_enqueue_script('tillpayments_capture_script_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, plugins_url("/tillpayments/assets/js/capture-payments.js"), ['jquery'], TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION, false);
            wp_localize_script('tillpayments_capture_script_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, 'tp_capture_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, ['security' => wp_create_nonce('tillpayments_capture_payment_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID)]);
        }
    });

    add_action('wp_ajax_tillpayments_capture_payment_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, function () {
        check_ajax_referer('tillpayments_capture_payment_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, 'security');

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
        WC_TillPayments_V1_10_5_Provider::autoloadClient();
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
            ->setReferenceTransactionId($order->get_meta('paymentUuid_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID));

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

                    $order->update_meta_data('paymentUuid_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, $result->getReferenceId());
                    $order->update_meta_data('pending_capture_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, 'no');
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
