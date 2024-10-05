<?php

namespace SiteMailer\Classes;

use SiteMailer\Classes\Services\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Utils {

	public static function get_api_client(): ?Client {
		return Client::get_instance();
	}

	public static function is_plugin_page(): bool {
		$current_screen = get_current_screen();

		return str_contains( $current_screen->id, 'site-mailer-' );
	}
}
