<?php

/*
Plugin Name: Tinkl.it WooCommerce Payment Gateway
Plugin URI: https://tinkl.it
Description: Accept Bitcoin Instantly via Tinkl.it
Version: 1.2.2
Author: Tinkl.it
Author URI: https://tinkl.it/it/chi-siamo
*/

add_action('plugins_loaded', 'tinklit_init');

add_action('wp_loaded', 'tinklit_custom_payment_response');


function tinklit_custom_payment_response() {

    if( !empty( $_REQUEST["custom_tinklit_response"] ) )
        {

            global $woocommerce;

            $order_key = $_GET['key'];

            $order_id = wc_get_order_id_by_order_key( $order_key );

            $guid = get_post_meta( $order_id, 'tinklit_invoice_guid' );

            $gateways = $woocommerce->payment_gateways->get_available_payment_gateways();

            if( tinklit_check_if_already_processed( $order_id , $guid ) ) {

                if( !empty( $gateways['tinklit'] ) &&
                method_exists( $gateways['tinklit'], 'payment_callback' ) )
                {
                    $gateways['tinklit']->payment_callback( $order_id, $guid[0] );

                    update_post_meta( $order_id, 'tinklit_processing',  'Payment done and processed' );

                }

            } else {

                wp_die( 'This order is already processed. ');
            }
        }
}

function tinklit_check_if_already_processed ( $order_id , $guid ) {

    $tinklit_processed_order = get_post_meta( $order_id, 'tinklit_processing' );

    if( empty( $tinklit_processed_order ) && !empty( $guid ) ) {

        return true;
    }
}



define('TINKLIT_WOOCOMMERCE_VERSION', '1.2');

function tinklit_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

    require_once(__DIR__ . '/lib/tinklit/init.php');

    class WC_Gateway_Tinklit extends WC_Payment_Gateway
    {
        public function __construct()
        {
            global $woocommerce;

            $this->id = 'tinklit';
            $this->has_fields = false;
            $this->method_title = 'Tinklit';
            $this->icon = apply_filters('woocommerce_tinklit_icon', PLUGIN_DIR . 'assets/bitcoin.png');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->client_id = (empty($this->get_option('client_id')) ? $this->get_option('client_id') : $this->get_option('client_id'));
            $this->token = (empty($this->get_option('token')) ? $this->get_option('token') : $this->get_option('token'));
			$this->test = (empty($this->get_option('test')) ? $this->get_option('test') : $this->get_option('test'));

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_order_statuses'));
            add_action('woocommerce_thankyou_tinklit', array($this, 'thankyou'));
        }

        public function admin_options()
        {
            ?>
            <h3><?php _e('Tinkl.it - Bitcoin POS Payments', 'woothemes'); ?></h3>
            <p><?php _e('Accept Bitcoin instantly with Tinkl.it API', 'woothemes'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php

        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Abilita Tinkl.it', 'woocommerce'),
                    'label' => __('Enable Bitcoin payments via Tinkl.it', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The payment method title which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('Pay with Bitcoin', 'woocommerce'),
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('The payment method description which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default' => __('Powered by Tinkl.it'),
                ),
                'client_id' => array(
                    'title' => __('Client ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Your personal Client ID. See our API <a href="https://api.tinkl.it/doc/" target="_blank">here</a>.  ', 'woocommerce'),
                    'default' => (empty($this->get_option('client_id')) ? '' : $this->get_option('client_id')),
                ),
                'token' => array(
                    'title' => __('Token', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Your personal Token. See our API <a href="https://api.tinkl.it/doc/" target="_blank">here</a>.  ', 'woocommerce'),
                    'default' => (empty($this->get_option('token')) ? '' : $this->get_option('token')),
                ),
                'order_statuses' => array(
                    'type' => 'order_statuses'
                ),
				'test' => array(
                    'title' => __('API Test Mode', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Test Mode', 'woocommerce'),
                    'default' => 'yes',
                    'description' => __('To test on <a href="https://staging.tinklit.it" target="_blank">Tinkl.it Staging</a>, keep inserted "staging". 
                    Please note, for Test Mode you must create a separate account on <a href="https://staging.tinklit.it" target="_blank">staging.tinkl.it</a> and generate API credentials there. 
                    API credentials generated on <a href="https://tinkl.it" target="_blank">Tinkl.it</a> are "Live" credentials and will not work for "Test" mode.', 'woocommerce'),
                )
            );
        }

        public function thankyou()
        {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
        }

        public function process_payment($order_id)
        {			
		
			$order = wc_get_order($order_id);

            $this->init_tinklit();

            $description = array();
            foreach ($order->get_items('line_item') as $item) {
                $description[] = $item['qty'] . ' × ' . $item['name'];
            }
	
            $tinklit_invoice_guid = get_post_meta($order->get_id(), 'tinklit_invoice_guid', true);
			
            if (empty($tinklit_invoice_guid)) {
			
				                $order = \Tinklit\Merchant\Order::create(array(
                        'price'             => $order->get_total(),
                        'currency'          => get_woocommerce_currency(),
                        'deferred'          => false,
                        'time_limit'        => 900,
                        'order_id'          => $order->get_id(),
                        'item_code'         => implode($description, ', ') . ' (Order #' . $order->get_id(). ') ' . $order->get_formatted_billing_full_name() . ' ('. $order->get_billing_email() .')',
                        'notification_url'  => trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_tinklit',
                        'redirect_url'      => add_query_arg('order-received', $order->get_id(), add_query_arg('key', $order->get_order_key(), add_query_arg('custom_tinklit_response', "true", $this->get_return_url($order))))
                                                
                       
                ));

				
				$tinklit_order_guid = $order->guid;
				update_post_meta( $order_id, 'tinklit_invoice_guid', $tinklit_order_guid );

                update_post_meta( $order_id, 'tinklit_processing_order_id',  $order_id );
			
				if ($order && $order->url) {				
					return array(
						'result' => 'success',
						'redirect' => $order->url,
					);
				} else {
					return array(
						'result' => 'fail',
					);
				}
			} else {
                return array(
                    'result' => 'fail',
                );
            }			
        }
		
        public function payment_callback( $order_id, $guid )
        {
            $request = array();

            $request[ 'order_id' ] = $order_id;
            $request[ 'guid' ]     = $guid;

            global $woocommerce;

            $order = wc_get_order( $request[ 'order_id' ] );
			
            try {
                if (!$order || !$order->get_id()) {
                    throw new Exception('Order #' . $request['order_id'] . ' does not exists');
                }

                $guid = get_post_meta($order->get_id(), 'tinklit_invoice_guid', true);

                if (empty($guid) ) {
                    throw new Exception('Order has not a Tinkl.it Invoice GUID associated');
                }

                $this->init_tinklit();
                $tnklOrder = \Tinklit\Merchant\Order::find( $request['guid'] );
				
                if (!$tnklOrder) {
                    throw new Exception('Tinkl.it Order #' . $order->get_id() . ' does not exists');
                }
				 
		$orderStatuses = $this->get_option('order_statuses');
                $wcOrderStatus = $orderStatuses[$tnklOrder->status];
                $wcExpiredStatus = $orderStatuses['expired'];
                $wcErrorStatus = $orderStatuses['error'];
                $wcPartialStatus = $orderStatuses['partial'];
                $wcPayedStatus = $orderStatuses['payed'];

                update_post_meta( $request['order_id'], 'get_status', $tnklOrder->status  );

		switch ($tnklOrder->status) {
                    case 'payed':
                        $statusWas = "wc-" . $order->status;
						
			$order->update_status($wcOrderStatus);
                        $order->add_order_note(__('Payment is confirmed and has been credited to your Tinkl.it account. Purchased goods/services can be securely delivered to the buyer.', 'tinklit'));
                        $order->payment_complete();
						
                        if ($order->status == 'processing' && ($statusWas == $wcExpiredStatus || $statusWas == $wcErrorStatus)) {
                            WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                        }
                        if (($order->status == 'processing' || $order->status == 'completed') && ($statusWas == $wcExpiredStatus || $statusWas == $wcErrorStatus)) {
                            WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
                        }						
                        break;
		/*
                    case 'pending':
                        $order->update_status($wcOrderStatus);
                        $order->add_order_note(__('Customer has paid via Chain. Payment is awaiting the confirmations by Bitcoin network, do not send purchased goods/services yet.', 'tinklit'));
                        break;
		*/
		    case 'partial':
                        $order->update_status($wcOrderStatus);
                        $order->add_order_note(__('Payment has arrived, but not enough to cover the entire amount requested.', 'tinklit'));
                        break;
		    case 'expired':
                        $order->update_status($wcOrderStatus);
                        $order->add_order_note(__('Something went wrong! Payment is expired.', 'tinklit'));
                        break;
		    case 'error':
                        $order->update_status($wcOrderStatus);
                        $order->add_order_note(__('Payment rejected by the network or did not confirm.', 'tinklit'));
                        break;
                }
            } catch (Exception $e) {
                die(get_class($e) . ': ' . $e->getMessage());
            }
        }
		
		public function generate_order_statuses_html()
        {
            ob_start();

            $tnklStatuses = $this->tnklOrderStatuses();
            $wcStatuses = wc_get_order_statuses();
            $defaultStatuses = array('payed' => 'wc-processing', 'partial' => 'wc-processing', 'expired' => 'wc-cancelled', 'error' => 'wc-canceled');

            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">Tinkl.it Invoice / WC Order<br>Statuses:</th>
                <td class="forminp" id="tinklit_order_statuses">
                    <table cellspacing="0">
                        <?php
                        foreach ($tnklStatuses as $tnklStatusName => $tnklStatusTitle) {
                            ?>
                            <tr>
                                <th><?php echo $tnklStatusTitle; ?></th>
                                <td>
                                    <select name="woocommerce_tinklit_order_statuses[<?php echo $tnklStatusName; ?>]">
                                        <?php
                                        $orderStatuses = get_option('woocommerce_tinklit_settings');
                                        $orderStatuses = $orderStatuses['order_statuses'];

                                        foreach ($wcStatuses as $wcStatusName => $wcStatusTitle) {
                                            $currentStatus = $orderStatuses[$tnklStatusName];

                                            if (empty($currentStatus) === true)
                                                $currentStatus = $defaultStatuses[$tnklStatusName];

                                            if ($currentStatus == $wcStatusName)
                                                echo "<option value=\"$wcStatusName\" selected>$wcStatusTitle</option>";
                                            else
                                                echo "<option value=\"$wcStatusName\">$wcStatusTitle</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        public function validate_order_statuses_field()
        {
            $orderStatuses = $this->get_option('order_statuses');

            if (isset($_POST[$this->plugin_id . $this->id . '_order_statuses']))
                $orderStatuses = $_POST[$this->plugin_id . $this->id . '_order_statuses'];

            return $orderStatuses;
        }

        public function save_order_statuses()
        {
            $tnklOrderStatuses = $this->tnklOrderStatuses();
            $wcStatuses = wc_get_order_statuses();

            if (isset($_POST['woocommerce_tinklit_order_statuses']) === true) {
                $tnklSettings = get_option('woocommerce_tinklit_settings');
                $orderStatuses = $tnklSettings['order_statuses'];

                foreach ($tnklOrderStatuses as $tnklStatusName => $tnklStatusTitle) {
                    if (isset($_POST['woocommerce_tinklit_order_statuses'][$tnklStatusName]) === false)
                        continue;

                    $wcStatusName = $_POST['woocommerce_tinklit_order_statuses'][$tnklStatusName];

                    if (array_key_exists($wcStatusName, $wcStatuses) === true)
                        $orderStatuses[$tnklStatusName] = $wcStatusName;
                }

                $tnklSettings['order_statuses'] = $orderStatuses;
                update_option('woocommerce_tinklit_settings', $tnklSettings);
            }
        }

        private function tnklOrderStatuses()
        {
            return array('payed' => 'Payed', 'partial' => 'Partial', 'expired' => 'Expired', 'error' => 'Error');
        }

        private function init_tinklit()
        {
            \Tinklit\Tinklit::config(
                array(
                    'client_id'    	=> (empty($this->client_id) ? $this->client_id : $this->client_id),
					'token'    		=> (empty($this->token) ? $this->token : $this->token),
					'environment'   => ($this->test === 'yes' ? 'staging' : 'live'),
                    'user_agent'    => ('Tinkl.it - WooCommerce v' . WOOCOMMERCE_VERSION . ' Plugin v' . TINKLIT_WOOCOMMERCE_VERSION)
                )
            );
        }
    }

    function add_tinklit_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Tinklit';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_tinklit_gateway');
}
