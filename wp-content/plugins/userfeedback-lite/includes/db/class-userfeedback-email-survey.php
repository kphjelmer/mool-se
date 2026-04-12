<?php

/**
 * Email Survey class.
 *
 * @see UserFeedback_DB
 * @since 1.11.0
 *
 * @package UserFeedback
 * @subpackage DB
 */
class UserFeedback_Email_Survey extends UserFeedback_DB {

	/**
	 * @inheritdoc
	 */
	protected $table_name = 'userfeedback_email_surveys';

	/**
	 * @inheritdoc
	 */
	protected $casts = array(
		'rating_options' => 'array',
		'settings'       => 'object',
		'notifications'  => 'object',
	);

	/**
	 * Get survey by ID with response count
	 *
	 * @param int $id Survey ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		return self::where(
			array(
				'id' => $id,
			)
		)->with_count( array( 'responses' ) )->single();
	}

	/**
	 * Find survey by slug
	 *
	 * @param string $slug Survey slug.
	 * @return object|null
	 */
	public static function find_by_slug( $slug ) {
		return self::where(
			array(
				'slug' => $slug,
			)
		)->single();
	}

	/**
	 * Generate unique slug from title
	 *
	 * @param string   $title      Survey title.
	 * @param int|null $exclude_id ID to exclude from uniqueness check.
	 * @return string Unique slug.
	 */
	public static function generate_unique_slug( $title, $exclude_id = null ) {
		global $wpdb;

		$instance  = new static();
		$table     = $instance->get_table();
		$base_slug = sanitize_title( $title );
		$slug      = $base_slug;
		$counter   = 2;

		while ( true ) {
			$query  = "SELECT id FROM $table WHERE slug = %s";
			$params = array( $slug );

			if ( $exclude_id ) {
				$query   .= " AND id != %d";
				$params[] = $exclude_id;
			}

			$exists = $wpdb->get_var( $wpdb->prepare( $query, $params ) );

			if ( ! $exists ) {
				break;
			}

			$slug = $base_slug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Generate secret key for HMAC signing
	 *
	 * @return string 64-character random key.
	 */
	public static function generate_secret_key() {
		return wp_generate_password( 64, false );
	}

	/**
	 * Change surveys status to draft
	 *
	 * @param array $survey_ids Survey IDs.
	 * @return bool|int
	 */
	public static function draft( $survey_ids ) {
		return self::update_many(
			$survey_ids,
			array(
				'status' => 'draft',
			)
		);
	}

	/**
	 * Change surveys status to publish
	 *
	 * @param array $survey_ids Survey IDs.
	 * @return bool|int
	 */
	public static function publish( $survey_ids ) {
		return self::update_many(
			$survey_ids,
			array(
				'status' => 'publish',
			)
		);
	}

	/**
	 * Change surveys status to trash
	 *
	 * @param array $survey_ids Survey IDs.
	 * @return bool|int
	 */
	public static function trash( $survey_ids ) {
		return self::update_many(
			$survey_ids,
			array(
				'status' => 'trash',
			)
		);
	}

	/**
	 * Restore surveys (change status to draft)
	 *
	 * @param array $survey_ids Survey IDs.
	 * @return bool|int
	 */
	public static function restore( $survey_ids ) {
		return self::update_many(
			$survey_ids,
			array(
				'status' => 'draft',
			)
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns() {
		return array(
			'id',
			'slug',
			'secret_key',
			'title',
			'link_text',
			'status',
			'rating_options',
			'thank_you_message',
			'collect_feedback',
			'feedback_response',
			'feedback_field_label',
			'settings',
			'notifications',
			'created_at',
			'updated_at',
		);
	}

	/**
	 * @inheritdoc
	 */
	public function create_table() {
		global $wpdb;

		if ( self::table_exists() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate     = $wpdb->get_charset_collate();
		$table_name          = self::get_table();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			slug varchar(255) NOT NULL,
			secret_key varchar(64) NOT NULL,
			title varchar(255) NOT NULL,
			link_text varchar(255) DEFAULT 'How would you rate this email?',
			status enum('publish', 'draft', 'trash') DEFAULT 'draft',
			rating_options longtext,
			thank_you_message varchar(255) DEFAULT 'Thank You For Your Feedback',
			collect_feedback tinyint(1) DEFAULT 1,
			feedback_field_label varchar(255) DEFAULT 'Why did you rate us this way',
			feedback_response varchar(255) DEFAULT 'Thank You For Your Feedback! We appreciate it!',
			settings text,
			notifications text,
			created_at timestamp NOT NULL,
			updated_at timestamp NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			UNIQUE KEY secret_key (secret_key)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * @inheritdoc
	 */
	public function get_relationship_config( $name ) {
		switch ( $name ) {
			case 'responses':
				return array(
					'type'  => 'many',
					'class' => UserFeedback_Email_Response::class,
					'key'   => 'survey_id',
				);
			default:
				return null;
		}
	}
}
