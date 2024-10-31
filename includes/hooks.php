<?php

//CREATE PHUCK PAYMENT CLASS
add_action('plugins_loaded', 'phuck_payment_gateway_init', 11);
function phuck_payment_gateway_init()
{
    class Phuck_Payment_Gateway extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway.
         */
        private $selected_crypto = 'phuck';
        private $amount_of_crypto_to_pay = 0;
        private $current_cryto_price = 0;
        private $sender_address = '';
        public function __construct()
        {
            $this->id                 = 'phuck_payment';
            $this->icon               = plugin_dir_url(__DIR__) . "assets/phuck-icon.png";
            $this->has_fields         = true;
            $this->method_title       = "Phanes Payments";
            $this->method_description = "Get paid with PHUCK-TRX coin.";

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );


            // Method with all the options fields
            $this->init_form_fields();
            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->enabled = $this->get_option('enabled');
            $this->phuck_address = $this->get_option('phuck_address');
            $this->trx_address = $this->get_option('trx_address');
            // $this->testmode = 'yes' === $this->get_option('testmode');
            // $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            // $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );


            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            //This action hooks shows instruction message in order placed page
            add_action('woocommerce_thankyou_phuck_payment', array($this, 'thankyou_page'));

            // Customer Emails.
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

            // our custom javascript in payment_scripts
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {

            $this->form_fields = apply_filters('phuck_payment_form_fields', array(

                'enabled' => array(
                    'title'   => __('Enable/Disable', 'phuck-payment-gateway'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Phanes Payment', 'phuck-payment-gateway'),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title'       => __('Title', 'phuck-payment-gateway'),
                    'type'        => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'phuck-payment-gateway'),
                    'default'     => __('PHUCKS', 'phuck-payment-gateway'),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __('Description', 'phuck-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'phuck-payment-gateway'),
                    'default'     => __('You are paying with Phanes Crypto Payment Gateway. After your payment is confirmed, your order will be shipped.', 'phuck-payment-gateway'),
                    'desc_tip'    => true,
                ),

                'instructions' => array(
                    'title'       => __('Instructions', 'phuck-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'phuck-payment-gateway'),
                    'default'     => 'NOTE: To complete your order, you must make a payment directly to our PHUCK  address. Your order won’t be shipped until the funds have cleared in our account.',
                    'desc_tip'    => true,
                ),
                'phuck_address' => array(
                    'title'       => 'Store Phuck Address',
                    'type'        => 'text',
                    'description' => 'Phuck Address where all payment is being paid to. Please confirm its your correct address.',
                    'default'     => '',
                    'desc_tip'    => true,
                    'placeholder' => "Store PHUCK wallet address"
                ),
                'trx_address' => array(
                    'title'       => 'Store TRX Address',
                    'type'        => 'text',
                    'description' => 'Trx Address where all payment is being paid to. Please confirm its your correct address.',
                    'default'     => '',
                    'desc_tip'    => true,
                    'placeholder' => "Store TRX wallet address"
                ),
                // 'testmode' => array(
                //     'title'       => 'Test mode',
                //     'label'       => 'Enable Test Mode',
                //     'type'        => 'checkbox',
                //     'description' => 'Place the payment gateway in test mode (offline).',
                //     'default'     => 'no',
                //     'desc_tip'    => true,
                // ),
                // 'publishable_key' => array(
                //     'title'       => 'Live Publishable Key',
                //     'type'        => 'text'
                // ),
                // 'private_key' => array(
                //     'title'       => 'Live Private Key',
                //     'type'        => 'password'
                // )
            ));
        }

        /** PROCESS THE PAYMENT */
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);
            //selected crypto
            $order->update_meta_data('phanes_selected_crypto', $this->selected_crypto);
            //add address where the payment is comming from
            $order->update_meta_data('phanes_payment_address', $this->sender_address);
            //add the total crypto coin to be paid
            $order->update_meta_data('phanes_crypto_amount', $this->amount_of_crypto_to_pay);
            //current value of crypto in dollar then
            $order->update_meta_data('phanes_crypto_rate_then', $this->current_cryto_price);
            //add payment initiated date
            // 2021-08-02T21:03:48+00:00
            $order->update_meta_data('payment_initiated_date', date("Y-m-d\TH:i:sO"));
            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting payment of ' . $this->amount_of_crypto_to_pay . '-' . sk_crypto_name($this->selected_crypto) . ' from ' . $this->sender_address));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url($order)
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page()
        {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {

            if ($this->instructions && !$sent_to_admin && 'phuck_payment' === $order->payment_method && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }

        /**
         * You will need it if you want your custom credit card form,
         */
        public function payment_fields()
        {
            // ok, let's display some description before the payment form
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                // if ($this->testmode) {
                //     $this->description .= ' TEST MODE ENABLED. You are testing payment gateway offline.';
                //     $this->description  = trim($this->description);
                // }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-address-form" class="wc-credit-card-form wc-phuck-payment-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_phanes_address_form_start', $this->id);

            //GET CURRENT PHUCK RATE
            // $phuck_response = wp_remote_get('https://api.coinpaprika.com/v1/tickers/phuck-phucks');
            $phuck_response = wp_remote_get('https://api.dex-trade.com/v1/public/ticker?pair=PHUCKUSDT');
            $phuck_body     = wp_remote_retrieve_body($phuck_response);
            $phuck_body_result = json_decode($phuck_body, true);
            $phuck_price = $phuck_body_result['data']['last']; //float phuck price
            $phuck_price_usd = number_format((float)$phuck_price, 10, '.', '');
            

            if (empty($phuck_price) || is_null($phuck_price))
                $amount_of_phuck_to_pay =  'NULL';
            else
                $amount_of_phuck_to_pay =  round($this->get_order_total() / $phuck_price, 6);
            
            //GET CURRENT TRX RATE
            $trx_response = wp_remote_get('https://api.coinpaprika.com/v1/tickers/trx-tron');
            $trx_body     = wp_remote_retrieve_body($trx_response);
            $trx_body_result = json_decode($trx_body, true);
            $trx_price = $trx_body_result['quotes']['USD']['price']; //float phuck price
            $trx_price_usd = number_format((float)$trx_price, 10, '.', '');

            if (empty($trx_price) || is_null($trx_price))
                $amount_of_trx_to_pay = 'NULL';
            else
                $amount_of_trx_to_pay =  round($this->get_order_total() / $trx_price, 6);

            ?>
            <p class="form-row form-row-wide">
                <label for="wapg_altcoin_payment-alt-name">Please select coin you want to pay: <span class="required">*</span></label>
                <select name="phanes-selected-coin" id="phanes-selected-coin" onchange="skCoinChange(this);" class="select" style="width: 100%;">
                <!-- PHUCK -->
                <?php
                if ($amount_of_phuck_to_pay == 'NULL') { ?>
                    <option value="error" aria-valuetext="PHUCK">PHUCK(Error! refresh page.)</option>
                <?php } else { ?>
                    <option value="phuck" aria-valuetext="PHUCK" aria-details="<?php echo $amount_of_phuck_to_pay; ?>"
                     aria-current="<?php echo $phuck_price_usd; ?>">PHUCK(<?php echo $amount_of_phuck_to_pay; ?>)</option>
                <?php } ?>
                <!-- TRX -->
                <?php
                if ($amount_of_trx_to_pay == 'NULL') { ?>
                    <option value="error" aria-valuetext="TRX">TRX(Error! refresh page.)</option>
                <?php } else { ?>
                    <option value="trx" aria-valuetext="TRX" aria-details="<?php echo $amount_of_trx_to_pay; ?>"
                     aria-current="<?php echo $trx_price_usd; ?>">TRX(<?php echo $amount_of_trx_to_pay; ?>)</option>
                <?php } ?>
                </select>
            </p>

            <input type="hidden" name="amount-of-crypto-to-pay" id="amount-of-crypto-to-pay" value="<?php echo $amount_of_phuck_to_pay ?>">
            <input type="hidden" name="current-crypto-price" id="current-crypto-price" value="<?php echo $phuck_price_usd; ?>">

            <p class="form-row form-row-wide" id="phanes-sender-address">
                <label>Your <span id="crypto-wallet-name">PHUCK</span> wallet address <span class="required">*</span></label>
                <div>The address where your payment is comming from.</div>
	        	<input id="phanes-payment-address-input" name="phanes-payment-address-input" type="text" autocomplete="off" placeholder="Your payment wallet address" required>
	        	</p>
	        	<div class="clear"></div>

                <h3 style="text-align: center;"><strong>Where to buy PHUCK?</strong></h3>
                <center>
                    <a class="phanes-phuck-ads" href="https://bit.ly/3rq4CBK"><img src="<?php echo plugin_dir_url(__DIR__) . "assets/where-to-buy-phuck.png"; ?>"></a>
                </center>
            <?php

            do_action('woocommerce_phanes_address_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';
        }
        /*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
        public function payment_scripts()
        {
            //enquene the css and javascript here
        }
        /*
 		 * Fields validation
		 */
        public function validate_fields()
        {
            //user payment address is required
            if (empty($_POST['phanes-payment-address-input'])) {
                wc_add_notice('Your payment wallet address is required!', 'error');
                return false;
            }
            if ($_POST['phanes-selected-coin'] == "error") {
                wc_add_notice('Selected crypto market could not be fetch!', 'error');
                return false;
            }
            if ($_POST['amount-of-crypto-to-pay'] == "NULL" || empty($_POST['amount-of-crypto-to-pay'])) {
                wc_add_notice('Could not get coin value!', 'error');
                return false;
            }
            if ($_POST['current-crypto-price'] == "NULL" || empty($_POST['current-crypto-price'])) {
                wc_add_notice('Could not get coin price!', 'error');
                return false;
            }
            
            $this->selected_crypto = $_POST['phanes-selected-coin'];
            $this->sender_address = $_POST['phanes-payment-address-input'];
            $this->amount_of_crypto_to_pay = $_POST['amount-of-crypto-to-pay'];
            $this->current_cryto_price = $_POST['current-crypto-price'];
            

            $store_address = sk_get_store_address($this->selected_crypto, $this);
            // address must be set in admin
            if (empty($store_address)) {
                wc_add_notice('There is no address to receive ' . sk_crypto_name($this->selected_crypto) . ' at the moment!', 'error');
                return false;
            } else {
                //is the store address valid
                //IF STORE ADDRESS IS VALID IN TRONSCAN - so that there won't be wrong transfer to someone else
                $response = wp_remote_get('https://apilist.tronscan.org/api/account?address=' . $store_address);
                // $response_code = wp_remote_retrieve_response_code( $response );
                $body     = wp_remote_retrieve_body($response);
                $body_result = json_decode($body, true);
                if (isset($body_result['message']) || empty($body_result) || is_null($body_result)) {
                    wc_add_notice('Store ' . sk_crypto_name($this->selected_crypto) . ' wallet address could not receive any payment at the moment! INVALID', 'error');
                    return false;
                }
            }

            //IF SENDER ADDRESS IS VALID IN TRONSCAN
            $response = wp_remote_get('https://apilist.tronscan.org/api/account?address=' . $_POST['phanes-payment-address-input']);
            // $response_code = wp_remote_retrieve_response_code( $response );
            $body     = wp_remote_retrieve_body($response);
            $body_result = json_decode($body, true);
            if (isset($body_result['message']) || empty($body_result) || is_null($body_result)) {
                wc_add_notice('Invalid wallet address... Address cannot be found!', 'error');
                return false;
            }
        }
        /*
		 * In case you need a webhook, like PayPal IPN etc
		 */
        // public function webhook() {
        // }


    } //end of Phuck Payment Gateway

    function phuck_payment_add_to_gateways($gateways)
    {
        $gateways[] = 'Phuck_Payment_Gateway';
        return $gateways;
    }
    add_filter('woocommerce_payment_gateways', 'phuck_payment_add_to_gateways');
} //end of function phuck_payment_gateway_init()
//enquneue payment CSS and JAVASCRIPT
function phuck_payment_add_scripts()
{
    // we need JavaScript to process a token only on cart/checkout pages, right?
    if (/*!is_cart() && */!is_checkout() && !isset($_GET['pay_for_order']) && !is_wc_endpoint_url('view-order')) {
        return;
    }
    $payment_gateway = WC()->payment_gateways->payment_gateways()['phuck_payment'];

    // if our payment gateway is disabled, we do not have to enqueue JS too
    if ('no' === $payment_gateway->get_option("enabled")) {
        return;
    }

    wp_enqueue_style('phuck_payment_css', plugin_dir_url(__DIR__) . 'css/payment-style.css', false, '1.1', 'all');
    // in most case you might need to access wallet address in your javascript
    wp_enqueue_script('woocommerce_phuck_payment', plugin_dir_url(__DIR__) . "js/payment-script.js", array('jquery'));
    wp_localize_script('woocommerce_phuck_payment', 'phuck_payment_params', array(
        'phuckAddress' => $payment_gateway->get_option("phuck_address"),
        'trxAddress' => $payment_gateway->get_option("trx_address"),
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'phuck_payment_add_scripts');

add_action('woocommerce_admin_order_totals_after_total', 'phuck_payment_admin_order_totals_after_tax', 10, 1);
function phuck_payment_admin_order_totals_after_tax($order_id)
{
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() == "phuck_payment") {
        // Here set your data and calculations

        $label = __('Amount in ' . sk_crypto_name($order->get_meta('phanes_selected_crypto')), 'woocommerce');
        $value = $order->get_meta("phanes_crypto_amount") . " " . sk_crypto_name($order->get_meta('phanes_selected_crypto'));
        $wallet_address = $order->get_meta("phanes_payment_address");
        
        // Output
        ?>
        <tr>
            <td class="label"><?php echo $label; ?>:</td>
            <td width="1%"></td>
            <td class="total">
                <span class="woocommerce-Price-amount amount"><?php echo $value; ?></span>
            </td>
        </tr>
        
        <tr>
            <td class="label">Payment from:</td>
            <td width="1%"></td>
            <td class="total">
                <span class="woocommerce-Price-amount amount"><?php echo $wallet_address; ?></span>
            </td>
        </tr>
    <?php
    }
}
// add_action('woocommerce_before_thankyou', 'phuck_payment_gateway_confirmation_page', 4);
add_action('woocommerce_order_details_before_order_table', 'phuck_payment_gateway_confirmation_page', 20);
function phuck_payment_gateway_confirmation_page($order_id)
{
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() == "phuck_payment" && $order->get_status() == "on-hold") {
        $amount_of_crypto_to_pay = $order->get_meta("phanes_crypto_amount");
        $sender_address = $order->get_meta("phanes_payment_address");
        $crypto_price_usd = $order->get_meta("phanes_crypto_rate_then");

        $payment_gateway = WC()->payment_gateways->payment_gateways()['phuck_payment'];
        $store_wallet_address = sk_get_store_address($order->get_meta('phanes_selected_crypto'), $payment_gateway);
    ?>

        <div class="phuck-coin-detail">
            <h3 id="wapg_order_review_heading">To Complete your Order:</h3>
            <div class="phuck-coin-payment-details">

                <table class="shop_table woocommerce-checkout-review-order-table">
                    <tbody>
                        <tr class="cart_item">
                            <td class="product-name">
                                <?php echo sk_crypto_name($order->get_meta('phanes_selected_crypto')); ?>&nbsp;<strong class="product-quantity">× <?php echo $amount_of_crypto_to_pay; ?></strong>
                                <br><span class="price-tag"> 1 <?php echo $order->get_meta('phanes_selected_crypto'); ?> = $<?php echo $crypto_price_usd; ?> (USD) </span>
                            </td>

                            <td class="product-total"><span class="woocommerce-Price-amount amount">
                                    $<?php echo $order->get_total(); ?>
                                </span><br><span class="currency-fullname help-info"> ( United States (US) dollar ) </span></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="cart-subtotal">
                            <th>Total</th>
                            <td>
                                <span class="woocommerce-Price-amount amount"><?php echo $amount_of_crypto_to_pay; ?> - <span class="woocommerce-Price-currencySymbol"> <?php echo sk_crypto_name($order->get_meta('phanes_selected_crypto')); ?></span> </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <p class="form-row form-row-wide"><label for="alt-coinAddress">Please pay to following address:</label></p>
                <div class="phuck-address-details" style="text-align: center;">
                    <img class="qr-code" src="https://chart.googleapis.com/chart?chs=225x225&amp;cht=qr&amp;chl=tron:<?php echo $store_wallet_address; ?>?amount=<?php echo $amount_of_crypto_to_pay; ?>">
                    <div class="coinAddress-info">
                        <h3 style="text-align: center;"><strong><?php echo $amount_of_crypto_to_pay; ?></strong> <?php echo sk_crypto_name($order->get_meta('phanes_selected_crypto')); ?></h3>

                        <div class="input-group store-address-group">
                            <div class="input-group-area"><input class="input-text" id="store-address-input" type="text" value="<?php echo $store_wallet_address; ?>" readonly></div>
                            <div class="input-group-icon" id="copy-store-address">Copy</div>
                        </div>

                        <p class="awaiting-payment">Awaiting payment</p>
                        <input type="hidden" id="phanes-selected-coin" value="<?php echo $order->get_meta("phanes_selected_crypto"); ?>">
                        <input type="hidden" id="phanes-order-id" value="<?php echo $order->get_id(); ?>">
                        <input type="hidden" id="order-crypto-amount" value="<?php echo $order->get_meta("phanes_crypto_amount"); ?>">
                        <input type="hidden" id="order-date-time" value="<?php echo $order->get_meta("payment_initiated_date"); ?>">
                        <input type="hidden" id="order-sender-crypto-address" value="<?php echo $order->get_meta("phanes_payment_address"); ?>">
                        <center>
                            <div id="transaction-timer">00:00:00</div>
                        </center>

                        <p style="text-align: center;">
                            From:
                            <br>
                            <b><?php echo $sender_address; ?></b>
                        </p>


                    </div>
                </div>

                <h3 style="text-align: center;"><strong>Where to buy PHUCK?</strong></h3>
                <center>
                    <a class="phanes-phuck-ads" href="https://bit.ly/3rq4CBK"><img src="<?php echo plugin_dir_url(__DIR__) . "assets/where-to-buy-phuck.png"; ?>"></a>
                </center>

            </div>
        </div>
        <br><br>

    <?php
    } //end if is phuck payment method
}

add_filter('woocommerce_get_order_item_totals', 'custom_order_total_message_html', 10, 3);
function custom_order_total_message_html($total_rows, $order, $tax_display)
{
    if ($order->get_payment_method() == "phuck_payment") {
        $total_rows[] = array(
            'label' => "Amount in " . sk_crypto_name($order->get_meta('phanes_selected_crypto')),
            'value' => $order->get_meta("phanes_crypto_amount") . " " . sk_crypto_name($order->get_meta('phanes_selected_crypto'))
        );
    }

    return $total_rows;
}
 
add_action("wp_ajax_phuck_order_action", "phuck_order_action_ajax_function");
add_action("wp_ajax_nopriv_phuck_order_action", "phuck_order_action_ajax_function");
function phuck_order_action_ajax_function()
{
    // $result = "failed";
    $hash = $_POST['hash_code'];
    $order_id = $_POST['order_id'];
    $order = wc_get_order($order_id);
    $order->update_status('wc-processing', sk_crypto_name($order->get_meta('phanes_selected_crypto')) . " payment confirmed!");
    $order->save();
    echo 'success';

    // $payment_gateway = WC()->payment_gateways->payment_gateways()['phuck_payment'];
    // $store_wallet_address = sk_get_store_address($order->get_meta('phanes_selected_crypto'), $payment_gateway);
    // $sender_address = $order->get_meta("phanes_payment_address");
    // //   $phuck_amount = $order->get_meta("phuck_amount");
    // //   $trx_amount = $order->get_meta("phuck_trx_amount");

    
    // //confirm the hash in TRONSCAN
    // $response = wp_remote_get('https://apilist.tronscan.org/api/transaction-info?hash=' . $hash);
    // $body     = wp_remote_retrieve_body($response);
    // $transaction = json_decode($body, true);

    
    //     // PHUCK HASH CHECK TRC20
    //     if ($order->get_meta('phanes_selected_crypto') == 'phuck') {
    //         if (isset($transaction['trc20TransferInfo'])) {
    //             $info = $transaction["trc20TransferInfo"];
    //             if (
    //                 $info["to_address"] == $store_wallet_address
    //                 && $info['name'] == "PHUCKS"
    //                 && $info["from_address"] == $sender_address
    //                 && ($transaction['confirmed'] == true || $transaction['confirmed'] == "true")
    //                 && $transaction['contractRet'] == "SUCCESS"
    //             ) {
    //                 //success
    //                 $order->update_status('wc-processing', "PHUCK payment confirmed!");
    //                 $order->save();
    //                 $result = "success";
    //             } else {
    //                 $result = "failed";
    //             }
    //         } else {
    //             $result = "failed";
    //         }

    //     } else if ($order->get_meta('phanes_selected_crypto') == 'trx') {
    //         //TRX HASH CHECK TRC10
    //         $data = $transaction['contractData'];
    //         if (
    //             $data["owner_address"] == $sender_address
    //             && $data['to_address'] == $store_wallet_address
    //             && ($transaction['confirmed'] == true || $transaction['confirmed'] == "true")
    //             && $transaction['contractRet'] == "SUCCESS"
    //         ) {
    //             //success
    //             $order->update_status('wc-processing', "TRX payment confirmed!");
    //             $order->save();
    //             $result = "success";
    //         } else {
    //             $result = "failed";
    //         }
    //     } else {
    //         $result = 'failed';
    //     }



    // echo $result;


    wp_die(); // ajax call must die to avoid trailing 0 in your response
}
add_action("wp_ajax_phuck_order_expire_action", "phuck_order_expire_action_ajax_function");
add_action("wp_ajax_nopriv_phuck_order_expire_action", "phuck_order_expire_action_ajax_function");
function phuck_order_expire_action_ajax_function()
{
    $order_id = $_POST['order_id'];
    $order = wc_get_order($order_id);

    $order->update_status('wc-pending', "Can't confirm payment! Order changed to pending.");
    $order->save();

    echo "order-updated";

    wp_die(); // ajax call must die to avoid trailing 0 in your response
}

// add_action('init', function () {
//     //testing
    
// });
