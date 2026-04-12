<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo wp_kses_post( $title );

echo "\n\n";

echo wp_kses_post( $description );

echo "\n\n";

if ( ! empty( $summaries ) ) {

    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

    foreach ( $summaries as $survey ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable set by caller.
        echo wp_kses_post(
            "\t" .
            __( 'Survey: ', 'userfeedback-lite' ) .
            $survey['name'] .
            "\n\n"
        );

        echo wp_kses_post(
            "\t" .
            __( 'Responses: ', 'userfeedback-lite' ) .
            $survey['responses'] .
            "\n\n"
        );
    }
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf(
	// translators: %s is the site URL.
	esc_html__( 'Sent from %s', 'userfeedback-lite' ),
	esc_url_raw(get_site_url())
);
