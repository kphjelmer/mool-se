<?php
/**
 * Plugin Name: Mool Performance
 * Description: Förhindrar att reCAPTCHA laddas flera gånger. Aktiverar WPForms No-Conflict Mode.
 */

// Aktivera WPForms No-Conflict Mode programmatiskt.
// WPForms avregistrerar då alla andra plugins' reCAPTCHA-skript och hanterar det själv.
add_filter( 'pre_option_wpforms_settings', function( $value ) {
    if ( is_array( $value ) ) {
        $value['recaptcha-noconflict'] = '1';
    }
    return $value;
} );
