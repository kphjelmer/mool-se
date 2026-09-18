<?php
/**
 * Plugin Name: Mool Säkerhet
 * Description: Stänger användarenumrering via REST, författararkiv och oEmbed. Städar bort kvarlämnad felsökningsfil i webbroten.
 * Version: 1.0.0
 */

/**
 * 1. /wp-json/wp/v2/users lämnade ut användarnamnet ninakp till vem som helst.
 *    Endpointen finns kvar för inloggade, så blockredigeraren, Site Kit och
 *    Elementor fortsätter fungera. Bara anonyma anrop stängs ute.
 */
add_filter('rest_endpoints', function ($endpoints) {
    if (is_user_logged_in()) {
        return $endpoints;
    }

    foreach (array('/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)') as $route) {
        if (isset($endpoints[$route])) {
            unset($endpoints[$route]);
        }
    }

    return $endpoints;
});

/**
 * 2. ?author=1 avslöjar samma sak: WordPress omdirigerar till /author/<slug>/
 *    och slugen ÄR användarnamnet. Författararkiven används inte på Mool.
 */
// Prioritet 0: WordPress egen redirect_canonical ligger på 10 och hinner annars
// före, och dess Location-huvud avslöjar slugen (alltså användarnamnet).
add_action('template_redirect', function () {
    if (is_admin() || is_user_logged_in()) {
        return;
    }

    if (is_author() || isset($_GET['author'])) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 0);

/**
 * 3. oEmbed-svaret innehåller författarens namn och adress.
 */
add_filter('oembed_response_data', function ($data) {
    unset($data['author_name'], $data['author_url']);
    return $data;
});

/**
 * 4. Engångsstädning: phpinfo-test.php låg kvar i webbroten och svarade 200 med
 *    PHP-version, sökvägar och modullista. Filen är gitignorerad, så FTP-deployen
 *    kan aldrig ta bort den. Den här koden gör det en gång och skriver ett spår i
 *    databasen så att den inte försöker igen.
 */
add_action('init', function () {
    $flagga = 'mool_phpinfo_rensad';

    if (get_option($flagga)) {
        return;
    }

    $fil = ABSPATH . 'phpinfo-test.php';

    if (!file_exists($fil)) {
        update_option($flagga, 'fanns inte ' . current_time('mysql'), false);
        return;
    }

    if (@unlink($fil)) {
        update_option($flagga, 'raderad ' . current_time('mysql'), false);
    }
});
