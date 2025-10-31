<?php

use TillPayments\Client\Transaction\Refund;

if (!class_exists('WC_TillPayments_V1_10_5_CreditCard')) {
    class WC_TillPayments_V1_10_5_CreditCard extends WC_Payment_Gateway
    {
    public $id = 'creditcard';

    // Store the original gateway ID to read settings from the original plugin
    protected $original_gateway_id = 'till_payments_creditcard';

    public $method_title = 'Credit Card';

    /**
     * @var false|WP_User
     */
    protected $user;

    /**
     * @var WC_Order
     */
    protected $order;

    /**
     * @var string
     */
    protected $callbackUrl;

    /**
     * @var null|WC_Logger
     */
    protected $logger;

    protected $loggerContext = ['source' => 'TillPayments_CreditCard'];

    public function __construct()
    {
        $this->logger = wc_get_logger();

        $this->id = TILL_PAYMENTS_V1_10_5_EXTENSION_UID_PREFIX . $this->id;
        $this->method_description = TILL_PAYMENTS_V1_10_5_EXTENSION_NAME . ' ' . $this->method_title . ' payments.';
		$this->icon = 'https://whitehenry.com.au/wp-content/uploads/2024/03/cards_icons2.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->supports = array(
            'products',
            'refunds'
        );

        // Show payment fields on checkout (seamless form)
        $this->has_fields = true;

        $this->title = $this->get_option('title');
        $this->callbackUrl = add_query_arg('wc-api', 'wc_' . $this->id, home_url('/'));


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Enqueue PaymentJs library
        add_action('wp_enqueue_scripts', function () {
            wp_register_script('payment_js_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, $this->get_option('apiHost') . 'js/integrated/payment.1.3.min.js', [], TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION, false);
            if (is_checkout() || is_checkout_pay_page()) {
                wp_enqueue_script('payment_js_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID);
            }
        }, 10);

        // Inject initialization directly into footer on checkout pages
        add_action('wp_footer', function () {
            if (!(is_checkout() || is_checkout_pay_page())) {
                return;
            }
            ?>
            <script>
            (function () {
                console.log('✓ Till Payments footer initialization starting');

                // Wait for jQuery to be available
                var checkJQuery = setInterval(function() {
                    if (typeof jQuery !== 'undefined') {
                        clearInterval(checkJQuery);
                        console.log('✓ jQuery available, initializing');
                        initTillPayments();
                    }
                }, 50);

                var initTillPayments = function() {
                    var $ = jQuery;
                    console.log('✓ Till Payments init function executing');

                    // Set and check integration key directly
                    var integrationKey = '<?php echo TILL_PAYMENTS_V1_10_5_INTEGRATION_KEY; ?>';
                    console.log('✓ Integration key:', integrationKey ? 'FOUND' : 'MISSING');

                    // Wait for PaymentJs library
                    var paymentJsRetry = 0;
                    var waitForPaymentJs = setInterval(function() {
                        if (typeof PaymentJs !== 'undefined') {
                            clearInterval(waitForPaymentJs);
                            console.log('✓ PaymentJs loaded');
                            initializeForm();
                        } else if (paymentJsRetry > 100) {
                            clearInterval(waitForPaymentJs);
                            console.error('✗ PaymentJs failed to load');
                        }
                        paymentJsRetry++;
                    }, 100);

                    var initializeForm = function() {
                        var $form = $('#till_payments_seamless');
                        var $cardNumber = $('#till_payments_seamless_card_number');
                        var $cvv = $('#till_payments_seamless_cvv');
                        var $cardHolder = $('#till_payments_seamless_card_holder');
                        var $expiry = $('#till_payments_seamless_expiry');
                        var $token = $('#till_payments_token');
                        var $errors = $('#till_payments_errors');
                        var $submitBtn = $("#place_order");

                        console.log('✓ Form elements found:', {
                            form: $form.length,
                            cardNumber: $cardNumber.length,
                            cvv: $cvv.length
                        });

                        if ($form.length === 0) {
                            console.error('✗ Form not found in DOM');
                            console.log('Searching for any element with id containing "seamless":');
                            $('[id*="seamless"]').each(function() {
                                console.log('  Found:', this.id, 'parent:', this.parentElement.className);
                            });
                            return;
                        }

                        // Log form details
                        console.log('✓ Form details (BEFORE sizing):');
                        console.log('  - Form width:', $form.width());
                        console.log('  - Form height:', $form.height());

                        // Set dimensions - form will size to content naturally
                        $form.css({
                            'min-width': '100%',
                            'width': '100%',
                            'min-height': 'auto',
                            'height': 'auto',
                            'display': 'block !important',
                            'visibility': 'visible !important',
                            'opacity': '1 !important',
                            'position': 'relative',
                            'left': 'auto',
                            'top': 'auto',
                            'z-index': '9999'
                        });

                        // Ensure parent payment_box is visible and properly positioned
                        var $paymentBox = $form.closest('.payment_box');
                        $paymentBox.css({
                            'min-width': '100%',
                            'width': '100%',
                            'display': 'block !important',
                            'visibility': 'visible !important',
                            'opacity': '1 !important',
                            'position': 'relative',
                            'left': 'auto',
                            'top': 'auto',
                            'z-index': '9998',
                            'min-height': 'auto'
                        });

                        // Ensure parent LI is visible
                        var $li = $paymentBox.closest('li');
                        $li.css({
                            'display': 'block !important',
                            'visibility': 'visible !important',
                            'opacity': '1 !important',
                            'position': 'relative',
                            'min-height': 'auto'
                        });

                        console.log('✓ Form details (AFTER sizing):');
                        console.log('  - Form width:', $form.width());
                        console.log('  - Form height:', $form.height());
                        console.log('  - Parent class:', $form.parent().attr('class'));

                        // Initialize PaymentJs
                        var payment = new PaymentJs('1.3');
                        console.log('✓ PaymentJs instance created');

                        var style = {
                            'border': $cardHolder.css('border'),
                            'border-radius': $cardHolder.css('border-radius'),
                            'height': $cardHolder.css('height'),
                            'padding': $cardHolder.css('padding'),
                            'font-size': $cardHolder.css('font-size'),
                            'font-weight': $cardHolder.css('font-weight'),
                            'font-family': $cardHolder.css('font-family'),
                            'color': $cardHolder.css('color'),
                            'background': $cardHolder.css('background'),
                        };

                        payment.init(integrationKey, $cardNumber.prop('id'), $cvv.prop('id'), function(p) {
                            console.log('✓ PaymentJs initialized');

                            // IMMEDIATELY hide all loaders
                            $('#loader').hide();
                            $('.payment_box #loader').hide();
                            $('[id*="loader"]').hide();
                            console.log('✓ Loader hidden');

                            // Show form
                            $form.show();
                            $form.css('display', 'block');
                            console.log('✓ Form shown, display:', $form.css('display'));

                            // Set styles
                            payment.setNumberStyle(style);
                            payment.setCvvStyle(style);

                            // Setup validation
                            var validNumber = false;
                            var validCvv = false;

                            payment.numberOn('input', function(data) {
                                validNumber = data.validNumber;
                                console.log('Card valid:', validNumber);
                            });

                            payment.cvvOn('input', function(data) {
                                validCvv = data.validCvv;
                                console.log('CVV valid:', validCvv);
                            });

                            // Handle submit
                            $submitBtn.on('click', function(e) {
                                if ($token.val()) {
                                    return true; // Token already set, proceed
                                }

                                e.preventDefault();
                                console.log('Processing payment...');

                                var expiryData = $expiry.val().split('/');
                                payment.tokenize({
                                    card_holder: $cardHolder.val(),
                                    month: expiryData[0],
                                    year: expiryData[1],
                                    email: $('#billing_email').val()
                                },
                                function(token) {
                                    console.log('✓ Token received');
                                    $token.val(token);
                                    $form.closest('form').submit();
                                },
                                function(errors) {
                                    console.error('Payment errors:', errors);
                                    $errors.html(errors.map(e => e.message).join('<br>'));
                                });

                                return false;
                            });

                            console.log('✓ Form ready for payment');

                            // AGGRESSIVE: Monitor for form being hidden and re-force visibility
                            var reforceVisibility = setInterval(function() {
                                var display = $form.css('display');
                                var visibility = $form.css('visibility');
                                var opacity = $form.css('opacity');

                                if (display === 'none' || visibility === 'hidden' || opacity === '0') {
                                    console.warn('✗ FORM WAS HIDDEN! Re-forcing visibility');
                                    $form.css({
                                        'display': 'block !important',
                                        'visibility': 'visible !important',
                                        'opacity': '1 !important'
                                    });
                                    $paymentBox.css({
                                        'display': 'block !important',
                                        'visibility': 'visible !important',
                                        'opacity': '1 !important'
                                    });
                                    $li.css({
                                        'display': 'block !important',
                                        'visibility': 'visible !important',
                                        'opacity': '1 !important'
                                    });
                                }
                            }, 50);
                        });
                    };

                    // Listen for WooCommerce payment method changes
                    // When user deselects and reselects this payment method, reinitialize
                    $(document).on('payment_method_selected', function() {
                        console.log('✓ Payment method change detected');

                        // Check if our payment method is selected
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        console.log('  - Selected method:', selectedMethod);

                        if (selectedMethod === 'till_payments_v1_10_5_creditcard') {
                            console.log('✓ Till Payments v1.10.5 Credit Card selected - checking form state');

                            setTimeout(function() {
                                // Recheck if form elements exist (DOM may have been recreated)
                                var $newForm = $('#till_payments_seamless');
                                if ($newForm.length > 0) {
                                    console.log('✓ Form found after payment method change, reinitializing');
                                    // Reinitialize - call initializeForm again
                                    initializeForm();
                                } else {
                                    console.warn('✗ Form not found after payment method change');
                                }
                            }, 100);
                        }
                    });

                    // Also listen for the general checkout_updated event
                    $(document).on('updated_checkout', function() {
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        if (selectedMethod === 'till_payments_v1_10_5_creditcard') {
                            console.log('✓ Checkout updated, Till Payments method is selected');

                            setTimeout(function() {
                                var $form = $('#till_payments_seamless');
                                if ($form.length > 0 && !$form.data('till-payments-initialized')) {
                                    console.log('✓ Form found and not yet reinitialized - reinitializing');
                                    initializeForm();
                                }
                            }, 100);
                        }
                    });
                };
            })();
            </script>
            <?php
        }, 999);
        add_action('woocommerce_api_wc_' . $this->id, [$this, 'process_callback']);
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle !== 'payment_js_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID) {
                return $tag;
            }
            return str_replace(' src', ' data-main="payment-js" src', $tag);
        }, 10, 2);
        
            add_action(
                'woocommerce_order_item_add_action_buttons',
                function(WC_Order $order) {
                    if ($order->get_meta('pending_capture_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID) === 'yes' && $order->get_payment_method() === $this->id) {
                        echo sprintf(
                            '<button
                            id="tillpayments_capture_payment_%s"
                            type="button"
                            class="button capture-payment"
                            data-order-id="%s"
                            data-version-id="%s"
                            data-payment-method="%s">Capture Payment</button>',
                            esc_attr(TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID),
                            esc_attr($order->get_id()),
                            esc_attr(TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID),
                            esc_attr($this->id)
                            );
                    }
                }

                );
            
        add_filter('woocommerce_available_payment_gateways', [$this, 'hide_payment_gateways_on_pay_for_order_page'], 100, 1);
        add_filter('woocommerce_gateway_description', [$this, 'updateDescription'], 5, 1);     
    }
    public function log(string $msg, string $level = WC_Log_Levels::DEBUG, string $source_suffix = null)
    {
        $context = $this->loggerContext;

        if (is_string($source_suffix)) {
            $context['source'] .= '_'.trim($source_suffix);
        }

        $this->logger->log($level, $msg, $context);
    }

    /**
     * Override get_option to read from the v1.10.5 settings if they exist,
     * otherwise fall back to the original gateway's settings for backwards compatibility
     */
    public function get_option($key, $empty_value = null)
    {
        // Special handling for 'enabled' - read from our own settings so enable/disable works
        if ($key === 'enabled') {
            return parent::get_option($key, $empty_value);
        }

        // First, try to read from v1.10.5 settings (after migration)
        $v1_10_5_option_key = 'woocommerce_' . $this->id . '_settings';
        $v1_10_5_settings = get_option($v1_10_5_option_key);

        if (is_array($v1_10_5_settings) && isset($v1_10_5_settings[$key])) {
            return $v1_10_5_settings[$key];
        }

        // Fall back to original gateway settings for backwards compatibility
        // (if migration hasn't happened yet or original plugin is still active)
        $original_option_key = 'woocommerce_' . $this->original_gateway_id . '_settings';
        $original_settings = get_option($original_option_key);

        if (is_array($original_settings) && isset($original_settings[$key])) {
            return $original_settings[$key];
        }

        return $empty_value;
    }

    /**
     * Override process_admin_options to save all settings to the v1.10.5 gateway
     * This makes the v1.10.5 plugin fully independent from the original
     */
    public function process_admin_options()
    {
        // Get current v1.10.5 settings
        $v1_10_5_option_key = 'woocommerce_' . $this->id . '_settings';
        $settings = get_option($v1_10_5_option_key, []);

        // Settings to save
        $settings_to_save = [
            'title',
            'apiHost',
            'apiUser',
            'apiPassword',
            'apiKey',
            'sharedSecret',
            'transactionRequest'
        ];

        // Update settings from POST data
        foreach ($settings_to_save as $setting_key) {
            $post_key = 'woocommerce_' . $this->id . '_' . $setting_key;
            if (isset($_POST[$post_key])) {
                $settings[$setting_key] = sanitize_text_field($_POST[$post_key]);
            }
        }

        // Save all settings to v1.10.5 gateway
        update_option($v1_10_5_option_key, $settings);

        // Handle enabled/disabled toggle
        if (isset($_POST['woocommerce_' . $this->id . '_enabled'])) {
            update_option('woocommerce_' . $this->id . '_enabled', 'yes');
        } else {
            update_option('woocommerce_' . $this->id . '_enabled', 'no');
        }

        WC_Admin_Settings::add_message(__('Settings saved successfully.', 'woocommerce'));

        return false;
    }

    public function hide_payment_gateways_on_pay_for_order_page($available_gateways)
    {
        if (is_checkout_pay_page()) {
            global $wp;
            $this->order = new WC_Order($wp->query_vars['order-pay']);
            foreach ($available_gateways as $gateways_id => $gateways) {
                if ($gateways_id !== $this->order->get_payment_method()) {
                    unset($available_gateways[$gateways_id]);
                }
            }
        }

        return $available_gateways;
    }

    /**
     * Field size limits for Till Payments gateway (standard payment gateway constraints)
     * These are applied to prevent gateway validation errors
     *
     * @var array
     */
    private $fieldSizeLimits = [
        'firstName' => 50,
        'lastName' => 50,
        'company' => 100,
        'email' => 255,
        'billingAddress1' => 100,
        'billingAddress2' => 100,
        'billingCity' => 50,
        'billingState' => 50,
        'billingCountry' => 2,
        'billingPostcode' => 20,
        'billingPhone' => 20,
        'shippingFirstName' => 50,
        'shippingLastName' => 50,
        'shippingCompany' => 100,
        'shippingAddress1' => 100,
        'shippingAddress2' => 100,
        'shippingCity' => 50,
        'shippingState' => 50,
        'shippingCountry' => 2,
        'shippingPostcode' => 20,
        'shippingPhone' => 20,
    ];

    /**
     * Sanitize and truncate customer data fields to meet gateway constraints
     * Logs warnings when fields are truncated
     *
     * @param string $fieldName Field identifier (e.g., 'firstName', 'billingAddress1')
     * @param string $value Field value from order
     * @return string Truncated field value or original if within limits
     */
    private function sanitizeField($fieldName, $value)
    {
        if (empty($value)) {
            return $value;
        }

        $value = trim($value);
        if (!isset($this->fieldSizeLimits[$fieldName])) {
            return $value;
        }

        $maxLength = $this->fieldSizeLimits[$fieldName];
        $currentLength = strlen($value);

        if ($currentLength > $maxLength) {
            $truncatedValue = substr($value, 0, $maxLength);
            $this->log(
                sprintf(
                    'Field "%s" truncated from %d to %d characters: "%s" → "%s"',
                    $fieldName,
                    $currentLength,
                    $maxLength,
                    $value,
                    $truncatedValue
                ),
                WC_Log_Levels::WARNING,
                'FieldTruncation'
            );
            return $truncatedValue;
        }

        return $value;
    }

    private function encodeOrderId($orderId)
    {
        return $orderId . '-' . date('YmdHis') . substr(sha1(uniqid()), 0, 10);
    }

    private function encodeRefundId($orderId)
    {
        return $orderId . '-refund-' . date('YmdHis') . substr(sha1(uniqid()), 0, 10);
    }

    private function decodeOrderId($orderId)
    {
        if (strpos($orderId, '-') === false) {
            return $orderId;
        }

        $orderIdParts = explode('-', $orderId);

        if(count($orderIdParts) === 2) {
            $orderId = $orderIdParts[0];
        }

        /**
         * void/capture will prefix the transaction id
         */
        if(count($orderIdParts) === 3) {
            $orderId = $orderIdParts[1];
        }

        return $orderId;
    }

    /**
     * Save a vault token securely for a user (only for logged-in users)
     * Stores: vault token, last 4 digits, card brand, expiry date
     */
    private function saveCardToken($userId, $vaultToken, $cardDetails = [])
    {
        if (!$userId || !is_user_logged_in()) {
            return false;
        }

        // Get existing saved cards
        $savedCards = get_user_meta($userId, 'till_payments_v1_10_5_saved_cards', true);
        if (!is_array($savedCards)) {
            $savedCards = [];
        }

        // Create card record with hashed token for security
        $cardId = wp_generate_password(16, false);
        $savedCards[$cardId] = [
            'token' => wp_hash_password($vaultToken), // Hash the token
            'token_plain' => $vaultToken, // Store plain for now (consider encryption in production)
            'last_4' => isset($cardDetails['last_4']) ? sanitize_text_field($cardDetails['last_4']) : '****',
            'brand' => isset($cardDetails['brand']) ? sanitize_text_field($cardDetails['brand']) : 'Card',
            'expiry' => isset($cardDetails['expiry']) ? sanitize_text_field($cardDetails['expiry']) : '',
            'saved_date' => current_time('mysql'),
        ];

        // Save updated cards
        update_user_meta($userId, 'till_payments_v1_10_5_saved_cards', $savedCards);
        $this->log('Card token saved for user ' . $userId . ': ' . $cardId);

        return $cardId;
    }

    /**
     * Get all saved cards for the current user
     */
    private function getSavedCards($userId = null)
    {
        if (!$userId && is_user_logged_in()) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        $savedCards = get_user_meta($userId, 'till_payments_v1_10_5_saved_cards', true);
        return is_array($savedCards) ? $savedCards : [];
    }

    /**
     * Delete a saved card for a user
     */
    public function deleteSavedCard($cardId)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return false;
        }

        $savedCards = $this->getSavedCards($userId);
        if (isset($savedCards[$cardId])) {
            unset($savedCards[$cardId]);
            update_user_meta($userId, 'till_payments_v1_10_5_saved_cards', $savedCards);
            $this->log('Saved card deleted: ' . $cardId . ' for user ' . $userId);
            return true;
        }

        return false;
    }

    public function process_payment($orderId)
    {
        $this->log('Processing new Creditcard payment...');

        global $woocommerce;

        /**
         * order & user
         */
        $this->order = new WC_Order($orderId);
        $this->order->update_status('pending', __('Awaiting payment', 'woocommerce'));
        $this->user = $this->order->get_user();

        /**
         * gateway client
         */
        WC_TillPayments_V1_10_5_Provider::autoloadClient();
        TillPayments\Client\Client::setApiUrl($this->get_option('apiHost'));
        $client = new TillPayments\Client\Client(
            $this->get_option('apiUser'),
            htmlspecialchars_decode($this->get_option('apiPassword')),
            $this->get_option('apiKey'),
            $this->get_option('sharedSecret')
        );

        /**
         * gateway customer (with field size sanitization)
         */
        $customer = new TillPayments\Client\Data\Customer();
        $customer
            ->setBillingAddress1($this->sanitizeField('billingAddress1', $this->order->get_billing_address_1()))
            ->setBillingAddress2($this->sanitizeField('billingAddress2', $this->order->get_billing_address_2()))
            ->setBillingCity($this->sanitizeField('billingCity', $this->order->get_billing_city()))
            ->setBillingCountry($this->sanitizeField('billingCountry', $this->order->get_billing_country()))
            ->setBillingPhone($this->sanitizeField('billingPhone', $this->order->get_billing_phone()))
            ->setBillingPostcode($this->sanitizeField('billingPostcode', $this->order->get_billing_postcode()))
            ->setBillingState($this->sanitizeField('billingState', $this->order->get_billing_state()))
            ->setCompany($this->sanitizeField('company', $this->order->get_billing_company()))
            ->setEmail($this->sanitizeField('email', $this->order->get_billing_email()))
            ->setFirstName($this->sanitizeField('firstName', $this->order->get_billing_first_name()))
            ->setIpAddress(WC_Geolocation::get_ip_address()) // $this->order->get_customer_ip_address()
            ->setLastName($this->sanitizeField('lastName', $this->order->get_billing_last_name()));

        /**
         * add shipping data for non-digital goods (with field size sanitization)
         */
        if ($this->order->get_shipping_country()) {
            $customer
                ->setShippingAddress1($this->sanitizeField('shippingAddress1', $this->order->get_shipping_address_1()))
                ->setShippingAddress2($this->sanitizeField('shippingAddress2', $this->order->get_shipping_address_2()))
                ->setShippingCity($this->sanitizeField('shippingCity', $this->order->get_shipping_city()))
                ->setShippingCompany($this->sanitizeField('shippingCompany', $this->order->get_shipping_company()))
                ->setShippingCountry($this->sanitizeField('shippingCountry', $this->order->get_shipping_country()))
                ->setShippingFirstName($this->sanitizeField('shippingFirstName', $this->order->get_shipping_first_name()))
                ->setShippingLastName($this->sanitizeField('shippingLastName', $this->order->get_shipping_last_name()))
                ->setShippingPostcode($this->sanitizeField('shippingPostcode', $this->order->get_shipping_postcode()))
                ->setShippingState($this->sanitizeField('shippingState', $this->order->get_shipping_state()));
        }

        /**
         * transaction
         */
        $transactionRequest = $this->get_option('transactionRequest');
        $transaction = null;
        switch ($transactionRequest) {
            case 'preauthorize':
                $transaction = new \TillPayments\Client\Transaction\Preauthorize();
                break;
            case 'debit':
            default:
                $transaction = new \TillPayments\Client\Transaction\Debit();
                break;
        }

        $orderTxId = $this->encodeOrderId($orderId);
        // keep track of last tx id
        $this->order->add_meta_data('orderTxId_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, $orderTxId, true); 
        $this->order->save_meta_data();
        $transaction->setTransactionId($orderTxId)
            ->setAmount(floatval($this->order->get_total()))
            ->setCurrency($this->order->get_currency())
            ->setCustomer($customer)
            ->setExtraData($this->extraData3DS())
            ->setCallbackUrl($this->callbackUrl)
            ->setCancelUrl(wc_get_checkout_url())
            ->setSuccessUrl($this->paymentSuccessUrl($this->order))
            ->setErrorUrl(add_query_arg(['gateway_return_result' => 'error'], TILL_PAYMENTS_V1_10_5_INTEGRATION_KEY ? $this->order->get_checkout_payment_url(false) : wc_get_checkout_url()));
        
        /**
         * integration key is set -> seamless
         * proceed to pay now page or apply submitted transaction token
         */
        if (TILL_PAYMENTS_V1_10_5_INTEGRATION_KEY) {
            $token = !empty($this->get_post_data()['token']) ? $this->get_post_data()['token'] : null;
            if (!$token) {
                return [
                    'result' => 'success',
                    'redirect' => $this->order->get_checkout_payment_url(false),
                ];
            }
            $transaction->setTransactionToken($token);
        }

        $this->log('  > created TillPayments transaction object. orderId: '.$orderId.', orderTxId: '. $orderTxId);

        /**
         * transaction
         */
        switch ($transactionRequest) {
            case 'preauthorize':
                $this->log('  > sending preauthorize transaction request...');
                $result = $client->preauthorize($transaction);
                break;
            case 'debit':
            default:
                $this->log('  > sending debit transaction request...');
                $result = $client->debit($transaction);
                break;
        }

        if ($result->isSuccess()) {
            $this->log('  > request successful');
            // $gatewayReferenceId = $result->getReferenceId();
            if ($result->getReturnType() == TillPayments\Client\Transaction\Result::RETURN_TYPE_ERROR) {
                $errors = $result->getErrors();
                $this->log('  > return type: ERROR', WC_Log_Levels::ERROR);
                $this->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);
                return $this->paymentFailedResponse();
            } elseif ($result->getReturnType() == TillPayments\Client\Transaction\Result::RETURN_TYPE_REDIRECT) {
                $this->log('  > return type: REDIRECT');
                $this->order->add_meta_data('paymentUuid_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, $result->getReferenceId(), true);
                $this->order->save_meta_data();

                $this->log('  > redirect URL: '.$result->getRedirectUrl());
                /**
                 * hosted payment page or seamless+3DS
                 */

                if ($transactionRequest === 'preauthorize') {
                    $this->order->add_meta_data('pending_capture_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, 'yes', true);
                    $this->order->save_meta_data();
                }
                return [
                    'result' => 'success',
                    'redirect' => $result->getRedirectUrl(),
                ];
            } elseif ($result->getReturnType() == TillPayments\Client\Transaction\Result::RETURN_TYPE_PENDING) {
                /**
                 * payment is pending, wait for callback to complete
                 */
                $this->log('  > return type: PENDING');
            } elseif ($result->getReturnType() == TillPayments\Client\Transaction\Result::RETURN_TYPE_FINISHED) {
                /**
                 * seamless will finish here ONLY FOR NON-3DS SEAMLESS
                 */
                $this->order->add_meta_data('paymentUuid_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, $result->getReferenceId(), true);
                $this->order->save_meta_data();

                // Save card if user requested it
                if (is_user_logged_in() && !empty($_POST['till_payments_save_card'])) {
                    $userId = get_current_user_id();
                    $vaultToken = $result->getReferenceId();

                    // Extract card details from order
                    $cardDetails = [
                        'last_4' => substr($this->get_post_data()['card_number'] ?? '', -4),
                        'brand' => 'Credit Card',
                        'expiry' => $this->get_post_data()['expiry'] ?? '',
                    ];

                    $savedCardId = $this->saveCardToken($userId, $vaultToken, $cardDetails);
                    if ($savedCardId) {
                        $this->order->add_order_note('Card saved for future purchases', false);
                    }
                }

                switch ($transactionRequest) {
                    case 'preauthorize':
                        $this->order->add_order_note('TillPayments authorization ID: '.$result->getReferenceId(), false);
                        $this->order->update_status('on-hold', 'Payment authorized. Awaiting capture.');
                        break;
                    case 'debit':
                    default:
                        $this->order->payment_complete($result->getPurchaseId());
                        $this->order->add_order_note('TillPayments purchase ID: '.$result->getPurchaseId(), false);
                        break;
                }

                $this->order->save();
                $this->log('  > return type: FINISHED');
                $this->log('  > result data: '.print_r($result->toArray(), true));
            }

            if ($transactionRequest === 'preauthorize') {
                $this->order->add_meta_data('pending_capture_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID, 'yes', true);
                $this->order->save_meta_data();
            }

            $woocommerce->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->paymentSuccessUrl($this->order),
            ];
        } else {
            $errors = $result->getErrors();
            $this->log('  > request failed', WC_Log_Levels::ERROR);
            $this->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);
        }

        /**
         * something went wrong
         */
        $this->log('  > fallback return point reached. something went wrong?', WC_Log_Levels::ERROR);
        return $this->paymentFailedResponse();
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $this->log('Processing new refund...');

        /**
         * order & user
         */
        $this->order = new WC_Order($order_id);
        $this->user = $this->order->get_user();

        /**
         * gateway client
         */
        WC_TillPayments_V1_10_5_Provider::autoloadClient();
        TillPayments\Client\Client::setApiUrl($this->get_option('apiHost'));
        $client = new TillPayments\Client\Client(
            $this->get_option('apiUser'),
            htmlspecialchars_decode($this->get_option('apiPassword')),
            $this->get_option('apiKey'),
            $this->get_option('sharedSecret')
        );

        /**
         * transaction
         */
        $transaction = new Refund();
        $refundTxId = $this->encodeRefundId($order_id);
        $transaction->setTransactionId($refundTxId)
            ->setAmount(floatval($amount))
            ->setCurrency($this->order->get_currency())
            ->setReferenceTransactionId($this->order->get_meta('paymentUuid_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID))
            ->setCallbackUrl($this->callbackUrl);

        /**
         * transaction
         */
        $result = $client->refund($transaction);

        if ($result->isSuccess()) {
            switch ($result->getReturnType()) {
                case TillPayments\Client\Transaction\Result::RETURN_TYPE_ERROR:
                    $errors = $result->getErrors();
                    $this->log('  > return type: ERROR', WC_Log_Levels::ERROR);
                    $this->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);

                    if (empty($errors)) {
                        return false;
                    }

                    $errorMsg = '';
                    foreach ($errors as $error) {
                        $errorMsg .= $error->getMessage() . PHP_EOL;
                    }

                    return new WP_Error('error', $errorMsg);
                case TillPayments\Client\Transaction\Result::RETURN_TYPE_PENDING:
                    $this->log('  > return type: PENDING');
                    $this->log('  > result data: '.print_r($result->toArray(), true));
                    break;
                case TillPayments\Client\Transaction\Result::RETURN_TYPE_FINISHED:
                    $this->log('  > return type: FINISHED');
                    $this->order->add_order_note('TillPayments refund ID: ' . $result->getReferenceId(), false);
                    $this->log('  > result data: '.print_r($result->toArray(), true));

                    return true;
            }
        } else {
            $errors = $result->getErrors();

            if (empty($errors)) {
                return false;
            }

            $this->log('  > request failed', WC_Log_Levels::ERROR);
            $this->log('  > errors: '.print_r($errors, true), WC_Log_Levels::ERROR);

            $errorMsg = '';
            foreach ($errors as $error) {
                $errorMsg .= $error->getMessage().PHP_EOL;
            }

            return new WP_Error('error', $errorMsg);
        }

        /**
         * something went wrong
         */
        $this->log('  > fallback return point reached. something went wrong?', WC_Log_Levels::ERROR);
        return false;
    }


    private function paymentSuccessUrl($order)
    {
        $url = $this->get_return_url($order);

        return $url . '&empty-cart';
    }

    private function paymentFailedResponse()
    {
        $this->order->update_status('failed', __('Payment failed or was declined', 'woocommerce'));
        $this->order->add_order_note('TillPayments payment failed or was declined', false);
        $this->order->save();
        wc_add_notice(__('Payment failed or was declined', 'woocommerce'), 'error');
        return [
            'result' => 'error',
            'redirect' => $this->get_return_url($this->order),
        ];
    }

    public function process_callback()
    {
        WC_TillPayments_V1_10_5_Provider::autoloadClient();

        TillPayments\Client\Client::setApiUrl($this->get_option('apiHost'));
        $client = new TillPayments\Client\Client(
            $this->get_option('apiUser'),
            htmlspecialchars_decode($this->get_option('apiPassword')),
            $this->get_option('apiKey'),
            $this->get_option('sharedSecret')
        );

        if (!$client->validateCallbackWithGlobals()) {
            if (!headers_sent()) {
                http_response_code(400);
            }
            die("OK");
        }

        $callbackResult = $client->readCallback(file_get_contents('php://input'));
        $this->order = new WC_Order($this->decodeOrderId($callbackResult->getTransactionId()));

        // check if callback data is coming from the last (=newest+relevant) tx attempt, otherwise ignore it
        if ($this->order->get_meta('orderTxId_' . TILL_PAYMENTS_V1_10_5_EXTENSION_VERSION_ID) !== $callbackResult->getTransactionId()) {
            die("OK");
        }
        
        if ($callbackResult->getResult() == \TillPayments\Client\Callback\Result::RESULT_OK) {
            switch ($callbackResult->getTransactionType()) {
                case \TillPayments\Client\Callback\Result::TYPE_DEBIT:
                case \TillPayments\Client\Callback\Result::TYPE_CAPTURE:
                    $this->order->payment_complete($callbackResult->getReferenceId());
                    $this->order->add_order_note('TillPayments callback processed: ' . $callbackResult->getReferenceId(), false);
                    break;
                case \TillPayments\Client\Callback\Result::TYPE_VOID:
                    $this->order->update_status('cancelled', __('Void', 'woocommerce'));
                    $this->order->add_order_note('TillPayments void processed', false);
                    break;
                case \TillPayments\Client\Callback\Result::TYPE_PREAUTHORIZE:
                    $this->order->update_status('on-hold', __('Awaiting capture/void', 'woocommerce'));
                    $this->order->add_order_note('TillPayments preauthorization completed', false);
                    break;
            }
        } elseif ($callbackResult->getResult() == \TillPayments\Client\Callback\Result::RESULT_ERROR) {
            switch ($callbackResult->getTransactionType()) {
                case \TillPayments\Client\Callback\Result::TYPE_DEBIT:
                case \TillPayments\Client\Callback\Result::TYPE_CAPTURE:
                case \TillPayments\Client\Callback\Result::TYPE_VOID:
                    $this->order->update_status('failed', __('Error', 'woocommerce'));
                    $this->order->add_order_note('TillPayments callback error: Payment processing failed', false);
                    break;
            }
        }

        // Explicitly save order to ensure all changes persist
        $this->order->save();

        die("OK");
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'label' => 'Title',
                'description' => 'Title',
                'default' => $this->method_title,
            ],
            'apiHost' => [
                'title' => 'API Host',
                'type' => 'select',
                'label' => 'Environment',
                'description' => 'Environment',
                'default' => TILL_PAYMENTS_V1_10_5_EXTENSION_URL,
                'options' => [
                    TILL_PAYMENTS_V1_10_5_EXTENSION_URL_TEST => 'Test (Sandbox)',
                    TILL_PAYMENTS_V1_10_5_EXTENSION_URL => 'Live (Production)'
                ],
            ],
            'apiUser' => [
                'title' => 'API User',
                'type' => 'text',
                'label' => 'API User',
                'description' => 'API User',
                'default' => '',
            ],
            'apiPassword' => [
                'title' => 'API Password',
                'type' => 'password',
                'label' => 'API Password',
                'description' => 'API Password',
                'default' => '',
            ],
            'apiKey' => [
                'title' => 'API Key',
                'type' => 'password',
                'label' => 'API Key',
                'description' => 'API Key',
                'default' => '',
            ],
            'sharedSecret' => [
                'title' => 'Shared Secret',
                'type' => 'password',
                'label' => 'Shared Secret',
                'description' => 'Shared Secret',
                'default' => '',
            ],
            // Integration Key is hard-coded for this instance
            'transactionRequest' => [
                'title' => 'Transaction Request',
                'type' => 'select',
                'label' => 'Transaction Request',
                'description' => 'Transaction Request',
                'default' => 'debit',
                'options' => [
                    'debit' => 'Debit',
                    'preauthorize' => 'Preauthorize/Capture',
                ],
            ],
        ];
    }

    public function payment_fields()
    {
        echo '
        <style>.payment_box iframe { width: 100%!important } #till_payments_errors{color: red; } 
        #loader {
          position: absolute;  
          left: 50%;
          top: 50%;
          border: 5px dotted #808080;
          border-radius: 50%;
          border-top: 5px dotted #FFFFFF;
          width: 40px;
          height: 40px;
          -webkit-animation: spin 2s linear infinite; /* Safari */
          animation: spin 1s linear infinite;
        }
        
        /* Safari */
        @-webkit-keyframes spin {
          0% { -webkit-transform: rotate(0deg); }
          100% { -webkit-transform: rotate(360deg); }
        }

        .payment_box::before {
			border: 0px !important;
		}
        
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        </style>
        <div id="till_payments_errors"></div>
        <div id = "loader"></div>
        <div id="till_payments_seamless" style="display: block !important; visibility: visible !important; width: 100%;">
            <input type="hidden" id="till_payments_token" name="token">
            <p class="form-row form-row-wide" style="height: 80px; display: block !important;">
                <label for="till_payments_seamless_card_number">Card Number&nbsp;<abbr class="required" title="required">*</abbr></label>
                <span class="woocommerce-input-wrapper" style="display: block !important; width: 100%;">
                    <span id="till_payments_seamless_card_number" class="input-text" style="padding: 0; width: 100% !important; height: 52px !important; border-radius: 3px; display: block !important; visibility: visible !important;"></span>
                </span>
            </p>

            <p class="form-row form-row-wide" style="height: 80px; display: block !important;">
                <label for="till_payments_seamless_card_holder">Cardholder Name&nbsp;<abbr class="required" title="required">*</abbr></label>
                <span class="woocommerce-input-wrapper" style="display: block !important; width: 100%;">
                    <input type="text" class="input-text" id="till_payments_seamless_card_holder" style="border-radius: 3px; width: 100% !important; height: 52px !important; padding: 8px !important; box-sizing: border-box !important;">
                </span>
            </p>

            <p class="form-row form-row-first" style="height: 80px; display: block !important; width: 48%; float: left; margin-right: 2%;">
                <label for="till_payments_seamless_expiry">Expiration Date&nbsp;<abbr class="required" title="required">*</abbr></label>
                <span class="woocommerce-input-wrapper" style="display: block !important;">
                    <input type="text" class="input-text" id="till_payments_seamless_expiry" maxlength="5" placeholder="MM/YY" style="border-radius: 3px; width: 100% !important; height: 52px !important; padding: 8px !important; box-sizing: border-box !important;">
                </span>
            </p>
            <p class="form-row form-row-last" style="height: 80px; display: block !important; width: 50%; float: left;">
                <label for="till_payments_seamless_cvv">CVC/CVV Code&nbsp;<abbr class="required" title="required" style="color: #b22222; text-decoration: none;">*</abbr></label>
                <span class="woocommerce-input-wrapper" style="display: block !important;">
                    <span id="till_payments_seamless_cvv" style="padding: 0; height: 52px !important; width: 100% !important; border-radius: 3px; display: block !important; visibility: visible !important;"></span>
                </span>
            </p>
            <div style="clear: both;"></div>';

        // Show save card checkbox for logged-in users
        if (is_user_logged_in()) {
            echo '<p class="form-row form-row-wide" style="margin-top: 15px;">
                <input type="checkbox" id="till_payments_save_card" name="till_payments_save_card" value="yes" style="width: auto; margin-right: 8px;">
                <label for="till_payments_save_card" style="display: inline; font-weight: normal;">Save this card for future purchases</label>
            </p>';
        }

        echo '</div>';
    }

    /**
     * @throws Exception
     * @return array
     */
    private function extraData3DS()
    {
        $extraData = [
            /**
             * Browser 3ds data injected by payment.js
             */
            // 3ds:browserAcceptHeader
            // 3ds:browserIpAddress
            // 3ds:browserJavaEnabled
            // 3ds:browserLanguage
            // 3ds:browserColorDepth
            // 3ds:browserScreenHeight
            // 3ds:browserScreenWidth
            // 3ds:browserTimezone
            // 3ds:browserUserAgent

            /**
             * force 3ds flow
             */
            '3dsecure' => 'mandatory',

            /**
             * Additional 3ds 2.0 data
             */
            '3ds:addCardAttemptsDay' => $this->addCardAttemptsDay(),
            '3ds:authenticationIndicator' => $this->authenticationIndicator(),
            '3ds:billingAddressLine3' => $this->billingAddressLine3(),
            '3ds:billingShippingAddressMatch' => $this->billingShippingAddressMatch(),
            '3ds:browserChallengeWindowSize' => $this->browserChallengeWindowSize(),
            '3ds:cardholderAccountAgeIndicator' => $this->cardholderAccountAgeIndicator(),
            '3ds:cardHolderAccountChangeIndicator' => $this->cardHolderAccountChangeIndicator(),
            '3ds:cardholderAccountDate' => $this->cardholderAccountDate(),
            '3ds:cardholderAccountLastChange' => $this->cardholderAccountLastChange(),
            '3ds:cardholderAccountLastPasswordChange' => $this->cardholderAccountLastPasswordChange(),
            '3ds:cardholderAccountPasswordChangeIndicator' => $this->cardholderAccountPasswordChangeIndicator(),
            '3ds:cardholderAccountType' => $this->cardholderAccountType(),
            '3ds:cardHolderAuthenticationData' => $this->cardHolderAuthenticationData(),
            '3ds:cardholderAuthenticationDateTime' => $this->cardholderAuthenticationDateTime(),
            '3ds:cardholderAuthenticationMethod' => $this->cardholderAuthenticationMethod(),
            '3ds:challengeIndicator' => $this->challengeIndicator(),
            '3ds:channel' => $this->channel(),
            '3ds:deliveryEmailAddress' => $this->deliveryEmailAddress(),
            '3ds:deliveryTimeframe' => $this->deliveryTimeframe(),
            '3ds:giftCardAmount' => $this->giftCardAmount(),
            '3ds:giftCardCount' => $this->giftCardCount(),
            '3ds:giftCardCurrency' => $this->giftCardCurrency(),
            '3ds:homePhoneCountryPrefix' => $this->homePhoneCountryPrefix(),
            '3ds:homePhoneNumber' => $this->homePhoneNumber(),
            '3ds:mobilePhoneCountryPrefix' => $this->mobilePhoneCountryPrefix(),
            '3ds:mobilePhoneNumber' => $this->mobilePhoneNumber(),
            '3ds:paymentAccountAgeDate' => $this->paymentAccountAgeDate(),
            '3ds:paymentAccountAgeIndicator' => $this->paymentAccountAgeIndicator(),
            '3ds:preOrderDate' => $this->preOrderDate(),
            '3ds:preOrderPurchaseIndicator' => $this->preOrderPurchaseIndicator(),
            '3ds:priorAuthenticationData' => $this->priorAuthenticationData(),
            '3ds:priorAuthenticationDateTime' => $this->priorAuthenticationDateTime(),
            '3ds:priorAuthenticationMethod' => $this->priorAuthenticationMethod(),
            '3ds:priorReference' => $this->priorReference(),
            '3ds:purchaseCountSixMonths' => $this->purchaseCountSixMonths(),
            '3ds:purchaseDate' => $this->purchaseDate(),
            '3ds:purchaseInstalData' => $this->purchaseInstalData(),
            '3ds:recurringExpiry' => $this->recurringExpiry(),
            '3ds:recurringFrequency' => $this->recurringFrequency(),
            '3ds:reorderItemsIndicator' => $this->reorderItemsIndicator(),
            '3ds:shipIndicator' => $this->shipIndicator(),
            '3ds:shippingAddressFirstUsage' => $this->shippingAddressFirstUsage(),
            '3ds:shippingAddressLine3' => $this->shippingAddressLine3(),
            '3ds:shippingAddressUsageIndicator' => $this->shippingAddressUsageIndicator(),
            '3ds:shippingNameEqualIndicator' => $this->shippingNameEqualIndicator(),
            '3ds:suspiciousAccountActivityIndicator' => $this->suspiciousAccountActivityIndicator(),
            '3ds:transactionActivityDay' => $this->transactionActivityDay(),
            '3ds:transactionActivityYear' => $this->transactionActivityYear(),
            '3ds:transType' => $this->transType(),
            '3ds:workPhoneCountryPrefix' => $this->workPhoneCountryPrefix(),
            '3ds:workPhoneNumber' => $this->workPhoneNumber(),
        ];

        return array_filter($extraData, function ($data) {
            return $data !== null;
        });
    }

    /**
     * 3ds:addCardAttemptsDay
     * Number of Add Card attempts in the last 24 hours.
     *
     * @return int|null
     */
    private function addCardAttemptsDay()
    {
        return null;
    }

    /**
     * 3ds:authenticationIndicator
     * Indicates the type of Authentication request. This data element provides additional information to the ACS to determine the best approach for handling an authentication request.
     * 01 -> Payment transaction
     * 02 -> Recurring transaction
     * 03 -> Installment transaction
     * 04 -> Add card
     * 05 -> Maintain card
     * 06 -> Cardholder verification as part of EMV token ID&V
     *
     * @return string|null
     */
    private function authenticationIndicator()
    {
        return null;
    }

    /**
     * 3ds:billingAddressLine3
     * Line 3 of customer's billing address
     *
     * @return string|null
     */
    private function billingAddressLine3()
    {
        return null;
    }

    /**
     * 3ds:billingShippingAddressMatch
     * Indicates whether the Cardholder Shipping Address and Cardholder Billing Address are the same.
     * Y -> Shipping Address matches Billing Address
     * N -> Shipping Address does not match Billing Address
     *
     * @return string|null
     */
    private function billingShippingAddressMatch()
    {
        return null;
    }

    /**
     * 3ds:browserChallengeWindowSize
     * Dimensions of the challenge window that has been displayed to the Cardholder. The ACS shall reply with content that is formatted to appropriately render in this window to provide the best possible user experience.
     * 01 -> 250 x 400
     * 02 -> 390 x 400
     * 03 -> 500 x 600
     * 04 -> 600 x 400
     * 05 -> Full screen
     *
     * @return string|null
     */
    private function browserChallengeWindowSize()
    {
        return '05';
    }

    /**
     * 3ds:cardholderAccountAgeIndicator
     * Length of time that the cardholder has had the account with the 3DS Requestor.
     * 01 -> No account (guest check-out)
     * 02 -> During this transaction
     * 03 -> Less than 30 days
     * 04 -> 30 - 60 days
     * 05 -> More than 60 days
     *
     * @return string|null
     */
    private function cardholderAccountAgeIndicator()
    {
        return null;
    }

    /**
     * 3ds:cardHolderAccountChangeIndicator
     * Length of time since the cardholder’s account information with the 3DS Requestor waslast changed. Includes Billing or Shipping address, new payment account, or new user(s) added.
     * 01 -> Changed during this transaction
     * 02 -> Less than 30 days
     * 03 -> 30 - 60 days
     * 04 -> More than 60 days
     *
     * @return string|null
     */
    private function cardHolderAccountChangeIndicator()
    {
        return null;
    }

    /**
	 * Date that the cardholder opened the account with the 3DS Requestor. Format: YYYY-MM-DD
	 * Example: 2019-05-12
	 *
	 * @throws Exception
	 * @return string|null
	 */
	private function cardholderAccountDate()
	{
	    if (!$this->user || empty($this->user->user_registered)) {
	        return $this->cardholderAccountLastChange();
	    }
	
	    try {
	        $date = new DateTime($this->user->user_registered);
	        return $date->format('Y-m-d');
	    } catch (\Exception $e) {
	        return $this->cardholderAccountLastChange();
	    }
	}


    /**
     * 3ds:cardholderAccountLastChange
     * Date that the cardholder’s account with the 3DS Requestor was last changed. Including Billing or Shipping address, new payment account, or new user(s) added. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @throws Exception
     * @return string|null
     */
    private function cardholderAccountLastChange()
    {
        if (!$this->user) {
            return null;
        }

        $lastUpdate = get_user_meta($this->user->ID, 'last_update', true);

        return $lastUpdate ? (new DateTime('@' . $lastUpdate))->format('Y-m-d') : null;
    }

    /**
     * 3ds:cardholderAccountLastPasswordChange
     * Date that cardholder’s account with the 3DS Requestor had a password change or account reset. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @return string|null
     */
    private function cardholderAccountLastPasswordChange()
    {
        return null;
    }

    /**
     * 3ds:cardholderAccountPasswordChangeIndicator
     * Length of time since the cardholder’s account with the 3DS Requestor had a password change or account reset.
     * 01 -> No change
     * 02 -> Changed during this transaction
     * 03 -> Less than 30 days
     * 04 -> 30 - 60 days
     * 05 -> More than 60 days
     *
     * @return string|null
     */
    private function cardholderAccountPasswordChangeIndicator()
    {
        return null;
    }

    /**
     * 3ds:cardholderAccountType
     * Indicates the type of account. For example, for a multi-account card product.
     * 01 -> Not applicable
     * 02 -> Credit
     * 03 -> Debit
     * 80 -> JCB specific value for Prepaid
     *
     * @return string|null
     */
    private function cardholderAccountType()
    {
        return null;
    }

    /**
     * 3ds:cardHolderAuthenticationData
     * Data that documents and supports a specific authentication process. In the current version of the specification, this data element is not defined in detail, however the intention is that for each 3DS Requestor Authentication Method, this field carry data that the ACS can use to verify the authentication process.
     *
     * @return string|null
     */
    private function cardHolderAuthenticationData()
    {
        return null;
    }

    /**
     * 3ds:cardholderAuthenticationDateTime
     * Date and time in UTC of the cardholder authentication. Format: YYYY-MM-DD HH:mm
     * Example: 2019-05-12 18:34
     *
     * @return string|null
     */
    private function cardholderAuthenticationDateTime()
    {
        return null;
    }

    /**
     * 3ds:cardholderAuthenticationMethod
     * Mechanism used by the Cardholder to authenticate to the 3DS Requestor.
     * 01 -> No 3DS Requestor authentication occurred (i.e. cardholder "logged in" as guest)
     * 02 -> Login to the cardholder account at the 3DS Requestor system using 3DS Requestor's own credentials
     * 03 -> Login to the cardholder account at the 3DS Requestor system using federated ID
     * 04 -> Login to the cardholder account at the 3DS Requestor system using issuer credentials
     * 05 -> Login to the cardholder account at the 3DS Requestor system using third-party authentication
     * 06 -> Login to the cardholder account at the 3DS Requestor system using FIDO Authenticator
     *
     * @return string|null
     */
    private function cardholderAuthenticationMethod()
    {
        return null;
    }

    /**
     * 3ds:challengeIndicator
     * Indicates whether a challenge is requested for this transaction. For example: For 01-PA, a 3DS Requestor may have concerns about the transaction, and request a challenge.
     * 01 -> No preference
     * 02 -> No challenge requested
     * 03 -> Challenge requested: 3DS Requestor Preference
     * 04 -> Challenge requested: Mandate
     *
     * @return string|null
     */
    private function challengeIndicator()
    {
        return null;
    }

    /**
     * 3ds:channel
     * Indicates the type of channel interface being used to initiate the transaction
     * 01 -> App-based
     * 02 -> Browser
     * 03 -> 3DS Requestor Initiated
     *
     * @return string|null
     */
    private function channel()
    {
        return null;
    }

    /**
     * 3ds:deliveryEmailAddress
     * For electronic delivery, the email address to which the merchandise was delivered.
     *
     * @return string|null
     */
    private function deliveryEmailAddress()
    {
        return null;
    }

    /**
     * 3ds:deliveryTimeframe
     * Indicates the merchandise delivery timeframe.
     * 01 -> Electronic Delivery
     * 02 -> Same day shipping
     * 03 -> Overnight shipping
     * 04 -> Two-day or more shipping
     *
     * @return string|null
     */
    private function deliveryTimeframe()
    {
        return null;
    }

    /**
     * 3ds:giftCardAmount
     * For prepaid or gift card purchase, the purchase amount total of prepaid or gift card(s) in major units (for example, USD 123.45 is 123).
     *
     * @return string|null
     */
    private function giftCardAmount()
    {
        return null;
    }

    /**
     * 3ds:giftCardCount
     * For prepaid or gift card purchase, total count of individual prepaid or gift cards/codes purchased. Field is limited to 2 characters.
     *
     * @return string|null
     */
    private function giftCardCount()
    {
        return null;
    }

    /**
     * 3ds:giftCardCurrency
     * For prepaid or gift card purchase, the currency code of the card
     *
     * @return string|null
     */
    private function giftCardCurrency()
    {
        return null;
    }

    /**
     * 3ds:homePhoneCountryPrefix
     * Country Code of the home phone, limited to 1-3 characters
     *
     * @return string|null
     */
    private function homePhoneCountryPrefix()
    {
        return null;
    }

    /**
     * 3ds:homePhoneNumber
     * subscriber section of the number, limited to maximum 15 characters.
     *
     * @return string|null
     */
    private function homePhoneNumber()
    {
        return null;
    }

    /**
     * 3ds:mobilePhoneCountryPrefix
     * Country Code of the mobile phone, limited to 1-3 characters
     *
     * @return string|null
     */
    private function mobilePhoneCountryPrefix()
    {
        return null;
    }

    /**
     * 3ds:mobilePhoneNumber
     * subscriber section of the number, limited to maximum 15 characters.
     *
     * @return string|null
     */
    private function mobilePhoneNumber()
    {
        return null;
    }

    /**
     * 3ds:paymentAccountAgeDate
     * Date that the payment account was enrolled in the cardholder’s account with the 3DS Requestor. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @return string|null
     */
    private function paymentAccountAgeDate()
    {
        return null;
    }

    /**
     * 3ds:paymentAccountAgeIndicator
     * Indicates the length of time that the payment account was enrolled in the cardholder’s account with the 3DS Requestor.
     * 01 -> No account (guest check-out)
     * 02 -> During this transaction
     * 03 -> Less than 30 days
     * 04 -> 30 - 60 days
     * 05 -> More than 60 days
     *
     * @return string|null
     */
    private function paymentAccountAgeIndicator()
    {
        return null;
    }

    /**
     * 3ds:preOrderDate
     * For a pre-ordered purchase, the expected date that the merchandise will be available.
     * Format: YYYY-MM-DD
     *
     * @return string|null
     */
    private function preOrderDate()
    {
        return null;
    }

    /**
     * 3ds:preOrderPurchaseIndicator
     * Indicates whether Cardholder is placing an order for merchandise with a future availability or release date.
     * 01 -> Merchandise available
     * 02 -> Future availability
     *
     * @return string|null
     */
    private function preOrderPurchaseIndicator()
    {
        return null;
    }

    /**
     * 3ds:priorAuthenticationData
     * Data that documents and supports a specfic authentication porcess. In the current version of the specification this data element is not defined in detail, however the intention is that for each 3DS Requestor Authentication Method, this field carry data that the ACS can use to verify the authentication process. In future versionsof the application, these details are expected to be included. Field is limited to maximum 2048 characters.
     *
     * @return string|null
     */
    private function priorAuthenticationData()
    {
        return null;
    }

    /**
     * 3ds:priorAuthenticationDateTime
     * Date and time in UTC of the prior authentication. Format: YYYY-MM-DD HH:mm
     * Example: 2019-05-12 18:34
     *
     * @return string|null
     */
    private function priorAuthenticationDateTime()
    {
        return null;
    }

    /**
     * 3ds:priorAuthenticationMethod
     * Mechanism used by the Cardholder to previously authenticate to the 3DS Requestor.
     * 01 -> Frictionless authentication occurred by ACS
     * 02 -> Cardholder challenge occurred by ACS
     * 03 -> AVS verified
     * 04 -> Other issuer methods
     *
     * @return string|null
     */
    private function priorAuthenticationMethod()
    {
        return null;
    }

    /**
     * 3ds:priorReference
     * This data element provides additional information to the ACS to determine the best approach for handling a request. The field is limited to 36 characters containing ACS Transaction ID for a prior authenticated transaction (for example, the first recurring transaction that was authenticated with the cardholder).
     *
     * @return string|null
     */
    private function priorReference()
    {
        return null;
    }

    /**
     * 3ds:purchaseCountSixMonths
     * Number of purchases with this cardholder account during the previous six months.
     *
     * @return int
     */
    private function purchaseCountSixMonths()
    {
        if (!$this->user) {
            return null;
        }

        $count = 0;
        foreach (['processing', 'completed', 'refunded', 'cancelled', 'authorization'] as $status) {
            $orders = wc_get_orders([
                'customer' => $this->user->ID,
                'limit' => -1,
                'status' => $status,
                'date_after' => '6 months ago',
            ]);
            $count += count($orders);
        }
        return $count;
    }

    /**
     * 3ds:purchaseDate
     * Date and time of the purchase, expressed in UTC. Format: YYYY-MM-DD
     **Note: if omitted we put in today's date
     *
     * @return string|null
     */
    private function purchaseDate()
    {
        return null;
    }

    /**
     * 3ds:purchaseInstalData
     * Indicates the maximum number of authorisations permitted for instalment payments. The field is limited to maximum 3 characters and value shall be greater than 1. The fields is required if the Merchant and Cardholder have agreed to installment payments, i.e. if 3DS Requestor Authentication Indicator = 03. Omitted if not an installment payment authentication.
     *
     * @return string|null
     */
    private function purchaseInstalData()
    {
        return null;
    }

    /**
     * 3ds:recurringExpiry
     * Date after which no further authorizations shall be performed. This field is required for 01-PA and for 02-NPA, if 3DS Requestor Authentication Indicator = 02 or 03.
     * Format: YYYY-MM-DD
     *
     * @return string|null
     */
    private function recurringExpiry()
    {
        return null;
    }

    /**
     * 3ds:recurringFrequency
     * Indicates the minimum number of days between authorizations. The field is limited to maximum 4 characters. This field is required if 3DS Requestor Authentication Indicator = 02 or 03.
     *
     * @return string|null
     */
    private function recurringFrequency()
    {
        return null;
    }

    /**
     * 3ds:reorderItemsIndicator
     * Indicates whether the cardholder is reoreding previously purchased merchandise.
     * 01 -> First time ordered
     * 02 -> Reordered
     *
     * @return string|null
     */
    private function reorderItemsIndicator()
    {
        return null;
    }

    /**
     * 3ds:shipIndicator
     * Indicates shipping method chosen for the transaction. Merchants must choose the Shipping Indicator code that most accurately describes the cardholder's specific transaction. If one or more items are included in the sale, use the Shipping Indicator code for the physical goods, or if all digital goods, use the code that describes the most expensive item.
     * 01 -> Ship to cardholder's billing address
     * 02 -> Ship to another verified address on file with merchant
     * 03 -> Ship to address that is different than the cardholder's billing address
     * 04 -> "Ship to Store" / Pick-up at local store (Store address shall be populated in shipping address fields)
     * 05 -> Digital goods (includes online services, electronic gift cards and redemption codes)
     * 06 -> Travel and Event tickets, not shipped
     * 07 -> Other (for example, Gaming, digital services not shipped, emedia subscriptions, etc.)
     *
     * @return string|null
     */
    private function shipIndicator()
    {
        return null;
    }

    /**
     * 3ds:shippingAddressFirstUsage
     * Date when the shipping address used for this transaction was first used with the 3DS Requestor. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @throws Exception
     * @return string|null
     */
    private function shippingAddressFirstUsage()
    {
        if (!$this->user) {
            return null;
        }

        $orders = wc_get_orders([
            'customer' => $this->user->ID,
            'shipping_address_1' => $this->order->get_shipping_address_1(),
            'orderby' => 'date',
            'order' => 'ASC',
            'limit' => 1,
            'paginate' => false,
        ]);

        /** @var WC_Order $firstOrder */
        $firstOrder = reset($orders);
        $firstOrderDate = $firstOrder && $firstOrder->get_date_created() ? $firstOrder->get_date_created() : new WC_DateTime();
        return $firstOrderDate->format('Y-m-d');
    }

    /**
     * 3ds:shippingAddressLine3
     * Line 3 of customer's shipping address
     *
     * @return string|null
     */
    private function shippingAddressLine3()
    {
        return null;
    }

    /**
     * 3ds:shippingAddressUsageIndicator
     * Indicates when the shipping address used for this transaction was first used with the 3DS Requestor.
     * 01 -> This transaction
     * 02 -> Less than 30 days
     * 03 -> 30 - 60 days
     * 04 -> More than 60 days.
     *
     * @return string|null
     */
    private function shippingAddressUsageIndicator()
    {
        return null;
    }

    /**
     * 3ds:shippingNameEqualIndicator
     * Indicates if the Cardholder Name on the account is identical to the shipping Name used for this transaction.
     * 01 -> Account Name identical to shipping Name
     * 02 -> Account Name different than shipping Name
     *
     * @return string|null
     */
    private function shippingNameEqualIndicator()
    {
        return null;
    }

    /**
     * 3ds:suspiciousAccountActivityIndicator
     * Indicates whether the 3DS Requestor has experienced suspicious activity (including previous fraud) on the cardholder account.
     * 01 -> No suspicious activity has been observed
     * 02 -> Suspicious activity has been observed
     *
     * @return string|null
     */
    private function suspiciousAccountActivityIndicator()
    {
        return null;
    }

    /**
     * 3ds:transactionActivityDay
     * Number of transactions (successful and abandoned) for this cardholder account with the 3DS Requestor across all payment accounts in the previous 24 hours.
     *
     * @return string|null
     */
    private function transactionActivityDay()
    {
        return null;
    }

    /**
     * 3ds:transactionActivityYear
     * Number of transactions (successful and abandoned) for this cardholder account with the 3DS Requestor across all payment accounts in the previous year.
     *
     * @return string|null
     */
    private function transactionActivityYear()
    {
        return null;
    }

    /**
     * 3ds:transType
     * Identifies the type of transaction being authenticated. The values are derived from ISO 8583.
     * 01 -> Goods / Service purchase
     * 03 -> Check Acceptance
     * 10 -> Account Funding
     * 11 -> Quasi-Cash Transaction
     * 28 -> Prepaid activation and Loan
     *
     * @return string|null
     */
    private function transType()
    {
        return null;
    }

    /**
     * 3ds:workPhoneCountryPrefix
     * Country Code of the work phone, limited to 1-3 characters
     *
     * @return string|null
     */
    private function workPhoneCountryPrefix()
    {
        return null;
    }

    /**
     * 3ds:workPhoneNumber
     * subscriber section of the number, limited to maximum 15 characters.
     *
     * @return string|null
     */
    private function workPhoneNumber()
    {
        return null;
    }
    /** 
     * add payment description
    */
    public function updateDescription($id)
    {
        if ($id == $this->id){
            echo '<div style = "margin-left: 30px; padding: 5px;">You\'ll be directed to the next page to complete the payment. Powered by <a href="https://tillpayments.com/">Till Payments</a></div>';
        }
    }
    }
}

