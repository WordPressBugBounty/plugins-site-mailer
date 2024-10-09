<?php

namespace SiteMailer\Modules\Logs\Database;

use SiteMailer\Classes\Database\Entry;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Log_Entry
 */
class Log_Entry extends Entry {
	/**
	 * @var mixed|null
	 */
	public mixed $subject;
	/**
	 * @var mixed|null
	 */
	public mixed $message;
	/**
	 * @var mixed|null
	 */
	public mixed $to;

	public static function get_helper_class(): string {
		return Logs_Table::get_class_name();
	}

	/**
	 * @param string $status
	 *
	 * @return bool
	 */
	public static function validate_status( string $status ): bool {
		return in_array( $status, Logs_Table::LOG_STATUSES, true );
	}

	/**
	 * @param string $fields
	 * @param string | array $where
	 * @param number $limit
	 * @param number $offset
	 * @param string $join
	 * @param array $order_by
	 *
	 * @return array
	 */
	public static function get_logs(
		string $fields = '*',
		$where = '1',
		$limit = null,
		$offset = null,
		string $join = '',
		array $order_by = []
	): array {
		return Logs_Table::select( $fields, $where, $limit, $offset, $join, $order_by );
	}

	/**
	 * @param string|array $where
	 *
	 * @return array
	 */
	public static function get_logs_count( $where ): array {
		return Logs_Table::select( 'COUNT(*) as count', $where );
	}

	/**
	 * @param string|array $where
	 *
	 * @return array
	 */
	public static function get_logs_stats( $where ): array {
		return Logs_Table::select(
			"COUNT(*) as total,
				COUNT(CASE WHEN `status` = 'delivered' THEN 1 END) as delivered,
				COUNT(CASE WHEN `status` IN ('failed', 'bounce', 'dropped') THEN 1 END) as failed,
				COUNT(CASE WHEN `opened` = 1 THEN 1 END) as opened",
			$where
		);
	}

	/**
	 * @param string $id
	 * @param string $status
	 *
	 * @return void
	 */
	public static function patch_log( string $id, string $status ): void {
		Logs_Table::update(
			[
				Logs_Table::STATUS => $status,
				Logs_Table::UPDATED_AT => current_time( 'mysql' ),
			],
			[ Logs_Table::API_ID => $id ]
		);
	}

	/**
	 * @param string $id
	 *
	 * @return void
	 */
	public static function set_log_opened( string $id ): void {
		Logs_Table::update(
			[
				Logs_Table::OPENED => true,
				Logs_Table::STATUS => 'delivered',
			],
			[ Logs_Table::API_ID => $id ]
		);
	}

	/**
	 * @param array $ids
	 *
	 * @return void
	 */
	public static function delete_logs( array $ids ): void {
		$ids_int = array_map( 'absint', $ids );
		$escaped = implode( ',', array_map(function( $item ) {
			return Logs_Table::db()->prepare( '%d', $item );
		}, $ids_int));
		$query = 'DELETE FROM `' . Logs_Table::table_name() . '` WHERE `' . Logs_Table::ID . '` IN(' . $escaped . ')';
		Logs_Table::query( $query );
	}

	/**
	 * Delete logs oldest then 30 days
	 * @return void
	 */
	public static function delete_expired_logs() {
		$query = 'DELETE FROM `' . Logs_Table::table_name() . '` WHERE `' . Logs_Table::CREATED_AT . '` < NOW() - INTERVAL 30 DAY';
		Logs_Table::query( $query );
	}
}
