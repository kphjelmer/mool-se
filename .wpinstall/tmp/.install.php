<?php

/**
 * This file will install WordPress in the current directory.
 */

# Fail-safe to remove this file after a certain period
$ctime = filectime(__file__);
if (($ctime + 300 < time() && file_exists('wp-blog-header.php')) || $ctime + 86400 < time() ) {
        unlink(__file__);
}      

include '/usr/local/libexec/loopia/wordpress/installer/wp-installer.class.php';

$installer = new WP_Installer(array(
	'db_host' => 's690.loopia.se',
	'db_user' => 'wrdprs@m328650',
	'db_password' => 'id2839hidmclbbtb',
	'db_name' => 'mool_se',
	'db_table_prefix' => 'wp_mool_se_',

	'wp_site_title' => 'mool',
	'wp_user' => 'ninakp',
	'wp_password' => 'NinaKpSatNam2022',
	'wp_email' => 'kp.hjelmer@gmail.com',
	'wp_locale' => 'sv_SE',

	'wp_path' => dirname(__FILE__) . '/',

	'install_plugins' => [
		
	]
));
$installer->install();
