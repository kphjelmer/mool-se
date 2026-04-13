<?php

/**
 * Twenty Twenty-Four Child – custom functions
 *
 * OBS: Lägg bara egen kod här. Parent-temat (twentytwentyfour) ska vara orört.
 */

/**
 * Google Tag Manager – GTM-5JNVFKDV
 * Snippet i <head> (måste vara så tidigt som möjligt)
 */
add_action('wp_head', function () {
    ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-5JNVFKDV');</script>
<!-- End Google Tag Manager -->
    <?php
}, 1); // Prio 1 = laddas före allt annat i <head>

/**
 * Google Tag Manager – noscript-fallback direkt efter <body>
 */
add_action('wp_body_open', function () {
    ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5JNVFKDV"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <?php
}, 1);

/**
 * Ladda styles korrekt (parent först, child ovanpå).
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('twentytwentyfour-parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('twentytwentyfour-child-style', get_stylesheet_uri(), array('twentytwentyfour-parent-style'), '1.0');
}, 20);

/**
 * === Loopia Boost optimering för WordPress/Woo ===
 * - Tillåter sidcache för icke-Woo-sidor
 * - Tar bort tunga Woo/AJAX-skript på landningssidor
 * - Förhindrar onödiga cookies som stoppar cache
 */

// 1. Stoppa WooCommerce Cart Fragments (som sätter cookies)
add_filter('woocommerce_disable_cart_fragmentation', '__return_true');
add_action('wp_enqueue_scripts', function () {
    if (!is_cart() && !is_checkout() && !is_account_page()) {
        wp_dequeue_script('wc-cart-fragments');
    }
}, 100);

// 2. Hindra WooCommerce från att starta sessioner på vanliga sidor
add_action('init', function () {
    if (!is_admin() && !is_cart() && !is_checkout() && !is_account_page()) {
        if (function_exists('wc()->session')) {
            wc()->session = null;
        }
        remove_action('init', 'wc_session_start');
    }
}, 1);

// 3. Ta bort Stripe och andra Woo-skript på icke-Woo-sidor
add_action('wp_enqueue_scripts', function () {
    if (!is_cart() && !is_checkout() && !is_account_page()) {
        // Stripe scripts
        wp_dequeue_script('stripe');
        wp_dequeue_script('wc-stripe');
        wp_dequeue_script('wc-stripe-upe');
        wp_dequeue_script('wc-stripe-blocks-integration');

        // Woo styles/scripts (om du vill vara aggressiv med cache)
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('wc-add-to-cart');
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        wp_dequeue_style('wc-block-style');
        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');
    }
}, 100);

// Debug-header
add_action('send_headers', function () {
    header('X-Cache-Debug: Loopia potential active');
});

/* ============================================================
   Fix för dubbla titlar / title-tag
   Buffrar hela sidan och tar bort eventuell extra <title>-tagg
   ============================================================ */
add_action('template_redirect', function () {
    ob_start(function ($html) {
        preg_match_all('/<title[^>]*>.*?<\/title>/is', $html, $matches);
        if (count($matches[0]) > 1) {
            // Ta bort alla utom den sista (Yoast skriver sist)
            for ($i = 0; $i < count($matches[0]) - 1; $i++) {
                $html = preg_replace('/' . preg_quote($matches[0][$i], '/') . '/', '', $html, 1);
            }
        }
        return $html;
    });
}, 0);



/**
Meddelande i kassan
 */


// Visa overlay på checkout (ej orderbekräftelse)
add_action('wp_footer', function () {
    if (is_checkout() && ! is_order_received_page()) {
        ?>
        <div id="custom-loading-message">
            <div class="loader-container">
                <div class="spinner"></div>
                <p>
                    Tack för din beställning.<br>
                    Vi kontaktar din bank för verifiering.<br>
                    Du kommer inom kort få möjlighet att bekräfta med BankID.<br><br>
                    🧘 Tack för ditt tålamod.
                </p>
            </div>
        </div>
        <?php
    }
});

// Lägg till JS-filen bara på checkout-sidan
add_action('wp_enqueue_scripts', function () {
    if (is_checkout()) {
        wp_enqueue_script(
            'custom-checkout',
            get_stylesheet_directory_uri() . '/js/custom-checkout.js',
            array(),
            '1.0.4',
            true
        );
    }
});



/**
 * Redirect till medlemssida efter att lösenord har satts
 * Gäller både nyregistrering via mail och glömt lösenord
 */
add_action('template_redirect', function () {

    // Kör inte om användaren inte är inloggad
    if ( ! is_user_logged_in() ) return;

    // 1. Redirect vid glömt lösenord
    if ( isset($_GET['password-reset']) && $_GET['password-reset'] === 'true' ) {
        wp_safe_redirect(home_url('/medlemssida/'));
        exit;
    }

    // 2. Redirect efter nytt konto → när lösenord har satts
    if (
        isset($_GET['action']) && $_GET['action'] === 'newaccount' &&
        ! empty($_GET['key']) &&
        ! empty($_GET['login'])
    ) {
        add_action('woocommerce_reset_password', function () {
            wp_safe_redirect(home_url('/medlemssida/'));
            exit;
        });
    }

});

// Dölj inloggningsformuläret på sidan för lösenordsåterställning via e-postlänk
add_action('template_redirect', function () {
    if (isset($_GET['action'], $_GET['key'], $_GET['login']) && $_GET['action'] === 'newaccount') {
        remove_action('woocommerce_before_customer_login_form', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_account_content', 'woocommerce_login_form', 10);
    }
});



// Ta bort meddelandet: "Thank you for purchasing a membership!"
add_action('init', function () {
    remove_action('woocommerce_thankyou', 'wc_memberships_thank_you_membership_message', 15);
});


// Ändra text på köpknappen till "Börja nu" (WooCommerce Subscriptions)
add_filter('woocommerce_product_single_add_to_cart_text', function ($text, $product) {

    if (class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($product)) {
        return 'Börja nu';
    }

    return $text;
}, 10, 2);


// Ta bort zoom-funktion på produktbilder
add_action('after_setup_theme', function () {
    remove_theme_support('wc-product-gallery-zoom');
});


/**
 * Toggle-text JS (för “+”-expandera)
 * OBS: här ändrat till child-sökväg (get_stylesheet_directory_uri)
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'toggle-text-script',
        get_stylesheet_directory_uri() . '/js/toggle-text.js',
        array(),
        '1.0',
        true
    );
}, 30);



// Visar olika info på orderbekräftelsen beroende på om kunden är ny eller återkommande
add_action('woocommerce_thankyou', function ($order_id) {

    if (!is_wc_endpoint_url('order-received')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) return;

    $customer_id = $order->get_customer_id();
    $customer_orders = wc_get_customer_order_count($customer_id);

    if ($customer_orders === 1) {

        echo '
        <div class="mool-start-block" style="margin-top: 2em;">
            <h3 class="mool-start-heading">Så här kommer du igång:</h3>
            <ul style="list-style-type: disc; padding-left: 1.5em; line-height: 1.6;">
                <li>Öppna mailet “Ditt konto på Mool har skapats!” (kontrollera skräpposten trots det magiska innehållet)</li>
                <li>Skapa lösenord via länken i mailet</li>
                <li>Logga in genom att klicka på medlemssida och njut av klasserna ✨</li>
                <li>Börja gärna med att titta på vår introduktionsfilm</li>
            </ul>
        </div>';

    } else {

        echo '
        <div class="mool-start-block" style="margin-top: 2em;">
            <h3 class="mool-start-heading">Välkommen tillbaka!</h3>
            <p>Du är inloggad och redo att fortsätta din Lifekit-resa 🙏<br>
            Gå till <a href="/medlemssida/">medlemssidan</a> för att se dina klasser och börja där du slutade.</p>
        </div>';

    }
}, 6);


// Google Ads conversion tracking på tacksidan
add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $total = $order->get_total();
    ?>
    <script>
      gtag('event', 'conversion', {
          'send_to': 'AW-1045557188/74vJCMvi_LgaEMTfx_ID',
          'value': <?php echo (float) $total; ?>,
          'currency': 'SEK',
          'transaction_id': '<?php echo esc_js($order->get_order_number()); ?>'
      });
    </script>
    <?php
}, 20);



// Ta bort onödiga fält i checkout
add_filter('woocommerce_checkout_fields', function ($fields) {
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_phone']);

    return $fields;
});



// Töm varukorg när man klickar på subscription (empty-cart param)
add_filter('woocommerce_product_add_to_cart_url', function ($url, $product) {
    if ($product->is_type('subscription') && $product->is_purchasable()) {
        $url = add_query_arg('empty-cart', '', $url);
    }
    return $url;
}, 10, 2);

add_action('wp_loaded', function () {
    if (isset($_GET['empty-cart'])) {
        WC()->cart->empty_cart();
    }
});



// 🕒 Automatisk schemaläggning av underhållsläge kl. 03:00–03:15 varje natt

// Aktivera underhållsläge
function start_maintenance_mode() {
    update_option('wp_maintenance_mode_status', 'true');
}

// Inaktivera underhållsläge
function stop_maintenance_mode() {
    update_option('wp_maintenance_mode_status', 'false');
}

// Schemalägg om det inte redan är schemalagt
if (!wp_next_scheduled('enable_maintenance_night')) {
    wp_schedule_event(strtotime('03:00:00'), 'daily', 'enable_maintenance_night');
}

if (!wp_next_scheduled('disable_maintenance_night')) {
    wp_schedule_event(strtotime('03:15:00'), 'daily', 'disable_maintenance_night');
}

// Hooka in funktionerna
add_action('enable_maintenance_night', 'start_maintenance_mode');
add_action('disable_maintenance_night', 'stop_maintenance_mode');



// Ta bort block-editor CSS (OBS: kan påverka vissa sidor)
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style');
}, 100);



// Preload hero-bilder för LifeKit-sidan
function mool_preload_lifekit_hero() {
    if (is_page(8741)) {
        echo '<link rel="preload" as="image" href="https://mool.se/...ebp" type="image/webp" media="(min-width: 768px)">' . "\n";
        echo '<link rel="preload" as="image" href="https://mool.se/..." media="(max-width: 767px)" fetchpriority="high">' . "\n";
    }
}
add_action('wp_head', 'mool_preload_lifekit_hero', 0);


// Preload hero-bilder för Terapi-sidan

add_action('wp_head', function () {
    if (is_page(10174)) { // Terapi & Samtal
        echo '<link rel="preload" as="image" href="HERO-URL-HÄR" type="image/webp" fetchpriority="high">' . "\n";
    }
}, 0);



// Tillåt uppladdning av woff-filer
add_filter('upload_mimes', function ($mimes) {
    $mimes['woff']  = 'font/woff';
    $mimes['woff2'] = 'font/woff2';
    return $mimes;
});



// Visa information i checkout för LifeKit
add_action('woocommerce_checkout_before_terms_and_conditions', function () {

    $lifekit_product_id = 5848;

    if (!WC()->cart) return;

    $has_lifekit_in_cart = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $lifekit_product_id) {
            $has_lifekit_in_cart = true;
            break;
        }
    }

    if (!$has_lifekit_in_cart) return;

    if (!is_user_logged_in()) {
        echo '<div style="
            margin-top: 2em;
            background-color: #fff6e5;
            padding: 1.5em;
            border-radius: 8px;
            font-size: 16px;
            line-height: 1.6;
            font-family: Nunito, sans-serif;
            color: rgb(86, 66, 61);
        ">
            <strong style="font-weight: 700;">Vad händer när du beställer?</strong><br><br>
            Det skapas ett konto hos Mool och din <strong style="font-weight:700;">gratis provperiod för LifeKit</strong> börjar.<br><br>
            - <strong style="font-weight:700;">14 dagar helt gratis</strong> – inget kort behövs, ingen bindningstid.<br>
            - Du får ett mail 3 dagar innan provperioden är slut.<br>
            - Därefter får du ett mail med en <strong style="font-weight:700;">betalningslänk</strong> – klicka på den och fyll i dina kortuppgifter om du vill fortsätta.<br><br>
            <i>Vill du inte fortsätta? Då behöver du inte göra någonting alls. Ditt konto finns kvar och du kan när som helst logga in och börja igen när det passar dig. Ingen betalning om du inte aktivt väljer att fortsätta.</i>
        </div>';
        return;
    }

    $user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($user_id);

    $has_active_lifekit = false;

    foreach ($subscriptions as $subscription) {
        if ($subscription->has_status('active')) {
            foreach ($subscription->get_items() as $item) {
                if ($item->get_product_id() == $lifekit_product_id) {
                    $has_active_lifekit = true;
                    break 2;
                }
            }
        }
    }

    if (!$has_active_lifekit) {
        echo '<div style="
            margin-top: 2em;
            background-color: #fff6e5;
            padding: 1.5em;
            border-radius: 8px;
            font-size: 16px;
            line-height: 1.6;
            font-family: Nunito, sans-serif;
            color: rgb(86, 66, 61);
        ">
            <strong style="font-weight:700;">Tack för att du investerar i dig själv 💛</strong><br><br>
            Du gör det också möjligt för oss att fortsätta skapa klasser och upplevelser för dig – direkt till din yogamatta.<br><br>
            Du kan när som helst logga in på ditt konto och <strong style="font-weight:700;">avsluta din prenumeration</strong> om du vill ta en paus.<br><br>
            Har du några frågor? <strong>Vi finns här för dig</strong> – tveka inte att höra av dig.
        </div>';
    }

});



// Ändra text på beställ-knappen
add_filter('woocommerce_order_button_text', function () {
    return 'Beställ';
});



// Ladda custom.js (om du använder den)
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'mool-custom-js',
        get_stylesheet_directory_uri() . '/js/custom.js',
        array(),
        '1.0',
        true
    );
});