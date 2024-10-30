<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
include_once('class-wc-gateway-lianlian-order.php');

/**
 * Generates requests to send to Lianlian
 */
class WC_Gateway_Lianlian_Request
{

    /**
     * Stores line items to send to Lianlian
     * @var array
     */
    protected $line_items = array();

    /**
     * Pointer to gateway making the request
     * @var WC_Gateway_Lianlian
     */
    protected $gateway;

    /**
     * Endpoint for requests from Lianlian
     * @var string
     */
    protected $notify_url;

    /**
     * Endpoint for requests from Lianlian
     * @var string
     */
    protected $setUrl;

    /**
     * Constructor
     * @param WC_Gateway_Lianlian $gateway
     */
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
        $this->notify_url = WC()->api_request_url('WC_Gateway_Lianlian');
    }


    /**
     * Get the Lianlian request URL for an order
     * @param WC_Order $order
     * @param boolean $sandbox
     * @return string
     */
    public function get_request_url($order, $sandbox = false, $url_redirect)
    {

        if ($sandbox) {
            $this->setURL = 'https://sandbox-th.lianlianpay-inc.com/gateway';
        } else {
            $this->setURL = 'https://api.lianlianpay.co.th/gateway';
        }

        error_log($this->setURL);

        $redirectUrl = $this->get_lianlian_args($order, $sandbox, $url_redirect);
        // $Lianlian_args = http_build_query( $this->get_lianlian_args( $order ), '', '&' );

        return $redirectUrl;

    }

    /**
     * Get Lianlian Args for passing to PP
     *
     * @param WC_Order $order
     * @return array
     */
    public function get_lianlian_args($order, $sandbox = false, $url_redirect)
    {

        $order = new WC_Gateway_Lianlian_Order($order);
        WC_Gateway_Lianlian::log('Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url);

        $order_total = number_format($order->order_total, 2, '.', '');
        $lianlian = new WC_Gateway_Lianlian();

        $privateKey = $this->gateway->get_privatekey();

        $data = array(

            'version' => 'v1',
            'service' => 'llpth.checkout.apply',

            // 'redirect_url' =>  get_permalink(function_exists('wc_get_page_id') ? wc_get_page_id('myaccount') : wc_get_page_id('myaccount')),
            'redirect_url' => $url_redirect,

            'notify_url' => $this->notify_url,
            'merchant_id' => $this->gateway->get_merchantid(),
            'merchant_order_id' => $order->get_id(),
            //'payment_method' => $this->gateway->get_pmid(),
            'order_amount' => $order_total,
            'order_currency' => get_woocommerce_currency(),
            'order_desc' => $this->get_order_item_names($order),
            'customer' => array(
                'merchant_user_id' => !empty($order->get_billing_email()) ? $order->get_billing_email() : $order->get_billing_phone(),
                'full_name' => $order->billing_first_name . ' ' . $order->billing_last_name
            )
        );

        $payBody = json_encode($data);
        error_log($payBody);

        $paymentOrderSign = $this->signMap($data, $privateKey);

        $url = esc_url($this->setURL);

        $headers = array(
            'sign_type' => 'RSA',
            'sign' => $paymentOrderSign
        );

        $result = $this->post($url, $payBody, $headers);

//        if ($result != null && $result['http_code'] == 200) {
//            error_log('passed');
//        } else {
//            error_log('failed');
//        }

        return $result;
    }

    protected function signMap($params, $privateKey)
    {
        ksort($params);
        $prestr = $this->createLinkstring($params);
        $sign = "";
        $prestr = stripslashes($prestr);
        $sign = $this->rsa_sign($prestr, $privateKey);
        return $sign;
    }

    protected function createLinkstring($para)
    {
        $arg = "";
        $arg = $this->linkString($para);
        $arg = substr($arg, 0, strlen($arg) - 1);
        //if (get_magic_quotes_gpc()) {
        if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase'))!="off"))) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    protected function linkString($para)
    {
        $arg = "";
        foreach ($para as $key => $val) {
            if (is_array($val)) {
                ksort($val);
                $arg .= $this->linkString($val);
            } else {
                $arg .= $key . "=" . $val . "&";
            }
        }
        return $arg;
    }

    private function rsa_sign($data, $priKey, $sign_type = OPENSSL_ALGO_SHA1)
    {
        $priKey = chunk_split($priKey, 64, "\n");
        $key = "-----BEGIN RSA PRIVATE KEY-----\n$priKey-----END RSA PRIVATE KEY-----\n";
        $sign = '';
        // $res = openssl_get_privatekey($priKey);
        if (openssl_sign($data, $sign, $key, OPENSSL_ALGO_SHA1)) {
            openssl_free_key(openssl_get_privatekey($key));
            $sign = base64_encode($sign);
        } else {
            error_log('open ssl not passed!');
        }

        return $sign;
    }


    private function post($url = '', $params = '', array $headers = array())
    {
        if (empty($url) || empty($params)) {
            return '{}';
        }

        $header = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );

        if (!empty($headers)) {
            $header = array_merge($header, $headers);
        }

        $jsonData = $params;
        $postUrl = $url;

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => $header,
            'httpversion' => '1.0',
            'sslverify' => false,
            'blocking' => true,
            'body' => $jsonData,
            'cookies' => array()
        );

        $data = wp_remote_post($postUrl, $args);
        $httpCode = wp_remote_retrieve_response_code($data);

        if (is_wp_error($data) || $httpCode != 200) {
            error_log('wp_remote_post failed: ' . json_encode($data));
        }

        $response = wp_remote_retrieve_body($data);
        $checkResponse = json_decode($response, true);

        $result = array();
        $result['http_code'] = $httpCode;
        $result['request'] = $jsonData;
        $result['response'] = $response;

        if ($result != null && $checkResponse['code'] == 200000) {
            return $checkResponse['data']['link_url'];
        } else {
            wc_add_notice('Response Failed ', 'error');
            wc_add_notice($response, 'error');
            return get_permalink(function_exists('wc_get_page_id') ? wc_get_page_id('checkout') : wc_get_page_id('checkout'));
        }

    }


    /**
     * Get shipping args for Lianlian request
     * @param WC_Order $order
     * @return array
     */
    protected function get_shipping_args($order)
    {
        $order = new WC_Gateway_Lianlian_Order($order);
        $shipping_args = array();

        if ('yes' == $this->gateway->get_option('send_shipping')) {
            $shipping_args['address_override'] = $this->gateway->get_option('address_override') === 'yes' ? 1 : 0;
            $shipping_args['no_shipping'] = 0;

            // If we are sending shipping, send shipping address instead of billing
            $shipping_args['first_name'] = $order->shipping_first_name;
            $shipping_args['last_name'] = $order->shipping_last_name;
            $shipping_args['company'] = $order->shipping_company;
            $shipping_args['address1'] = $order->shipping_address_1;
            $shipping_args['address2'] = $order->shipping_address_2;
            $shipping_args['city'] = $order->shipping_city;
            $shipping_args['state'] = $this->get_Lianlian_state($order->shipping_country, $order->shipping_state);
            $shipping_args['country'] = $order->shipping_country;
            $shipping_args['zip'] = $order->shipping_postcode;
        } else {
            $shipping_args['no_shipping'] = 1;
        }

        return $shipping_args;
    }

    /**
     * Get line item args for Lianlian request
     * @param WC_Order $order
     * @return array
     */
    protected function get_line_item_args($order)
    {
        /**
         * Try passing a line item per product if supported
         */
        if ((!wc_tax_enabled() || !wc_prices_include_tax()) && $this->prepare_line_items($order)) {

            $line_item_args = $this->get_line_items();
            $line_item_args['tax_cart'] = $order->get_total_tax();

            if ($order->get_total_discount() > 0) {
                $line_item_args['discount_amount_cart'] = round($order->get_total_discount(), 2);
            }

            /**
             * Send order as a single item
             *
             * For shipping, we longer use shipping_1 because Lianlian ignores it if *any* shipping rules are within Lianlian, and Lianlian ignores anything over 5 digits (999.99 is the max)
             */
        } else {

            $this->delete_line_items();

            $this->add_line_item($this->get_order_item_names($order), 1, number_format($order->get_total() - round($order->get_total_shipping() + $order->get_shipping_tax(), 2), 2, '.', ''), $order->get_order_number());
            $this->add_line_item(sprintf(__('Shipping via %s', 'woocommerce'), ucwords($order->get_shipping_method())), 1, number_format($order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', ''));

            $line_item_args = $this->get_line_items();
        }

        return $line_item_args;
    }

    /**
     * Get order item names as a string
     * @param WC_Order $order
     * @return string
     */
    protected function get_order_item_names($order)
    {
        $item_names = array();

        $length = 0;

        $item_content = '';
        foreach ($order->get_items() as $item) {
            $item_content = $item['name'] . ' x ' . $item['qty'];
            $length += strlen($item_content);
            error_log('length=' . $length);
            if ($length < 222) {
                $item_names[] = $item_content;
            } else {
                break;
            }
        }

        if (strlen($item_content) > 0 && count($item_names) == 0) {
            return $this->sub_str($item_content, 252);
        }

        return implode(', ', $item_names);
    }

    function sub_str($str, $length = 0, $append = true)
    {
        $str = trim($str);
        $strlength = strlen($str);

        if ($length == 0 || $length >= $strlength) {
            return $str;  //截取长度等于0或大于等于本字符串的长度，返回字符串本身
        } elseif ($length < 0)  //如果截取长度为负数
        {
            $length = $strlength + $length;//那么截取长度就等于字符串长度减去截取长度
            if ($length < 0) {
                $length = $strlength;//如果截取长度的绝对值大于字符串本身长度，则截取长度取字符串本身的长度
            }
        }

        if (function_exists('mb_substr')) {
            $newstr = mb_substr($str, 0, $length, 'UTF-8');
        } elseif (function_exists('iconv_substr')) {
            $newstr = iconv_substr($str, 0, $length, 'UTF-8');
        } else {
            //$newstr = trim_right(substr($str, 0, $length));
            $newstr = substr($str, 0, $length);
        }

        if ($append && $str != $newstr) {
            $newstr .= '...';
        }

        return $newstr;
    }

    /**
     * Get order item names as a string
     * @param WC_Order $order
     * @param array $item
     * @return string
     */
    protected function get_order_item_name($order, $item)
    {
        $item_name = $item['name'];
        $item_meta = new WC_Order_Item_Meta($item['item_meta']);

        if ($meta = $item_meta->display(true, true)) {
            $item_name .= ' ( ' . $meta . ' )';
        }

        return $item_name;
    }

    /**
     * Return all line items
     */
    protected function get_line_items()
    {
        return $this->line_items;
    }

    /**
     * Remove all line items
     */
    protected function delete_line_items()
    {
        $this->line_items = array();
    }

    /**
     * Get line items to send to Lianlian
     *
     * @param WC_Order $order
     * @return bool
     */
    protected function prepare_line_items($order)
    {
        $order = new WC_Gateway_Lianlian_Order($order);
        $this->delete_line_items();
        $calculated_total = 0;

        // Products
        foreach ($order->get_items(array('line_item', 'fee')) as $item) {
            if ('fee' === $item['type']) {
                $line_item = $this->add_line_item($item['name'], 1, $item['line_total']);
                $calculated_total += $item['line_total'];
            } else {
                $product = $order->get_product_from_item($item);
                $line_item = $this->add_line_item($this->get_order_item_name($order, $item), $item['qty'], $order->get_item_subtotal($item, false), $product->get_sku());
                $calculated_total += $order->get_item_subtotal($item, false) * $item['qty'];
            }

            if (!$line_item) {
                return false;
            }
        }

        // Shipping Cost item - Lianlian only allows shipping per item, we want to send shipping for the order
        if ($order->get_total_shipping() > 0 && !$this->add_line_item(sprintf(__('Shipping via %s', 'woocommerce'), $order->get_shipping_method()), 1, round($order->get_total_shipping(), 2))) {
            return false;
        }

        // Check for mismatched totals
        if (wc_format_decimal($calculated_total + $order->get_total_tax() + round($order->get_total_shipping(), 2) - round($order->get_total_discount(), 2), 2) != wc_format_decimal($order->get_total(), 2)) {
            return false;
        }

        return true;
    }

    /**
     * Add Lianlian Line Item
     * @param string $item_name
     * @param integer $quantity
     * @param integer $amount
     * @param string $item_number
     * @return bool successfully added or not
     */
    protected function add_line_item($item_name, $quantity = 1, $amount = 0, $item_number = '')
    {
        $index = (sizeof($this->line_items) / 4) + 1;

        if (!$item_name || $amount < 0 || $index > 9) {
            return false;
        }

        $this->line_items['item_name_' . $index] = html_entity_decode(wc_trim_string($item_name, 127), ENT_NOQUOTES, 'UTF-8');
        $this->line_items['quantity_' . $index] = $quantity;
        $this->line_items['amount_' . $index] = $amount;
        $this->line_items['item_number_' . $index] = $item_number;

        return true;
    }

    /**
     * Get the state to send to Lianlian
     * @param string $cc
     * @param string $state
     * @return string
     */
    protected function get_Lianlian_state($cc, $state)
    {
        if ('US' === $cc) {
            return $state;
        }

        $states = WC()->countries->get_states($cc);

        if (isset($states[$state])) {
            return $states[$state];
        }

        return $state;
    }
}

?>