<?php

/**
 * Email Survey Response class.
 *
 * @see UserFeedback_DB
 * @since 1.11.0
 *
 * @package UserFeedback
 * @subpackage DB
 */
class UserFeedback_Email_Response extends UserFeedback_DB {

	/**
	 * @inheritdoc
	 */
	protected $table_name = 'userfeedback_email_survey_responses';

	/**
	 * @inheritdoc
	 */
	protected $timestamp_column = 'created_at';

	/**
	 * Change responses status to trash
	 *
	 * @param array $response_ids Response IDs.
	 * @return bool|int
	 */
	public static function trash( $response_ids ) {
		return self::update_many(
			$response_ids,
			array(
				'status' => 'trash',
			)
		);
	}

	/**
	 * Change responses status to publish
	 *
	 * @param array $response_ids Response IDs.
	 * @return bool|int
	 */
	public static function restore( $response_ids ) {
		return self::update_many(
			$response_ids,
			array(
				'status' => 'publish',
			)
		);
	}

	/**
	 * Find response by session token
	 *
	 * @param string $token Session token.
	 * @return object|null
	 */
	public static function find_by_session_token( $token ) {
		return self::where(
			array(
				'session_token' => $token,
			)
		)->single();
	}

	/**
	 * Check if session has already responded to a survey
	 *
	 * @param string $token     Session token.
	 * @param int    $survey_id Survey ID.
	 * @return bool
	 */
	public static function has_session_responded( $token, $survey_id ) {
		global $wpdb;

		$instance = new static();
		$table    = $instance->get_table();

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE session_token = %s AND survey_id = %d",
				$token,
				$survey_id
			)
		);

		return ! empty( $exists );
	}

	/**
	 * @inheritdoc
	 */
	public function get_columns() {
		return array(
			'id',
			'survey_id',
			'session_token',
			'rating_value',
			'utm_campaign',
			'utm_medium',
			'utm_source',
			'utm_term',
			'utm_content',
			'comment',
			'comment_submitted_at',
			'user_agent',
			'referrer',
			'created_at',
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

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_table();

		$email_surveys_db_instance = new UserFeedback_Email_Survey();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			session_token varchar(64) NOT NULL,
			rating_value varchar(50) NOT NULL,
			utm_campaign varchar(255) DEFAULT NULL,
			utm_medium varchar(255) DEFAULT NULL,
			utm_source varchar(255) DEFAULT NULL,
			utm_term varchar(255) DEFAULT NULL,
			utm_content varchar(255) DEFAULT NULL,
			comment text DEFAULT NULL,
			comment_submitted_at timestamp NULL,
			user_agent varchar(255),
			referrer varchar(500),
			created_at timestamp NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY session_token (session_token),
			KEY survey_id (survey_id),
			KEY rating_value (rating_value),
			KEY utm_campaign (utm_campaign),
			KEY utm_medium (utm_medium),
			KEY utm_source (utm_source),
			KEY created_at (created_at),
			FOREIGN KEY (survey_id)
				REFERENCES {$email_surveys_db_instance->get_table()}({$email_surveys_db_instance->primary_key}) ON DELETE CASCADE
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * @inheritdoc
	 */
	public function get_relationship_config( $name ) {
		switch ( $name ) {
			case 'survey':
				return array(
					'type'  => 'one',
					'class' => UserFeedback_Email_Survey::class,
					'key'   => 'survey_id',
				);
			default:
				return null;
		}
	}
}
