<?php

namespace OM4\WooCommerceZapier\NewUser;

use OM4\WooCommerceZapier\Notice\BaseNotice;
use OM4\WooCommerceZapier\Plugin;

defined( 'ABSPATH' ) || exit;


/**
 * Notice that is displayed to new users installing/activating the plugin for the first time.
 * Wording/content for this notice is stored in `templates/notices/new-user.php`
 *
 * @since 2.0.0
 */
class NewUserWelcomeNotice extends BaseNotice {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $id = 'new_user';

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	protected function template_variables() {
		return array(
			'quick_start_url' => Plugin::DOCUMENTATION_URL . 'quick-start/' . Plugin::UTM_TAGS . 'welcome',
			'whats_new_url'   => Plugin::DOCUMENTATION_URL . Plugin::UTM_TAGS . 'welcome#whats-new',
		);
	}
}
