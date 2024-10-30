<?php
/*
Plugin Name: Lianlian Pay for WooCommerce
Plugin URI: https://www.lianlianpay.co.th/
Description: Accept credit or debit card payments directly on your WooCommerce store via Lianlian pay.
Version: 1.1.1
Author: Lianlian Pay
Text Domain: lianlian
Author URI: https://www.lianlianpay.co.th/about
*/

add_action('plugins_loaded', 'init_lianlian_gateway', 0);

function init_lianlian_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once('class-wc-gateway-lianlian.php');
    require_once('class-wc-gateway-lianlian-creditdebitcard.php');


    // Add the gateway to WooCommerce
    function add_lianlian_gateway($methods)
    {
        return array_merge($methods,
            array(
                'WC_Gateway_Lianlian',
                'WC_Gateway_Lianlian_CreditDebit',));

    }

    add_filter('woocommerce_payment_gateways', 'add_lianlian_gateway');

    function wc_lianlian_plugin_edit_link($links)
    {
        return array_merge(
            array(
                'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_lianlian') . '">' . __('Settings', 'alipay') . '</a>'
            ),
            $links
        );
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_lianlian_plugin_edit_link');
}

// Register new status
function register_lianlian_paid_partial_order_status()
{
    register_post_status('wc-paid-partial', array(
        'label' => 'Paid partial',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Paid partial (%s)', 'Paid partial (%s)')
    ));
}

add_action('init', 'register_lianlian_paid_partial_order_status');
function add_lianlian_order_statuses($order_statuses)
{
    $order_statuses['wc-paid-partial'] = _x('Paid partial', 'WooCommerce Order status', 'text_domain');
    return $order_statuses;
}

add_filter('wc_order_statuses', 'add_lianlian_order_statuses');
?>
