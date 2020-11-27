<?php
/*
  Plugin Name: Hyperpay Payment Gateway plugin for WooCommerce
  Plugin URI:
  Description: Hyperpay is the first one stop-shop service company for online merchants in MENA Region.<strong>If you have any question, please <a href="http://www.hyperpay.com/" target="_new">contact Hyperpay</a>.</strong>
  Version: 1.0
  Author: Hyperpay Team
  Ported to Oppwa By : Hyperpay Team

 */

add_filter('woocommerce_payment_gateways', 'hyperpay_add_gateway_class');
function hyperpay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Hyperpay_Gateway'; // your class name is here
    return $gateways;
}

register_activation_hook(__FILE__, 'install');

function install()
{
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    global $wpdb;
    $sql = "CREATE TABLE wp_woocommerce_saving_cards (
        id INT AUTO_INCREMENT,
         registration_id VARCHAR(255) NOT NULL,
         customer_id VARCHAR(255) NOT NULL,
         mode int (10) NOT NULL,
         PRIMARY KEY (id)
     )  ENGINE=INNODB;";

    dbDelta($sql);
}


add_action('plugins_loaded', 'hyperpay_init_gateway_class');

function hyperpay_init_gateway_class()
{

    class WC_Hyperpay_Gateway extends WC_Payment_Gateway
    {
        protected $msg = array();
        protected $is_registered_user = false; // If the user is not a registerd user then no need to store the card.
        public function __construct()
        {
            $this->id = 'hyperpay';
            $this->has_fields = false;
            $this->method_title = 'Hyperpay Gateway';
            $this->method_description = 'Hyperpay Plugin for Woocommerce';

            $this->init_form_fields();
            $this->init_settings();

            $this->script_url = "https://oppwa.com/v1/paymentWidgets.js?checkoutId=";
            $this->token_url = "https://oppwa.com/v1/checkouts";
            $this->transaction_status_url = "https://oppwa.com/v1/checkouts/##TOKEN##/payment";
            $this->script_url_test = "https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=";
            $this->token_url_test = "https://test.oppwa.com/v1/checkouts";
            $this->transaction_status_url_test = "https://test.oppwa.com/v1/checkouts/##TOKEN##/payment";
/*
             * Refund url
             *
             * */
            $this->refund_url = "https://oppwa.com/v1/payments/";
            $this->refund_url_test = "https://test.oppwa.com/v1/payments/";
            /*
             * Add refund support
             */
            $this->supports           = array(
                'products',
                'refunds'
            );

            $this->testmode = $this->settings['testmode'];
            $this->title = $this->settings['title'];
            $this->trans_type = $this->settings['trans_type'];
            $this->trans_mode = $this->settings['trans_mode'];
            $this->accesstoken = $this->settings['accesstoken'];
            $this->entityid = $this->settings['entityId'];
            $this->brands = $this->settings['brands'];
            $this->connector_type = $this->settings['connector_type'];
            $this->payment_style = $this->settings['payment_style'];
            $this->mailerrors = $this->settings['mailerrors'];
            $this->lang = $this->settings['lang'];

            $lang = explode('-', get_bloginfo('language'));
            $lang = $lang[0];
            $this->lang  = $lang;

            $this->tokenization = $this->settings['tokenization'];

            $this->redirect_page_id = $this->settings['redirect_page_id'];


            if ($lang == 'ar') {
                $this->failed_message = 'تم رفض العملية ';

                $this->success_message = 'تم إجراء عملية الدفع بنجاح.';
            } else {
                $this->failed_message = 'Your transaction has been declined.';

                $this->success_message = 'Your payment has been procssed successfully.';
            }
            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_hyperpay', array(&$this, 'receipt_page'));
        }

        public function init_form_fields()
        {
            $postbackURL = get_option('siteurl');
            $successURL = $postbackURL . '?hyperpay_callback=1&success=1';
            $failURL = $postbackURL . '?hyperpay_callback=1&fail=1';


            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Hyperpay Payment Module.'),
                    'default' => 'no'
                ),
                'lang' => array(
                    'title' => __('Language'),
                    'type' => 'select',
                    'options' => array('en' => __('English'), 'ar' => __('Arabic')),
                    'description' => 'Form Language',
                ),
                'testmode' => array(
                    'title' => __('Test mode'),
                    'type' => 'select',
                    'options' => array('0' => __('Off'), '1' => __('On')),
                    'description' => '',
                ),
                'title' => array(
                    'title' => __('Title:'),
                    'type' => 'text',
                    'description' => ' ' . __('This controls the title which the user sees during checkout.'),
                    'default' => __('Credit Cards')
                ),
                'trans_type' => array(
                    'title' => __('Transaction type'),
                    'type' => 'select',
                    'options' => $this->get_hyperpay_trans_type(),
                    'description' => ''
                ),
                'trans_mode' => array(
                    'title' => __('Transaction mode'),
                    'type' => 'select',
                    'options' => $this->get_hyperpay_trans_mode(),
                    'description' => ''
                ),
                'connector_type' => array(
                    'title' => __('Connector Type'),
                    'type' => 'select',
                    'options' => $this->get_hyperpay_connector_type(),
                    'description' => ''
                ),
                'accesstoken' => array(
                    'title' => __('Access Token'),
                    'type' => 'text',
                    'description' => ''
                ),
                'entityId' => array(
                    'title' => __('Entity ID'),
                    'type' => 'text',
                    'description' => ''
                ),
                'tokenization' => array(
                    'title' => __('Tokenization'),
                    'type' => 'select',
                    'options' => $this->get_hyperpay_tokenization(),
                    'description' => ''
                ),
                'brands' => array(
                    'title' => __('Brands'),
                    'type' => 'multiselect',
                    'options' => $this->get_hyperpay_payment_methods(),
                    'description' => ''
                ),
                'payment_style' => array(
                    'title' => __('Payment Style'),
                    'type' => 'select',
                    'options' => $this->get_hyperpay_payment_style(),
                    'description' => ''
                ),
                'mailerrors' => array(
                    'title' => __('Enable error logging by email?'),
                    'type' => 'checkbox',
                    'label' => __('Yes'),
                    'default' => 'no',
                    'description' => __('If checked, an email will be sent to ' . get_bloginfo('admin_email') . ' whenever a callback fails.'),
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
        }

        function get_hyperpay_tokenization()
        {
            $hyperpay_tokenization = array(
                'enable' => 'Enable',
                'disable' => 'Disable'
            );

            return $hyperpay_tokenization;
        }
        function get_hyperpay_trans_type()
        {
            $hyperpay_trans_type = array(
                'DB' => 'Debit',
                'PA' => 'Pre-Authorization'
            );

            return $hyperpay_trans_type;
        }

        function get_hyperpay_trans_mode()
        {
            $hyperpay_trans_mode = array(
                'CONNECTOR_TEST' => 'CONNECTOR_TEST',
                'INTEGRATOR_TEST' => 'INTEGRATOR_TEST',
                'LIVE' => 'LIVE'
            );

            return $hyperpay_trans_mode;
        }

        function get_hyperpay_connector_type()
        {
            $hyperpay_connector_type = array(
                'MPGS' => 'MPGS',
                'VISA_ACP' => 'VISA_ACP'
            );

            return $hyperpay_connector_type;
        }

        function get_hyperpay_payment_methods()
        {
            $hyperpay_payments = array(
                'VISA' => 'Visa',
                'MASTER' => 'Master Card',
                'AMEX' => 'American Express',
                'MADA' => 'Mada',
                'STC_PAY' => 'STCPay',

            );

            return $hyperpay_payments;
        }

        function get_hyperpay_payment_style()
        {
            $hyperpay_payment_style = array(
                'card' => 'Card',
                'plain' => 'Plain'
            );

            return $hyperpay_payment_style;
        }

        function receipt_page($order)
        {
            global $woocommerce;
            $order = new WC_Order($order);
            $error = false; // used to rerender the form in case of an error

            if (isset($_GET['g2p_token'])) {
                $token = $_GET['g2p_token'];
                $this->renderPaymentForm($order, $token);
            }

            if (isset($_GET['id'])) {
                $token = $_GET['id'];

                if ($this->testmode == 0) {
                    $url = $this->transaction_status_url;
                } else {
                    $url = $this->transaction_status_url_test;
                }

                $url = str_replace('##TOKEN##', $token, $url);
                $url .= "?entityId=" . $this->entityid;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization:Bearer ' . $this->accesstoken
                ));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                $resultPayment = curl_exec($ch);
                curl_close($ch);
                $resultJson = json_decode($resultPayment, true);

                $sccuess = 0;
                $failed_msg = '';
                $orderid = '';

                if (isset($resultJson['result']['code'])) {
                    $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
                    $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
                    //success status
                    if (preg_match($successCodePattern, $resultJson['result']['code']) || preg_match($successManualReviewCodePattern, $resultJson['result']['code'])) {
                        $sccuess = 1;
                    } else {
                        //fail case
                        $failed_msg = $resultJson['result']['description'];
                    }
                    $orderid = '';

                    if (isset($resultJson['merchantTransactionId'])) {
                        $orderid = explode('_', $resultJson['merchantTransactionId']);
                        $orderid = $orderid[0];
                    }

                    $order_response = new WC_Order($orderid);
                    if ($this->lang == 'ar') {
                        echo  " <style>
                            .woocommerce-error {
                            text-align: right;
                            }
                            </style>
                            ";
                    }
                    if ($order_response) {
                        if ($sccuess == 1) {
                            WC()->session->set('hp_payment_retry', 0);
                            if ($order->status != 'completed') {
                                $order->payment_complete();
                                $woocommerce->cart->empty_cart();


                                $uniqueId = $resultJson['id'];
                                update_post_meta($order->get_id(),'hyperpay_uniqueId',$uniqueId);

                                if (isset($resultJson['registrationId'])) {

                                    $registrationID = $resultJson['registrationId'];

                                    $customerID = $order->get_customer_id();
                                    global $wpdb;

                                    $registrationIDs = $wpdb->get_results(
                                        "
                                                                         SELECT * 
                                                                     FROM wp_woocommerce_saving_cards
                                                                         WHERE registration_id ='$registrationID'
                                                                         and mode = '" . $this->testmode . "'
                                                                         "
                                    );

                                    if (count($registrationIDs) == 0) {

                                        $wpdb->insert(
                                            'wp_woocommerce_saving_cards',
                                            array(
                                                'customer_id' => $customerID,
                                                'registration_id' => $registrationID,
                                                'mode' => $this->testmode,
                                            )

                                        );
                                    }
                                }



                                $order->add_order_note($this->success_message . 'Transaction ID: ' . $uniqueId);
                                //unset($_SESSION['order_awaiting_payment']);


                            }

                            wp_redirect($this->get_return_url($order));


                            /* return array('result'   => 'success',
                              'redirect'  => get_site_url().'/checkout/order-received/'.$order->id.'/?key='.$order->order_key );
                             */
                        } else {
                            $order->add_order_note($this->failed_message . $failed_msg);
                            $order->update_status('cancelled');

                            if ($this->lang == 'ar') {
                                wc_add_notice(__('حدث خطأ في عملية الدفع والسبب <br/>' . $failed_msg . '<br/>' . 'يرجى المحاولة مرة أخرى'), 'error');
                            } else {
                                wc_add_notice(__('(Transaction Error) ' . $failed_msg), 'error');
                            }
                            wc_print_notices();
                            $error = true;
                        }
                    } else {
                        $order->add_order_note($this->failed_message);
                        $order->update_status('cancelled');
                        if ($this->lang == 'ar') {
                            wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                        } else {
                            wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                        }
                        wc_print_notices();
                        $error = true;
                    }
                } else {
                    $order->add_order_note($this->failed_message);
                    $order->update_status('cancelled');

                    if ($this->lang == 'ar') {
                        wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                    } else {
                        wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                    }
                    wc_print_notices();
                    $error = true;
                }
            }
        }

        private function renderPaymentForm($order, $token = '')
        {
            if ($token) {
                $token = $token;

                $order_id = $order->get_id();

                if ($this->testmode == 0) {
                    $scriptURL = $this->script_url;
                } else {
                    $scriptURL = $this->script_url_test;
                }

                $scriptURL .= $token;

                $payment_brands = implode(' ', $this->brands);

                $postbackURL = $order->get_checkout_payment_url(true);


                echo '<script src="https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.4.1.min.js"></script>';
?>
                <script>
                    var wpwlOptions = {

                        onReady: function() {
                            <?php
                            if ($this->tokenization == 'enable') {
                            ?>

                                var storeMsg = 'Store payment details?';
                                var style = 'style="direction: ltr"';
                                if (wpwlOptions.locale == "ar") {
                                    storeMsg = ' هل تريد حفظ معلومات البطاقة ؟';
                                    style = 'style="direction: rtl"';
                                }
                                var createRegistrationHtml = '<div class="customLabel style ="' + style + '">' + storeMsg +
                                    '</div><div class="customInput style ="' + style + '""><input type="checkbox" name="createRegistration" value="true" /></div>';
                                $('form.wpwl-form-card').find('.wpwl-button').before(createRegistrationHtml);
                            <?php } ?>



                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-qrcode').hide();
                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile').hide();
                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-paymentMode').hide();
                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-group-mobilePhone').show();
                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').attr('checked', true);
                            $('.wpwl-form-virtualAccount-STC_PAY .wpwl-wrapper-radio-mobile .wpwl-control-radio-mobile').trigger('click');

                        },
                        "style": "<?php echo $this->payment_style  ?>",
                        "locale": "<?php echo  $this->lang ?>",
                        "paymentTarget": "_top",
                        "registrations": {
                            "hideInitialPaymentForms": "true",
                            "requireCvv": "true"
                        }




                    }
                </script>

<?php
                if ($this->lang == 'ar') {
                    echo '<style>
				.wpwl-group{
				direction:ltr !important;
                }
                
			  </style>';
                };

                echo '<script  src="' . $scriptURL . '"></script>
		        <form action="' . $postbackURL . '" class="paymentWidgets">
		          ' . $payment_brands . '
		        </form>';
            }
        }

        /**
         * Process the payment and return the result
         * */
        public function process_payment($order_id)
        {
            global $woocommerce;


            $order = new WC_Order($order_id);

            if ($order->get_customer_id() > 0 && get_current_user_id() == $order->get_customer_id()) {
                //Registered
                $this->is_registered_user = true;
            } else {
                //Guest
                $this->is_registered_user = false;
            }

            if ($this->testmode == 0) {
                $url = $this->token_url;
            } else {
                $url = $this->token_url_test;
            }

            $orderAmount = number_format($order->get_total(), 2, '.', '');

            $orderid = $order_id;

            $accesstoken = $this->accesstoken;
            $entityid = $this->entityid;
            $mode = $this->trans_mode;
            $type = $this->trans_type;
            $amount = number_format(round($orderAmount, 2), 2, '.', '');
            $currency = get_woocommerce_currency();
            $transactionID = $orderid;
            $firstName = $order->get_billing_first_name();
            $family = $order->get_billing_last_name();
            $street = $order->get_billing_address_1();
            $zip = $order->get_billing_postcode();
            $city = $order->get_billing_city();
            $state = $order->get_billing_state();
            $country = $order->get_billing_country();
            $email = $order->get_billing_email();


            if (empty($state)) {
                $state = $city;
            }

            $data = "entityId=$entityid" .
                "&amount=$amount" .
                "&currency=$currency" .
                "&paymentType=$type" .
                "&merchantTransactionId=$transactionID" .
                "&customer.email=$email";

            if ($mode == 'CONNECTOR_TEST') {
                $data .= "&testMode=EXTERNAL";
            }

            if ($this->connector_type == 'VISA_ACP') {

                $data .= "&customer.givenName=$firstName";
                $data .= "&customer.surname=$family";
                $data .= "&billing.street1=$street";
                $data .= "&billing.city=$city";
                $data .= "&billing.state=$state";
                $data .= "&billing.country=$country";
            }
            $data .= "&customParameters[branch_id]=1";
            $data .= "&customParameters[teller_id]=1";
            $data .= "&customParameters[device_id]=1";
            $data .= "&customParameters[bill_number]=$transactionID";


            if ($this->tokenization == 'enable' && $this->is_registered_user == true) {

                //$data .=  "&createRegistration=true";
                global $wpdb;
                $customerID = $order->get_customer_id();
                $registrationIDs = $wpdb->get_results("SELECT * FROM wp_woocommerce_saving_cards WHERE customer_id =$customerID and mode = '" . $this->testmode . "'");
                if ($registrationIDs) {

                    foreach ($registrationIDs as $key => $id) {
                        $data .= "&registrations[$key].id=" . $id->registration_id;
                    }
                }
            }


            $customerID = $order->get_customer_id();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization:Bearer ' . $accesstoken
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem with $url, $php_errormsg", 'error');
            }
            curl_close($ch);
            if ($response === false) {
                wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem reading data from $url, $php_errormsg", 'error');
            }

            $result = json_decode($response);


            $token = '';

            if (isset($result->id)) {
                $token = $result->id;
            }

            return array(
                'result' => 'success',
                'token' => $token,
                'redirect' => add_query_arg('g2p_token', $token, $order->get_checkout_payment_url(true))
            );
        }

        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();

            if ($title)
                $page_list[] = $title;

            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        /*
          * Refund Function
          *
          * */
        function process_refund($order_id, $amount = NULL, $reason = ''){
            /*
             * Roles Restriction
             * */
            if(!current_user_can('administrator')) return false;

            $order  = wc_get_order( $order_id );
            $trans = $order->get_meta('hyperpay_uniqueId');

            if ($this->testmode == 0) {
                $url = $this->refund_url.$trans;
            } else {
                $url = $this->refund_url_test.$trans;
            }

            $data = "entityId=".$this->entityid.
                "&amount=".$amount.
                "&currency=".$order->get_currency().
                "&paymentType=".'RF';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization:Bearer '.$this->accesstoken));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseData = curl_exec($ch);
            if(curl_errno($ch)) {
                return curl_error($ch);
            }
            curl_close($ch);
            $responseData_array = json_decode($responseData,true);
            $status = 'Hyperpay Refund : ';
            $rf_status = false;

            if(isset($responseData_array['result']['code'])){
                $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
                $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
                if (preg_match($successCodePattern, $responseData_array['result']['code']) || preg_match($successManualReviewCodePattern, $responseData_array['result']['code'])){
                    $status = $status . $responseData_array['result']['description'] . ' . Amount : ' .$responseData_array['amount'];
                    $rf_status = true;
                }
                else {
                    $status = $status . "Failed";
                    $rf_status = false;
                }
            }

            $order->add_order_note($status);
            if($rf_status){
                $order->add_order_note(__('Hyperpay : Refunded successfully. Refund Amount : ').$amount.$order->get_currency().__('. Refund Reason : ').$reason);
            } else{
                $order->add_order_note(__('Hyperpay : Refund failed.'));
            }
            return $rf_status;
        }
    }
}
?>