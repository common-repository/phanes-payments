<?php

// ON Activating plugin
//setup database
if (!function_exists('phuck_payment_gateway_activated')) {
    function phuck_payment_gateway_activated() {
    set_transient( 'phuck-payment-activated', true, 5);

    // action to perform on activated
    //register store to givephuck.com
    // "localhost/wp-json/stores/add?name=seun.com&address=seun.com&description=desc&email=@seun.com";
    $givephuck = "https://givephuck.com/wp-json/stores/add"; //route
    $body = array(
        'name' => get_bloginfo('name'),
        'address' => get_bloginfo( 'url'),
        'description' => get_bloginfo( 'description'),
        'email' => get_bloginfo( 'admin_email')
    );
    $args = array(
        'body'        => $body,
        // 'timeout'     => '5',
        // 'redirection' => '5',
        // 'httpversion' => '1.0',
        // 'blocking'    => true,
        // 'headers'     => array(),
        // 'cookies'     => array(),
    );
    $response = wp_remote_post( $givephuck, $args );
    set_transient( 'phuck-payment-activated', true, 5);
    }
}
//welcome message
add_action( 'admin_notices', function() {
   if (get_transient( 'phuck-payment-activated' )) { ?>
        <div class="updated notice is-dismissible">
            <p>Thank you for using <strong>Phanes Payment Gateway</strong>... You can now receive <strong>Phuck Coins</strong> as a payment for your goods/services.</p>
        </div>
   <?php 
   //to only display this once
   delete_transient( 'phuck-payment-activated' );
   }
});

//deactivation
if (!function_exists('phuck_payment_gateway_deactivated')) {
    function phuck_payment_gateway_deactivated() {
        global $current_user;
	    $user_id = $current_user->ID;

        // localhost/wp-json/stores/deactivate/taye.com
        $givephuck = "https://givephuck.com/wp-json/stores/deactivate"; //route
        $body = array(
            'address' => get_bloginfo( 'url')
        );
        $args = array(
            'body'        => $body,
        );
        $response = wp_remote_post( $givephuck, $args );

        

        delete_user_meta( $user_id, 'phanes_comp_notice_dismiss');
    }
}

//competition banner
add_action( 'admin_notices', function() {
    global $current_user;
	
	$user_id = $current_user->ID;
    if (!get_user_meta($user_id, 'phanes_comp_notice_dismiss')) {
     ?>
         <div class="updated notice">
             <p style="font-size: 18px;">Feeling Lucky? <strong>First 100 merchants</strong> that use <img src="<?php echo plugin_dir_url("") . "phanes-payment-gateway/assets/phanes-icon.png"; ?>"> <strong>Phanes Payment to accept PHUCKS</strong> <img src="<?php echo plugin_dir_url("") . "phanes-payment-gateway/assets/phuck-icon.png"; ?>"> will <strong>receive $250 bonus</strong> to sign up in PHUCKS. <br> For more info, <a href="https://givephuck.com/accept-phucks" class="buton buttn-small">click here</a>. 
             &nbsp; &nbsp; Your store has been registered! 
             &nbsp; &nbsp; &nbsp; &nbsp;
              <a href="?phanes-dismiss-comp-notice" class="button button-small">Dismiss</a></p>
         </div>
    <?php
    }
});
add_action('admin_init', function() {
    //catch if dismiss button is clicked
    global $current_user;
	
	$user_id = $current_user->ID;

    if (isset($_GET['phanes-dismiss-comp-notice'])) {
		add_user_meta($user_id, 'phanes_comp_notice_dismiss', 'true', true);
	}
});