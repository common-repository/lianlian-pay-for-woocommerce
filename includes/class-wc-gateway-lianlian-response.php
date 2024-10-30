<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once('class-wc-gateway-lianlian-order.php');

/**
 * Handles refunds
 */
class WC_Gateway_Lianlian_Response extends WC_Payment_Gateway
{

    /** @var bool Sandbox mode */
    protected $sandbox = false;

    /**
     * Complete order, add transaction ID and note
     */
    protected function payment_complete($order_id)
    {
        global $woocommerce;

        error_log('payment complete: ' . $order_id);

        $order = new WC_Order($order_id);

        //change to status complete
        $order->update_status('processing');

        //Reduce stock levels
        $order->reduce_order_stock();

        WC()->cart->empty_cart();


    }

    /**
     * Hold order and add note
     */
    protected function payment_on_hold($order_id)
    {
        error_log('response on hold');
        $order = new WC_Order($order_id);
        $order->update_status('on-hold', __('Awaiting offline payment', 'wc-gateway-offline'));
    }


}

?>