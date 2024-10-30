<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lianlian Pay
 *
 * @class        WC_Gateway_Lianlian
 * @extends      WC_Payment_Gateway
 * @version      1.0
 * @package      WooCommerce/Classes/Payment
 * @author       Lianlian Pay
 */
class WC_Gateway_Lianlian extends WC_Payment_Gateway
{

    /** @var boolean Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    protected $payment_method = '';
    protected $pm = '';
    protected $is_channel = true;
    public $title = 'Lianlian Pay Gateway';
    public $description = '';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $class_name = get_class($this);
        if (strlen($class_name) == strlen('WC_Gateway_Lianlian')) {
            $this->is_channel = false;
        }
        $index = strrpos($class_name, '_');
        $this->pm = substr($class_name, $index + 1);

        $this->id = strtolower($this->is_channel ? 'lianlian-' . $this->pm : $this->pm);
        $this->icon = apply_filters('woocommerce_lianlian_icon', 'https://www.lianlianpay.co.th/static/images/home/logo@2x.png');
        $this->has_fields = false;

        $method_title = $this->getMethodTitle();
        $this->order_button_text = __('Checkout now', 'woocommerce');
        $this->method_title = ($this->payment_method ? '' : '') . $method_title;
        $this->method_description = __($this->is_channel ? '' : 'Allow customers to easily checkout with credit or debit card.', 'woocommerce');
        $this->supports = array(
            'products'
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->init_lianlian_setting();

        // Define user set variables
        $this->title = $this->get_option('title');
        if ($this->testmode) {
            $this->title .= ' Sandbox';
        }
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->pm, array($this, 'receipt_page'));


        if (!$this->is_valid_for_use()) {
            $this->enabled = 'no';
        } else {
            include_once('includes/class-wc-gateway-lianlian-notify-handler.php');
            new WC_Gateway_Lianlian_Notify_Handler($this->testmode);
        }
    }

    protected function getMethodTitle()
    {
        $method_title = '';
        if ($this->title) {
            $method_title = $this->title;
        } else {
            $method_title = __($this->pm, 'woocommerce');
            $index = strrpos($this->payment_method, '_');
            if ($index && substr($this->payment_method, $index + 1) == substr($method_title, strlen($method_title) - 2)) {
                $method_title = substr($method_title, 0, strlen($method_title) - 2);
            }
        }

        return $method_title;
    }

    protected $merchant_id;
    protected $public_key;
    protected $private_key;

    protected function init_lianlian_setting()
    {
        if ($this->is_channel) {
            $lianlian = new WC_Gateway_Lianlian();
            $this->merchant_id = $lianlian->get_option('merchant_id');
            $this->public_key = $lianlian->get_option('public_key');
            $this->private_key = $lianlian->get_option('private_key');
            $this->testmode = 'yes' === $lianlian->get_option('testmode', 'no');
            $this->debug = 'yes' === $lianlian->get_option('debug', 'no');
        } else {
            $this->merchant_id = $this->get_option('merchant_id');
            $this->public_key = $this->get_option('public_key');
            $this->private_key = $this->get_option('private_key');
            $this->testmode = 'yes' === $this->get_option('testmode', 'no');
            $this->debug = 'yes' === $this->get_option('debug', 'no');
        }
    }

    public function get_merchantid()
    {
        return $this->merchant_id;
    }

    public function get_publickey()
    {
        return $this->public_key;
    }

    public function get_privatekey()
    {
        return $this->private_key;
    }

    /**
     * Logging method
     * @param string $message
     */
    public static function log($message)
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add('Lianlian', $message);
        }
    }

    /**
     * get_icon function.
     *
     * @return string
     */
// 	public function get_icon() {
// 		return apply_filters('woocommerce_lianlian_icon',  plugins_url('assets/images/lianlian.png', __FILE__));
// 	}

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @return bool
     */
    public function is_valid_for_use()
    {
        return $this->is_channel;
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        if (true) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error"><p>
                    <strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Lianlian does not support your store currency.', 'woocommerce'); ?>
                </p></div>
            <?php
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $method_title = $this->getMethodTitle();
        if ($this->is_channel) {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable ' . $method_title, 'woocommerce'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => $method_title,
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __($this->description ? $this->description : ('Pay via ' . $method_title), 'woocommerce')
                )
            );
        } else {
            $this->form_fields = array(
                'testmode' => array(
                    'title' => __('Lianlian Pay Sandbox', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable sandbox', 'woocommerce'),
                    'default' => 'no',
                    'description' => __('Sandbox environment is for payment test only.', 'woocommerce'),
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Get your Merchant ID from Lianlian', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                    'placeholder' => __('Required', 'woocommerce')
                ),
                'public_key' => array(
                    'title' => __('Public Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Get your Public Key from Lianlian', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                    'placeholder' => __('Required', 'woocommerce')
                ),
                'private_key' => array(
                    'title' => __('Private Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter your private key', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                    'placeholder' => __('Required', 'woocommerce')
                ));
        }
    }

    public function get_pmid()
    {
        return $this->payment_method;
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {

        error_log('start processpayment');

        include_once('includes/class-wc-gateway-lianlian-request.php');
        $order = wc_get_order($order_id);

        if ($this->check_currency()) {
            error_log('check_currency pass');
            $Lianlian_request = new WC_Gateway_Lianlian_Request($this);

            // WC()->cart->empty_cart();
            // Return thankyou redirect
            return array(
                'result' => 'success',
                // 'redirect' => $this->get_return_url( $order )
                'redirect' => $Lianlian_request->get_request_url($order, $this->testmode, $this->get_return_url($order))
            );
        } else {
            error_log('check_currency faild');
            wc_add_notice('Lianlian does not support your store currency', 'error');
        }


    }

    /**
     * Check if this gateway is available in the user's country based on currency.
     *
     * @return bool
     */
    private function check_currency()
    {
        return in_array(
            get_woocommerce_currency(),
            apply_filters(
                'woocommerce_paypal_supported_currencies',
                array('CNY', 'THB', 'USD', 'EUR', 'JPY', 'GBP', 'AUD', 'NZD', 'HKD', 'SGD', 'CHF', 'AED', 'BDT', 'BND', 'CAD', 'DKK', 'IDR', 'INR', 'KRW', 'KWD', 'LKR', 'MOP', 'MYR', 'NOK', 'NPR', 'OMR', 'PHP', 'PKR', 'QAR', 'RUB', 'SAR', 'SEK', 'TWD', 'VND', 'ZAR', 'BHD')
            ),
            true
        );
    }


    /**
     * Generate the lianlian button link (POST method)
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_lianlian_form($order_id)
    {

        $order = new WC_Order($order_id);
        $lianlian_args_array = array('<input type="hidden" name="' . 'key' . '" value="' . 'value' . '" />');

        wc_enqueue_js('
				$.blockUI({
				message: "' . esc_js(__('Thank you for your order. We are now redirecting you to lianlian to make payment.', 'lianlian')) . '",
				baseZ: 99999,
				overlayCSS:
				{
				background: "#fff",
				opacity: 0.6
	},
				css: {
				padding:        "20px",
				zindex:         "9999999",
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait",
				lineHeight:     "24px",
	}
	});
				jQuery("#submit_lianlian_payment_form").click();
				');

        return '<form id="lianliansubmit" name="lianliansubmit" action="www.lianlian.com' . '" method="post" target="_top">' . implode('', $lianlian_args_array) . '
		<!-- Button Fallback -->
		<div class="payment_buttons">
		<input type="submit" class="button-alt" id="submit_lianlian_payment_form" value="' . __('Pay via lianlian', 'lianlian') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'lianlian') . '</a>
		</div>
		<script type="text/javascript">
		jQuery(".payment_buttons").hide();
		</script>
		</form>';
    }

    /**
     * Process a refund if supported
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return  boolean True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        error_log('into refund function');
        $this->log('Refund Failed: You have to log in at lianlian in order to process refund');
        return false;
    }
}

?>
