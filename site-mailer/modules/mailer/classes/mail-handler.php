<?php

namespace SiteMailer\Modules\Mailer\Classes;

use Exception;
use SiteMailer\Classes\Database\Exceptions\Missing_Table_Exception;
use SiteMailer\Classes\Services\Client;
use SiteMailer\Modules\Logs\Database\Log_Entry;
use SiteMailer\Modules\Logs\Database\Logs_Table;
use SiteMailer\Modules\Mailer\Components\Rate_Limit_Retry;
use SiteMailer\Modules\Settings\Module as Settings;

/**
 * The class is responsible for the send email to external service
 */
class Mail_Handler {


	const SERVICE_ENDPOINT = 'email-account/send';
	const LOG_STATUSES = [
		'pending' => 'pending',
		'failed'  => 'failed',
		'not_sent' => 'not sent',
		'rate_limit' => 'rate limit',
	];
	const ERROR_MSG = [
		'quota_exceeded' => 'Quota Status Guard Request Failed!: Quota exceeded',
		'rate_limit' => 'Too many requests',
	];

	private array $email;
	private string $log_id;
	private array $attachments = [];
	private string $source;
	private string $type;

	/**
	 * Get data from logs and try to send one more time
	 *
	 * @param array $ids
	 *
	 * @return void
	 * @throws Missing_Table_Exception
	 */
	public static function resend_mails( array $ids ): void {
		$ids_int = array_map( 'absint', $ids );
		$escaped = implode( ',', array_map(function( $item ) {
			return Logs_Table::db()->prepare( '%d', $item );
		}, $ids_int));
		$where = '`id` IN (' . $escaped . ')';
		$logs = Log_Entry::get_logs(
			'`api_id`, `subject`, `message`, `to`, `headers`',
			$where,
		);
		// TODO: Discuss and add possibility to resend mails as array with one request
		foreach ( $logs as $log ) {
			$log->to      = json_decode( $log->to );
			$log->headers = json_decode( $log->headers );
			$handler      = new self( (array) $log, 'Resend', 'Plugin' );
			$handler->send();
		}
	}

	/**
	 * Create and send test mail
	 *
	 * @param string $address
	 *
	 * @return void
	 * @throws Missing_Table_Exception
	 */
	public static function send_test_mail( string $address ): void {
		$current_timestamp = gmdate( 'Y-m-d H:i:s' );
		$url = get_bloginfo('url');
		/* translators: %s is the timestamp */
		$msg = '<!doctype html>
				<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
					<head>
						<title>' . __( 'Site Mailer Email Test', 'site-mailer' ) . '</title>
					</head>
					<body>
						<p style="padding: 4px 0">' . __( 'Congrats, the test email was sent successfully!', 'site-mailer' ) . '</p>
						<p style="padding: 4px 0">' . __( 'Thank you for using Site Mailer. We are here to make sure your emails actually get delivered!', 'site-mailer' ) . '</p>
						<p style="padding: 12px 0">' . __( 'The Site Mailer Team', 'site-mailer' ) . '</p>
						<p style="padding: 4px 0">' . __( 'Sent By: ', 'site-mailer' ) . $url . '</p>
						<p style="padding: 4px 0">' . __( 'Timestamp: ', 'site-mailer' ) . $current_timestamp . '</p>
					</body>
				</html>';
		$email = [
			'to' => $address,
			'subject' => __( 'Site Mailer Email Test', 'site-mailer' ),
			'message' => $msg,
			'headers' => 'Content-Type: text/html',
		];
		$handler = new self( $email, 'Test', 'Plugin' );
		$handler->send();
	}

	/**
	 * Add data from settings to email
	 * @param array $email Array of the `wp_mail()` arguments.
	 *
	 * @return void
	 */
	private function prepare_mail( array $email ) {
		$this->email = array_merge([
			'from_name' => Settings::get_sender_from_name(),
			'reply_to' => Settings::get_sender_reply_email(),
		], $email);
	}

	/**
	 * get_mail_attachments
	 *
	 * Get file content and path info from tmp file
	 *
	 * TODO add store file if needed
	 */
	private function get_mail_attachments(): void {
		if ( array_key_exists( 'attachments', $this->email ) && ! empty( $this->email['attachments'] ) ) {
			foreach ( $this->email['attachments'] as $attachment ) {
				$file                = file_get_contents( $attachment );
				$pathinfo            = pathinfo( $attachment );
				$this->attachments[] = [
					'file'     => base64_encode( $file ),
					'basename' => $pathinfo['basename'],
					'type'     => mime_content_type( $attachment ),
				];
			}
		}
	}

	/**
	 * Create and save log entry
	 * @param string|null $status
	 *
	 * @return void
	 * @throws Missing_Table_Exception
	 */
	private function write_log( string $status = null ) {
		$keep_log = Settings::get_keep_log_setting();

		$required = [
			'api_id'  => $this->log_id,
			'subject' => $this->email['subject'],
			'to'      => wp_json_encode( $this->email['to'] ), // possible array of strings
			'source'  => $this->source,
			'status'  => $status ?? self::LOG_STATUSES['pending'],
		];
		$on_keep  = $keep_log ? [
			'headers' => wp_json_encode( $this->email['headers'] ), // possible array of strings
			'message' => $this->email['message'],
		] : [];
		$log      = new Log_Entry( [ 'data' => array_merge( $required, $on_keep ) ] );
		$log->create();
	}

	/**
	 * Handle send email error
	 * @param string $error
	 *
	 * @throws Missing_Table_Exception
	 * @throws Exception
	 */
	private function error_handler( string $error ) {
		switch ( $error ) {
			case self::ERROR_MSG['quota_exceeded']:
				$status = self::LOG_STATUSES['not_sent'];
				break;
			case self::ERROR_MSG['rate_limit']:
				$status = self::LOG_STATUSES['rate_limit'];
				Rate_Limit_Retry::schedule_resend_email( $this->log_id, $this->email );
				break;
			default:
				$status = self::LOG_STATUSES['failed'];
				break;
		}

		$this->write_log( $status );
		throw new Exception( esc_html( $error ) );
	}

	/**
	 * send request with mail to the api service
	 *
	 * Send mail to the external service
	 *
	 * @return void
	 * @throws Missing_Table_Exception
	 * @throws Exception
	 */
	public function send() {
		$response = Client::get_instance()->make_request(
			'POST',
			self::SERVICE_ENDPOINT,
			[
				'email'       => $this->email,
				'attachments' => $this->attachments,
				'from'        => Settings::get_sender_email(),
				'custom_args' => [
					'email_id'     => $this->log_id,
					'source'       => $this->source,
					'type'         => $this->type,
					'status'       => self::LOG_STATUSES['pending'],
				],
			],
			[],
			true
		);
		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			$this->error_handler( $error );
		}

		$this->write_log();
	}

	/**
	 *
	 * @param array $email Array of the `wp_mail()` arguments.
	 * @param string $type Normal|Resend|Test
	 * @param string|null $source
	 *
	 */
	public function __construct( array $email, string $type, string $source = null ) {
		$this->log_id = wp_generate_uuid4();
		$this->source = $source ?? Caller_Source::get_caller_source();
		$this->type = $type;
		$this->prepare_mail( $email );
		$this->get_mail_attachments();
	}
}
