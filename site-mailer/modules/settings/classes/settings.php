<?php

namespace SiteMailer\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Settings {
	public const SENDER_DOMAIN = 'site_mailer_sender_domain';
	public const SENDER_EMAIL_PREFIX = 'site_mailer_sender_email_prefix';
	public const CUSTOM_DOMAIN_DNS_RECORDS = 'site_mailer_custom_domain_dns_records';
	public const CUSTOM_DOMAIN_VERIFICATION_STATUS = 'site_mailer_verification_status';
	public const CUSTOM_DOMAIN_VERIFICATION_RECORDS = 'site_mailer_verification_records';

	/**
	 * Returns plugin settings data by option name typecasted to an appropriate data type.
	 *
	 * @param string $option_name
	 * @return mixed
	 */
	public static function get( string $option_name ) {
		$data = get_option( $option_name );

		switch ( $option_name ) {
			case self::SENDER_DOMAIN:
			case self::CUSTOM_DOMAIN_VERIFICATION_RECORDS:
				return json_decode( $data );

			case self::CUSTOM_DOMAIN_VERIFICATION_STATUS:
				if ( ! $data ) {
					return 'not-started';
				}

				return $data;

			default:
				return $data;
		}
	}
}
