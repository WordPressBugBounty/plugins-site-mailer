<?php

namespace SiteMailer\Modules\Logs\Database;

use mysql_xdevapi\Exception;
use SiteMailer\Classes\Database\{
	Table,
	Database_Constants
};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Logs_Table extends Table {
	// override base's const:
	const DB_VERSION = '4';
	const DB_VERSION_FLAG_NAME = 'site_mail_logs_db_version';
	const LOG_STATUSES = [
		'pending',
		'accepted',
		'processed',
		'delivered',
		'bounce',
		'dropped',
		'deferred',
		'not sent',
		'rate limit',
		'not valid',
	];

	const ID = 'id';
	const API_ID = 'api_id';
	const TO = 'to';
	const SUBJECT = 'subject';
	const HEADERS = 'headers';
	const MESSAGE = 'message';
	const STATUS = 'status';
	const ACTIVITY = 'activity';
	const SOURCE = 'source';
	const OPENED = 'opened';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';

	public static $table_name = 'site_mail_logs';

	/**
	 * install
	 *
	 * This function compares the version of the installed table and the current version as reported by
	 * the class.
	 * If the versions are different, the table will be installed or updated, and the option
	 * will be set to the current version.
	 */
	public static function install(): void {
		$installed_ver = get_option( static::DB_VERSION_FLAG_NAME, -1 );

		if ( static::DB_VERSION !== $installed_ver ) {

			self::run_migration( $installed_ver );

			$sql = static::get_create_table_sql();

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( static::DB_VERSION_FLAG_NAME, static::DB_VERSION, false );
		}

		static::set_table_prefix();
	}

	public static function run_migration( $version ) {
		// Drop index from self::TO
		if ( $version > 0 && $version < 4 ) {
			self::query( 'ALTER TABLE `' . self::table_name() . '` DROP INDEX `' . self::TO . '`' );
		}
	}

	public static function get_columns(): array {
		return [
			self::ID         => [
				'type'  => Database_Constants::get_col_type( Database_Constants::INT, 11 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::UNSIGNED,
					Database_Constants::NOT_NULL,
					Database_Constants::AUTO_INCREMENT,
				] ),
				'key'   => Database_Constants::get_primary_key_string( self::ID ),
			],
			self::API_ID     => [
				'type'  => Database_Constants::get_col_type( Database_Constants::VARCHAR, 255 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'\'\'',
				] ),

				'key' => Database_Constants::build_key_string( Database_Constants::KEY, self::API_ID ),
			],
			self::TO         => [
				'type'  => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'\'\'',
				] ),
			],
			self::SUBJECT    => [
				'type'  => Database_Constants::get_col_type( Database_Constants::VARCHAR, 768 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'\'\'',
				] ),
			],
			self::HEADERS    => [
				'type'  => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					Database_Constants::NULL,
				] ),
			],
			self::MESSAGE    => [
				'type'  => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					Database_Constants::NULL,
				] ),
			],
			self::STATUS     => [
				'type'  => Database_Constants::get_col_type( Database_Constants::VARCHAR, 255 ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'\'pending\'',
					Database_Constants::COMMENT,
					'"' . implode( '|', self::LOG_STATUSES ) . '"',
				] ),
			],
			self::ACTIVITY   => [
				'type'  => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::DEFAULT,
					'\'\'',
				] ),
			],
			self::SOURCE => [
				'type'  => Database_Constants::get_col_type( Database_Constants::TEXT ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					'\'\'',
				] ),
			],
			self::OPENED => [
				'type'  => Database_Constants::get_col_type( Database_Constants::BOOLEAN ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					0,
				] ),
			],
			self::CREATED_AT => [
				'type'  => Database_Constants::get_col_type( Database_Constants::DATETIME ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					Database_Constants::CURRENT_TIMESTAMP,
				] ),
			],
			self::UPDATED_AT => [
				'type'  => Database_Constants::get_col_type( Database_Constants::DATETIME ),
				'flags' => Database_Constants::build_flags_string( [
					Database_Constants::NOT_NULL,
					Database_Constants::DEFAULT,
					Database_Constants::CURRENT_TIMESTAMP,
					Database_Constants::ON_UPDATE,
					Database_Constants::CURRENT_TIMESTAMP,
				] ),
			],
		];
	}
}
