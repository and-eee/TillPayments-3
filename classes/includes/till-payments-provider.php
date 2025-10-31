<?php

if (!class_exists('WC_TillPayments_V1_10_5_Provider')) {
    final class WC_TillPayments_V1_10_5_Provider
    {
        public static function paymentMethods()
        {
            /**
             * Comment/disable adapters that are not applicable
             */
            return [
                'WC_TillPayments_V1_10_5_CreditCard',
                'WC_TillPayments_V1_10_5_GooglePay',
                'WC_TillPayments_V1_10_5_ApplePay',
            ];
        }

        public static function autoloadClient()
        {
            require_once TILL_PAYMENTS_V1_10_5_EXTENSION_BASEDIR . 'classes/vendor/autoload.php';
        }
    }
}
