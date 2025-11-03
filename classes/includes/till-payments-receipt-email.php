<?php
/**
 * Till Payments Receipt Email
 *
 * Custom WooCommerce email class for sending payment receipts after successful Till Payments transactions
 *
 * @package TillPayments
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_TillPayments_Receipt_Email Class
 */
class WC_TillPayments_Receipt_Email extends WC_Email {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'tillpayments_payment_receipt';
        $this->title          = __('Till Payments Payment Receipt', 'woocommerce');
        $this->description    = __('Payment receipt sent after successful payment through Till Payments', 'woocommerce');
        $this->heading        = __('Payment Receipt', 'woocommerce');
        $this->subject        = __('Payment Receipt for Order {order_number}', 'woocommerce');

        $this->template_html  = 'emails/till-payments-receipt.php';
        $this->template_plain = 'emails/plain/till-payments-receipt.php';
        $this->template_base  = TILL_PAYMENTS_EXTENSION_BASEDIR . 'templates/';

        // Triggers for this email
        add_action('tillpayments_send_receipt', array($this, 'trigger'), 10, 1);

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Trigger the sending of this email
     *
     * @param int $order_id The order ID
     */
    public function trigger($order_id) {
        $this->setup_locale();

        if ($order_id && !is_a($order_id, 'WC_Order')) {
            $this->object = wc_get_order($order_id);
        }

        if (!is_a($this->object, 'WC_Order')) {
            $this->restore_locale();
            return;
        }

        // Only send for Till Payments orders
        $payment_method = $this->object->get_payment_method();
        if (!in_array($payment_method, array('till_payments_creditcard', 'till_payments_googlepay', 'till_payments_applepay'))) {
            $this->restore_locale();
            return;
        }

        // Check if receipt emails are enabled
        $gateway = wc_get_payment_gateway_by_order($this->object);
        if ($gateway && isset($gateway->settings['receipt_enabled']) && $gateway->settings['receipt_enabled'] !== 'yes') {
            $this->restore_locale();
            return;
        }

        $this->recipient = $this->object->get_billing_email();

        // Replace placeholders in subject and heading
        $this->placeholders = array(
            '{order_number}' => $this->object->get_order_number(),
            '{order_date}'   => wc_format_datetime($this->object->get_date_created()),
        );

        // Handle PDF attachment
        $this->attachments = array();
        if ($gateway && isset($gateway->settings['receipt_attach_pdf']) && $gateway->settings['receipt_attach_pdf'] === 'yes') {
            try {
                require_once TILL_PAYMENTS_EXTENSION_BASEDIR . 'classes/includes/till-payments-receipt-pdf.php';
                $pdf_path = WC_TillPayments_Receipt_PDF::generate_pdf($order_id);
                if ($pdf_path && file_exists($pdf_path)) {
                    $this->attachments[] = $pdf_path;
                }
            } catch (Exception $e) {
                // Log error but don't block email
                error_log('Till Payments: Failed to generate PDF receipt: ' . $e->getMessage());
                $this->object->add_order_note(__('Receipt PDF generation failed', 'woocommerce'));
            }
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            $send_result = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());

            if (!$send_result) {
                error_log('Till Payments: Failed to send receipt email for order #' . $order_id);
                $this->object->add_order_note(__('Receipt email failed to send', 'woocommerce'));
            }
        }

        $this->restore_locale();
    }

    /**
     * Get content HTML
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'receipt_data'       => $this->get_receipt_data($this->object),
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain text
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
                'receipt_data'       => $this->get_receipt_data($this->object),
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get receipt data formatted for display
     *
     * @param WC_Order $order
     * @return array
     */
    public function get_receipt_data($order) {
        if (!$order) {
            return array();
        }

        $payment_date = $order->get_date_paid();
        if (!$payment_date) {
            $payment_date = $order->get_date_created();
        }

        $data = array(
            'order_number'          => $order->get_order_number(),
            'order_id'              => $order->get_id(),
            'payment_date'          => wc_format_datetime($payment_date, get_option('date_format') . ' ' . get_option('time_format')),
            'amount'                => $order->get_total(),
            'currency'              => $order->get_currency(),
            'formatted_amount'      => wc_price($order->get_total(), array('currency' => $order->get_currency())),
            'payment_method_title'  => $order->get_payment_method_title(),
            'payment_method'        => $order->get_payment_method(),
            'reference_id'          => $order->get_meta('paymentUuid'),
            'transaction_id'        => $order->get_transaction_id(),
            'customer_name'         => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email'        => $order->get_billing_email(),
            'billing_address'       => $order->get_formatted_billing_address(),
            'payment_method_details' => $this->get_payment_method_details($order),
            'status'                => __('Paid', 'woocommerce'),
            'order_view_url'        => $order->get_view_order_url(),
            'receipt_view_url'      => $this->get_receipt_view_url($order),
        );

        // Fallback if no transaction ID in meta
        if (empty($data['reference_id'])) {
            $data['reference_id'] = $data['transaction_id'];
        }

        return $data;
    }

    /**
     * Get formatted payment method details
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_payment_method_details($order) {
        $payment_method = $order->get_payment_method();

        if ($payment_method === 'till_payments_googlepay') {
            return __('Google Pay', 'woocommerce');
        }

        if ($payment_method === 'till_payments_applepay') {
            return __('Apple Pay', 'woocommerce');
        }

        // For credit card, try to extract last 4 digits from order notes
        if ($payment_method === 'till_payments_creditcard') {
            $notes = $order->get_customer_order_notes();
            foreach ($notes as $note) {
                // Look for patterns like "•••• 4242" or "ending in 1234"
                if (preg_match('/[•\*]{4}\s*(\d{4})/', $note->comment_content, $matches)) {
                    return sprintf(__('Card •••• %s', 'woocommerce'), $matches[1]);
                }
                if (preg_match('/ending in\s*(\d{4})/i', $note->comment_content, $matches)) {
                    return sprintf(__('Card •••• %s', 'woocommerce'), $matches[1]);
                }
            }
            return __('Credit Card', 'woocommerce');
        }

        return $order->get_payment_method_title();
    }

    /**
     * Get receipt view URL
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_receipt_view_url($order) {
        return wc_get_endpoint_url('view-receipt', $order->get_id(), wc_get_page_permalink('myaccount'));
    }

    /**
     * Initialize settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __('Enable/Disable', 'woocommerce'),
                'type'          => 'checkbox',
                'label'         => __('Enable this email notification', 'woocommerce'),
                'default'       => 'yes',
            ),
            'subject' => array(
                'title'         => __('Subject', 'woocommerce'),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => sprintf(__('Available placeholders: %s', 'woocommerce'), '{order_number}, {order_date}'),
                'placeholder'   => $this->get_default_subject(),
                'default'       => '',
            ),
            'heading' => array(
                'title'         => __('Email heading', 'woocommerce'),
                'type'          => 'text',
                'desc_tip'      => true,
                'description'   => sprintf(__('Available placeholders: %s', 'woocommerce'), '{order_number}, {order_date}'),
                'placeholder'   => $this->get_default_heading(),
                'default'       => '',
            ),
            'attach_pdf' => array(
                'title'         => __('Attach PDF Receipt', 'woocommerce'),
                'type'          => 'checkbox',
                'label'         => __('Attach PDF receipt to email', 'woocommerce'),
                'default'       => 'yes',
            ),
        );
    }
}
