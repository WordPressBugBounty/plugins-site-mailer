<?php

namespace SiteMailer\Modules\Settings;

use SiteMailer\Classes\Module_Base;
use SiteMailer\Modules\Connect\Classes\Config;
use SiteMailer\Modules\Connect\Module as Connect;
use SiteMailer\Modules\Connect\Classes\Data;
use SiteMailer\Classes\Utils;
use SiteMailer\Modules\Connect\Classes\Utils as Connect_Utils;
use SiteMailer\Modules\Settings\Classes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Module
 */
class Module extends Module_Base {


	const SETTING_PREFIX     = 'site_mailer_';
	const SETTING_GROUP      = 'site_mailer_settings';
	const SETTING_BASE_SLUG  = 'site-mailer-settings';
	const SETTING_CAPABILITY = 'manage_options';

	/**
	 * Get module name.
	 * Retrieve the module name.
	 *
	 * @access public
	 * @return string Module name.
	 */
	public function get_name(): string {
		return 'settings';
	}

	public static function component_list(): array {
		return [
			'Settings_Pointer',
		];
	}

	public static function routes_list() : array {
		return [
			'Get_Settings',
		];
	}

	public function render_top_bar() {
		?>
		<div id="site-mailer-app-top-bar"></div>
		<?php
	}

	public function render_app() {
		?>
		<!-- The hack required to wrap WP notifications -->
		<div class="wrap">
			<h1 style="display: none;" role="presentation"></h1>
		</div>

		<div id="site-mailer-app"></div>
		<?php
	}

	public function register_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Site Mailer', 'site-mailer' ),
			__( 'Site Mailer', 'site-mailer' ),
			self::SETTING_CAPABILITY,
			self::SETTING_BASE_SLUG,
			[ $this, 'render_app' ],
			20
		);
	}

	public function maybe_add_url_mismatch_notice() {
		if ( ! Utils::is_plugin_page() || Connect_Utils::is_valid_home_url() ) {
			return;
		}

		?>
		<div class="notice notice-error notice site-mailer__notice site-mailer__notice--error">
			<p>
				<b>
					<?php esc_html_e(
						'Your license key does not match your current domain, causing a mismatch.',
						'site-mailer'
					); ?>
				</b>

				<span>
					<?php esc_html_e(
						'This is most likely due to a change in the domain URL of your site (including HTTP/SSL migration).',
						'site-mailer'
					); ?>

					<button type="button" onclick="document.dispatchEvent( new Event( 'site-mailer/auth/url-mismatch-modal/open' ) );">
						<?php esc_html_e(
							'Fix mismatched URL',
							'site-mailer'
						); ?>
					</button>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue Scripts and Styles
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_site-mailer-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'site-mailer-admin-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
			[],
			SITE_MAILER_VERSION
		);

		Utils\Assets::enqueue_app_assets( 'admin' );

		wp_localize_script(
			'admin',
			'siteMailerSettingsData',
			[ 'wpRestNonce' => wp_create_nonce( 'wp_rest' ) ]
		);
	}

	public function on_connect() {
		if ( ! Connect::is_connected() ) {
			return;
		}

		$register_response = Utils::get_api_client()->make_request(
			'POST',
			'site/register'
		);

		if ( $register_response && ! is_wp_error( $register_response ) ) {
			Data::set_subscription_id( $register_response->id );

			$response = Utils::get_api_client()->make_request(
				'POST',
				'site/info'
			);

			if ( ! empty( $response->site_url ) && Data::get_home_url() !== $response->site_url ) {
				Data::set_home_url( $response->site_url );
			}
		}
		if ( ! is_wp_error( $response ) ) {
			update_option( self::SETTING_PREFIX . 'plan_data', $response );
			update_option( self::SETTING_PREFIX . 'is_valid_plan_data', true );
		} else {
			error_log( esc_html( $response->get_error_message() ) );
			update_option( self::SETTING_PREFIX . 'is_valid_plan_data', false );
		}
	}

	/**
	 * Register settings.
	 *
	 * Register settings for the plugin.
	 *
	 * @return void
	 */
	public function register_settings() {
		$settings = [
			'keep_log'                       => [
				'type'         => 'boolean',
				'show_in_rest' => [
					'schema' => [
						'type' => 'boolean',
					],
				],
				'description'  => __( 'Site Mailer Keep Log', 'site-mailer' ),
			],
			'close_post_connect_modal'       => [
				'type'         => 'boolean',
				'show_in_rest' => [
					'schema' => [
						'type' => 'boolean',
					],
				],
				'description'  => __( 'Site Mailer Close Post Connect Modal', 'site-mailer' ),
			],
			'from_name'                      => [
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
					],
				],
				'description'  => __( 'Site Mailer From Email', 'site-mailer' ),
			],
			'reply_to_email'                 => [
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
					],
				],
				'description'  => __( 'Site Mailer Reply Email', 'site-mailer' ),
			],
			'sender_domain'                  => [
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
					],
				],
				'description'  => __( 'Site Mailer Sender Domain', 'site-mailer' ),
			],
			'sender_email_prefix'            => [
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
					],
				],
				'description'  => __( 'Site Mailer Sender Mail Prefix', 'site-mailer' ),
			],
			'custom_domain_dns_records'      => [
				'type'         => 'string',
				'show_in_rest' => [
					'schema' => [
						'type' => 'string',
					],
				],
				'description'  => __( 'Site Mailer custom domain DNS records', 'site-mailer' ),
			],
			'verification_started'           => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'registration_started'           => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'confirm_domain_settings_opened' => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'confirm_dns_settings_added'     => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'active_step'                    => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'custom_domain_verified'         => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'custom_domain_verified_alert'   => [
				'type'         => 'boolean',
				'show_in_rest' => true,
			],
			'custom_domain_verification_start_time' => [
				'type'         => 'string',
				'show_in_rest' => true,
			],
			'is_valid_plan_data' => [
				'type' => 'boolean',
				'show_in_rest' => true,
			],
		];

		foreach ( $settings as $setting => $args ) {
			register_setting( 'options', self::SETTING_PREFIX . $setting, $args );
		}
	}

	/**
	 * Get sender email
	 *
	 * @return string
	 */
	public static function get_sender_email(): string {
		$settings    = get_option( 'site_mailer_plan_data' );
		$sender_in_used    = get_option( 'site_mailer_sender_email_prefix' );
		$sender_mail = '';
		$domain_verified = 'verified' === Settings::get( Settings::CUSTOM_DOMAIN_VERIFICATION_STATUS );

		if ( ! empty( $settings ) && ! is_wp_error( $settings ) ) {
			if ( ! empty( $settings->sender->sender_in_used ) ) {
				$sender_mail = $settings->sender->sender_in_used;

				$sender = explode( '@', $sender_mail );
				if ( $domain_verified && ! empty( $sender_in_used ) && $sender[0] !== $sender_in_used ) {
					$sender_mail = $sender_in_used . '@' . $sender[1];
				}
			}
		}

		return $sender_mail;
	}

	/**
	 * Get sender from name
	 *
	 * @return string
	 */
	public static function get_sender_from_name(): string {
		return get_option( self::SETTING_PREFIX . 'from_name', 'Site Mailer' );
	}

	/**
	 * Get sender reply to email
	 *
	 * @return string
	 */
	public static function get_sender_reply_email(): string {
		$email = get_option( self::SETTING_PREFIX . 'reply_to_email' );
		if ( ( ! empty( $email ) && ! is_email( $email ) ) || empty( $email ) ) {
			return get_bloginfo( 'admin_email' );
		}
		return $email;
	}

	/**
	 * Get sender from name
	 *
	 * @return string
	 */
	public static function get_keep_log_setting(): string {
		return get_option( self::SETTING_PREFIX . 'keep_log', false );
	}

	/**
	 * Refresh plan data when the settings page is loaded.
	 *
	 * @return void
	 */
	public static function refresh_plan_data() {
		$response = Utils::get_api_client()->make_request(
			'POST',
			'site/info'
		);

		if ( ! empty( $response->site_url ) && Data::get_home_url() !== $response->site_url ) {
			Data::set_home_url( $response->site_url );
		}

		if ( ! is_wp_error( $response ) ) {
			update_option( self::SETTING_PREFIX . 'plan_data', $response );
			update_option( self::SETTING_PREFIX . 'is_valid_plan_data', true );
		} else {
			error_log( esc_html( $response->get_error_message() ) );
			update_option( self::SETTING_PREFIX . 'is_valid_plan_data', false );
		}
	}

	public static function clear_settings_cache() {
		wp_cache_delete( self::SETTING_PREFIX . 'keep_log', 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'close_post_connect_modal', 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'plan_data', 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'sender_email_prefix', 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'from_name', 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'reply_to_email', 'option' );
		wp_cache_delete( Settings::CUSTOM_DOMAIN_DNS_RECORDS, 'option' );
		wp_cache_delete( Settings::CUSTOM_DOMAIN_VERIFICATION_STATUS, 'option' );
		wp_cache_delete( Settings::CUSTOM_DOMAIN_VERIFICATION_RECORDS, 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'custom_domain_verification_start_time', 'option' );
		wp_cache_delete( Config::APP_PREFIX . Data::SUBSCRIPTION_ID, 'option' );
		wp_cache_delete( Config::APP_PREFIX . Data::ACCESS_TOKEN, 'option' );
		wp_cache_delete( Config::APP_PREFIX . Data::CLIENT_ID, 'option' );
		wp_cache_delete( Config::APP_PREFIX . Data::HOME_URL, 'option' );
		wp_cache_delete( Config::APP_PREFIX . Data::OPTION_OWNER_USER_ID, 'option' );
		wp_cache_delete( self::SETTING_PREFIX . 'is_valid_plan_data', 'option'  );
	}

	/**
	 * Get all plugin settings data
	 * @return array
	 */
	public static function get_plugin_settings(): array {
		self::refresh_plan_data();
		self::clear_settings_cache();

		$default_plan_data = [
			'plan'   => [
				'quota' => [
					'allowed' => 0,
					'used'    => 0,
				],
				'name'  => 'None',
			],
			'sender' => [
				'sender_in_used' => '',
			],
			'failed' => true,
		];

		return [
			'isConnected'            => Connect::is_connected(),
			'isUrlMismatch'          => ! Connect_Utils::is_valid_home_url(),
			'subscriptionId'         => Data::get_subscription_id(),
			'keepLog'                => self::get_keep_log_setting(),
			'isDevelopment'          => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'siteUrl'                => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'closePostConnectModal'  => get_option( self::SETTING_PREFIX . 'close_post_connect_modal' ),
			'senderEmail'            => self::get_sender_email() ?? '',
			'fromName'               => self::get_sender_from_name(),
			'replyToEmail'           => self::get_sender_reply_email(),
			'settings'               => get_option( self::SETTING_PREFIX . 'plan_data', $default_plan_data ),
			'customDomainDNSRecords' => Settings::get( Settings::CUSTOM_DOMAIN_DNS_RECORDS ),
			'verificationStatus'     => Settings::get( Settings::CUSTOM_DOMAIN_VERIFICATION_STATUS ),
			'verificationRecords'    => Settings::get( Settings::CUSTOM_DOMAIN_VERIFICATION_RECORDS ),
			'verificationStartTime'  => get_option( self::SETTING_PREFIX . 'custom_domain_verification_start_time' ),
			'isValidPlanData'		 => get_option( self::SETTING_PREFIX . 'is_valid_plan_data' ),
		];
	}

	public function __construct() {
		$this->register_routes();
		$this->register_components( self::component_list() );

		add_action('current_screen', function () {
			add_action( 'admin_notices', [ $this, 'maybe_add_url_mismatch_notice' ] );
		});

		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'in_admin_header', [ $this, 'render_top_bar' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'on_connect_' . Config::APP_PREFIX . '_connected', [ $this, 'on_connect' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
	}
}
