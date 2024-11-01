<?php

if (!defined('ABSPATH')) {
    exit;
}


function Load_Shatoot_Gateway(){
    session_start();
    
    if (!function_exists('Woocommerce_Add_Shatoot_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_Shatoot')) {
        
        //add payment methods
        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Shatoot_Gateway');

        function Woocommerce_Add_Shatoot_Gateway($methods)
        {
            $methods[] = 'WC_Shatoot';
            return $methods;
        }

        //IR currency
        add_filter('woocommerce_currencies', 'adding_IR_currency');

        function adding_IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }


        //IR currency symbole
        add_filter('woocommerce_currency_symbol', 'adding_IR_currency_symbol', 10, 2);

        function adding_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }


        class WC_Shatoot extends WC_Payment_Gateway
        {
            private $merchantCode;
            private $failedMessage;
            private $successMessage;

            public function __construct()
            {
                $this->id = 'WC_Shatoot';
                $this->method_title = __('پرداخت امن شاتوت', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت شاتوت برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_Shatoot_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->merchantCode = $this->settings['merchantcode'];
                $this->terminalId = $this->settings['terminalId'];
                $this->username = $this->settings['username'];
                $this->password = $this->settings['password'];

                 $this->successMessage = $this->settings['success_message'];
                 $this->failedMessage = $this->settings['failed_message'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Shatoot_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Shatoot_Gateway'));
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_Shatoot_Config', array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه شاتوت', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت شاتوت باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت امن شاتوت', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله داراکارت از طریق درگاه شاتوت', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب شاتوت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت کد', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('شناسه پذیرنده یا MerchantCode درگاه شاتوت', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'terminalId' => array(
                            'title' => __('شناسه ترمینال', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('شناسه ترمینال یا TerminalID درگاه شاتوت', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'username' => array(
                            'title' => __('نام کاربری', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('نام کاربری یا UserName اتصال به درگاه شاتوت', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'password' => array(
                            'title' => __('رمز عبور', 'woocommerce'),
                            'type' => 'password',
                            'description' => __('رمز عبور یا Password اتصال به درگاه شاتوت', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_message' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیام پرداخت موفقیت آمیز', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_message' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیام پرداخت ناموفق', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }


            public function SendRequestToShatoot($url,$params)
            {
               
                $args = array(
                    'body' => $params
                );
            
                try {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Shatoot Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'Content-Length: ' . strlen($params)
                    ));
                    $result = curl_exec($ch);
                    return json_decode($result, true);
                } catch (Exception $ex) {
                    return false;
                }
            }


            public function Send_to_Shatoot_Gateway($order_id)
            {

                global $woocommerce;
                $woocommerce->session->order_id_shatoot = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Shatoot_Currency', $currency, $order_id);

               
                $Amount = (int)$order->get_total();
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                $strToLowerCurrency = strtolower($currency);
                if (
                    ($strToLowerCurrency === strtolower('IRT')) ||
                    ($strToLowerCurrency === strtolower('TOMAN')) ||
                    $strToLowerCurrency === strtolower('Iran TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('تومان ایران'
                    )
                ) {
                    $Amount *= 10;
                } else if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 10000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRR')) {
                    $Amount *= 1;
                }


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_shatoot_gateway', $Amount, $currency);

            
                $products = array();
                $order_items = $order->get_items();
                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $MerchantID= sanitize_text_field($this->merchantCode);
                $TerminalID =  sanitize_text_field($this->terminalId);
                $UserName=  sanitize_text_field($this->username);
                $Password= sanitize_text_field($this->password);


                $URL = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Shatoot'));

                $RequestDateTime = date("Y-m-d h:i:s");
                $Amount = strval($Amount);
              
               

                $data = array(
                    'MerchantID' =>  $MerchantID, 
                    'TerminalID' => $TerminalID,
                    'UserName' => $UserName,
                    'Password' => MD5($UserName.$Password),
                    'RequestDateTime' => $RequestDateTime,
                    'URL' => $URL,
                    'Amount' => $Amount);

                $result = $this->SendRequestToShatoot('https://ipg.faash.ir/Shatoot/ipggettoken',json_encode($data));
                
                if($result['Token']){
                    $Token = $result['Token'];
                   $_SESSION["token"] = $Token;
                   $woocommerce->session->order_token = $Token;

                    $form = '<form action="https://ipg.faash.ir/Shatoot/shatootipg.jsp" method="POST" class="shatoot-checkout-form" id="shatoot-checkout-form">
                        <input type="hidden" value="'.$TerminalID.'" name="TerminalID">
                        <input type="hidden" value="'.$Amount.'" name="Amount">
                        <input type="hidden" value="'.$Token.'" name="Token" id="token">
                            <input type="submit" class="button alt" id="shatoot-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
                            <a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
                         </form><br/>';
                    $form = apply_filters('WC_Shatoot_Form', $form, $order_id, $woocommerce);
    
                    do_action('WC_Shatoot_Gateway_Before_Form', $order_id, $woocommerce);
                    echo $form;
                    do_action('WC_Shatoot_Gateway_After_Form', $order_id, $woocommerce);
                }



                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Shatoot_Send_to_Gateway_Failed_Note', $Note, $order_id);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Shatoot_Send_to_Gateway_Failed_Notice', $Notice, $order_id);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_Shatoot_Send_to_Gateway_Failed', $order_id);
                }
            }





            public function Return_from_Shatoot_Gateway()
            {
                



                global $woocommerce;
                $order_id = $woocommerce->session->order_id_shatoot;

                

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Shatoot_Currency', $currency, $order_id);
              
                
                            

                $url='https://ipg.faash.ir/Shatoot/ipgquery';
                $TerminalID =  $this->terminalId;
                $UserName=  $this->username;
                $Password= md5($this->username.$this->password);
                $Token = sanitize_text_field($woocommerce->session->order_token ? $woocommerce->session->order_token : $_SESSION["token"]);
                 
                
                $data = array('TerminalID' => $TerminalID, 'UserName' => $UserName, 'Password' => $Password,'Token'=>$Token);
                $result = $this->SendRequestToShatoot($url, json_encode($data));


                wp_redirect(add_query_arg('token', $Token, $this->get_return_url($order)));

                
                if ($result['ResultCode']==0) {
                                $Status = 'completed';


                                //purchase info
                                $TerminalID = $result['PurchaseInfo']['TerminalID'];
                                $RRN = $result['PurchaseInfo']['RRN'];
                                $Amount = $result['PurchaseInfo']['Amount'];
                                $AmountAfterDiscount = $result['PurchaseInfo']['AmountAfterDiscount'];
                                $PurchaseDateTime = $result['PurchaseInfo']['PurchaseDateTime'];
                                $OrderStatus = $result['PurchaseInfo']['Status'];
                                $CampaignID = $result['PurchaseInfo']['CampaignID'];
                                $CampaignName = $result['PurchaseInfo']['CampaignName'];

        


                        if ($Status === 'completed') {
                            update_post_meta($order_id, '_terminal_id', $TerminalID);
                            update_post_meta($order_id, '_rrn', $RRN);
                            update_post_meta($order_id, '_amount', $Amount);
                            update_post_meta($order_id, '_amount_after_discount', $AmountAfterDiscount);
                            update_post_meta($order_id, '_purchase_date_time', $PurchaseDateTime);
                            update_post_meta($order_id, '_status', $OrderStatus);
                            update_post_meta($order_id, '_campaign_id', $CampaignID);
                            update_post_meta($order_id, '_campaign_name', $CampaignName);
                       


                            $order->payment_complete();
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $RRN);
                            $Note = apply_filters('WC_Shatoot_Return_from_Gateway_Success_Note', $Note, $order_id, $RRN);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMessage));

    
                            wc_add_notice($Notice, 'success');

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }else{

                        $Note = sprintf(__('خطا در هنگام بازگشت از بانک.<br/> کد رهگیری : %s', 'woocommerce'), $RRN);

                        $Note = apply_filters('WC_Shatoot_Return_from_Gateway_Failed_Note', $Note, $order_id, $RRN);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMessage));

                            wc_add_notice($Notice, 'error');
                    
                        wp_redirect(wc_get_checkout_url());
                        exit;
                
                   }
                            } 
                            else {
                                $Notice = wpautop(wptexturize($this->failedMessage));
                                wc_add_notice($Notice, 'error');
                                wp_redirect(wc_get_checkout_url());
                                exit;
                            }
            }

    }

    }   
}

add_action('plugins_loaded', 'Load_Shatoot_Gateway', 0);

?>