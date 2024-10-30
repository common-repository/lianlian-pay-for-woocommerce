<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once('class-wc-gateway-lianlian-response.php');

/**
 * Handles responses from Lianlian Notify
 */
class WC_Gateway_Lianlian_Notify_Handler extends WC_Gateway_Lianlian_Response
{
    /**
     * Constructor
     */
    public function __construct($sandbox = false)
    {
        add_action('woocommerce_api_wc_gateway_lianlian', array($this, 'check_response'));
//        $params = array();
//        foreach ($_POST as $key => $value) {
//            $params[$key] = $value;
//        }
        //$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
        //       if (empty($params)) {
        //           $params_str = file_get_contents("php://input");
        //           $params = json_decode($params_str, true);
        //       }
        //error_log(json_encode($params));
//		if ( !empty( $params )) {
//			$posted = wp_unslash($params);
//			$status = sanitize_text_field($posted['order_status']);
//			if ($status =='PS') {
//				$merchantOrderId = sanitize_text_field($posted['merchant_order_id']);
//				if ($this->validate_notify($params)) {
        //WC_Gateway_Lianlian_Response::payment_complete($merchantOrderId);
        //error_log('{"code": 200000}');
//					echo '{"code": 200000}';
//				} else {
//					wc_add_notice( 'sign error', 'error' );
//					WC_Gateway_Lianlian_Response::payment_on_hold($merchantOrderId);
//				}
//			}
//		}

        $this->sandbox = $sandbox;
    }

    public function check_response()
    {
        $params = array();
        foreach ($_POST as $key => $value) {
            $params[$key] = $value;
        }
        //$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
        if (empty($params)) {
            $params_str = file_get_contents("php://input");
            $params = json_decode($params_str, true);
        }
        //error_log(json_encode($params));
        if (!empty($params)) {
            $posted = wp_unslash($params);
            $status = sanitize_text_field($posted['order_status']);
            if ($status == 'PS') {
                $merchantOrderId = sanitize_text_field($posted['merchant_order_id']);
                if ($this->validate_notify($params)) {
                    WC_Gateway_Lianlian_Response::payment_complete($merchantOrderId);
                    //error_log('{"code": 200000}');
                    echo '{"code": 200000}';
                    exit;
                } else {
                    wc_add_notice('sign error', 'error');
                    WC_Gateway_Lianlian_Response::payment_on_hold($merchantOrderId);
                }
            }
        }
    }


    /**
     * Check Lianlian notify validity
     */
    public function validate_notify($params)
    {

        WC_Gateway_Lianlian::log('Checking Notify response is valid');
        $lianlian = new WC_Gateway_Lianlian();
        $merchantId = $lianlian->get_option('merchant_id');
        $publicKey = $lianlian->get_option('public_key');

        #error_log($publicKey);

        // Assign payment notification values to local variables
        //$orderId = sanitize_text_field($_POST['order_id']);
        //$merchantOrderId = sanitize_text_field($_POST['merchant_order_id']);
        //$orderStatus = sanitize_text_field($_POST['order_status']);
        //$merchant_order_id = sanitize_text_field($_POST['order_amount']);
        //$orderCurrency = sanitize_text_field($_POST['order_currency']);
        //$sign = sanitize_text_field($_POST['sign']);

        //$orderId = sanitize_text_field($params['order_id']);
        //$merchantOrderId = sanitize_text_field($params['merchant_order_id']);
        //$orderStatus = sanitize_text_field($params['order_status']);
        //$merchant_order_id = sanitize_text_field($params['order_amount']);
        //$orderCurrency = sanitize_text_field($params['order_currency']);
        //$sign = sanitize_text_field($params['sign']);

        $sign = sanitize_text_field($_SERVER['HTTP_SIGN']);
        //error_log(json_encode($_SERVER));
        //error_log($sign);

        //$check_array = array(
        //		'order_amount'        =>$merchant_order_id,
        //		'order_currency'      =>$orderCurrency,
        //		'order_id'            =>$orderId,
        //		'order_status'        =>$orderStatus,
        //		'merchant_order_id'   =>$merchantOrderId
        //);

        //ksort($check_array);

        ksort($params);

        //$check_msg = http_build_query( $check_array, '', '&' );
        $check_msg = urldecode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        //error_log($check_msg);
        $signature = $this->rsa_verify($check_msg, $sign, $publicKey);

        return $signature;
    }

    private function rsa_verify($data, $sign, $pubKey, $sign_type = OPENSSL_ALGO_SHA1)
    {

        $pubKey = chunk_split($pubKey, 64, "\n");
        $key = "-----BEGIN PUBLIC KEY-----\n$pubKey-----END PUBLIC KEY-----\n";

        $res = openssl_get_publickey($key);
        $result = (bool)openssl_verify($data, base64_decode($sign), $key, $sign_type);
        openssl_free_key($res);

        return $result;

    }


    /**
     * Send a notification to the user handling orders.
     * @param string $subject
     * @param string $message
     */
    private function send_email_notification($subject, $message)
    {
        $new_order_settings = get_option('woocommerce_new_order_settings', array());
        $mailer = WC()->mailer();
        $message = $mailer->wrap_message($subject, $message);

        $mailer->send(!empty($new_order_settings['recipient']) ? $new_order_settings['recipient'] : get_option('admin_email'), $subject, $message);
    }
}

?>
