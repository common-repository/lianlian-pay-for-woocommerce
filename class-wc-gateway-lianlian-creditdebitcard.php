<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once('class-wc-gateway-lianlian.php');

/**
 * Lianlian
 *
 * @class        WC_Gateway_Lianlian_CreditDebit
 * @extends      WC_Payment_Gateway
 * @author       Lianlian
 */
class WC_Gateway_Lianlian_CreditDebit extends WC_Gateway_Lianlian
{
    public $title = 'LianlianPay';
    protected $payment_method = 'CARD';

    // public function init_form_fields() {
    // 	$this->icon = apply_filters( 'woocommerce_lianlian_icon', 'http://lianlianpay.co.th/static/images/home/logo@2x.png');
    // 	$this->method_title = __( 'Lianlian Payment', 'woocommerce');
    //     $this->method_description = __( 'Accept payment through Credit / Debit Card via LianLian payment gateway.	', 'woocommerce');

    //}

}