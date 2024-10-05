<?php

namespace SiteMailer\Modules\Logs\Rest;

use SiteMailer\Modules\Logs\Classes\Route_Base;
use SiteMailer\Modules\Logs\Database\Log_Entry;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Get_Stats extends Route_Base {
	public string $path = 'get-stats';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'get-stats';
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 *
	 * @query {
	 *     Date $period
	 * }
	 */
	public function GET( WP_REST_Request $request ) {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$params = $request->get_query_params();

			// Add period
			$where = $params['period'] ? [
				[
					'column' => 'created_at',
					'value' => $params['period'],
					'operator' => '>',
					'relation_after' => 'AND',
				],
				[
					'column' => 'status',
					'value' => 'not sent',
					'operator' => '<>',
				],
			] : '1';

			$stats = Log_Entry::get_logs_stats( $where );

			return $this->respond_success_json( $stats[0] );
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			] );
		}
	}
}
