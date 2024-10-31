<?php
function sk_crypto_name($slug){
    switch($slug) {
        case 'phuck':
            return 'PHUCK';
        case 'trx':
            return 'TRX';
        default:
            return 'error-do-not-make-any-transaction';
    }
}
function sk_get_store_address($coin_slug, $gateway) {
    switch($coin_slug) {
        case 'phuck':
            return $gateway->get_option("phuck_address");
        case 'trx':
            return $gateway->get_option("trx_address");
        default:
        return 'error-do-not-make-any-transaction';
    }
}