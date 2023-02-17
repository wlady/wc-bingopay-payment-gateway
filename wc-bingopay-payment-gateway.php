<?php
/**
 * Plugin Name: WooCommerce BingoPay Gateway
 * Plugin URI: https://github.com/wlady/payment-gateway-bingopay/
 * Description: WooCommerce BingoPay Gateway
 * Author: Vladimir Zabara <wlady2001@gmail.com>
 * Author URI: https://github.com/wlady/
 * Version: 1.0.0
 * Text Domain: wc-bingopay
 * Requires PHP: 7.4
 * Requires at least: 4.7
 * Tested up to: 6.0
 * WC requires at least: 3.0
 * WC tested up to: 6.2
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or exit;

define( 'BINGOPAY_SUPPORT_PHP', '7.3' );
define( 'BINGOPAY_SUPPORT_WP', '5.0' );
define( 'BINGOPAY_SUPPORT_WC', '3.0' );
define( 'BINGOPAY_DB_VERSION', '1.0' );


/**
 * Add the gateway to WC Available Gateways
 *
 * @param array $gateways all available WC gateways
 *
 * @return array $gateways all WC gateways + offline gateway
 * @since 1.0.0
 *
 */
function wc_bingopay_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_BingoPay';

	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'wc_bingopay_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @param array $links all plugin links
 *
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 * @since 1.0.0
 *
 */
function wc_bingopay_gateway_plugin_links( $links ) {

	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bingopay_gateway' ) . '">' . esc_html__( 'Settings',
			'wc-bingopay' ) . '</a>',
	];

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_bingopay_gateway_plugin_links' );


/**
 * BingoPay Payment Gateway
 *
 * @class        WC_Gateway_BingoPay
 * @extends      WC_Payment_Gateway
 * @package      WooCommerce/Classes/Payment
 * @author       Vladimir Zabara
 */
add_action( 'plugins_loaded', 'wc_bingopay_gateway_init', 11 );


define( 'BINGOPAY_CALLBACK_URL', add_query_arg( 'wc-api', 'WC_Gateway_BingoPay', home_url( '/' ) ) );

function wc_bingopay_gateway_init() {

	if ( class_exists( "WC_Payment_Gateway_CC", false ) ) {

		class WC_Gateway_BingoPay extends WC_Payment_Gateway_CC {

			const RETURN_CODE_OK = 200;
			const RETURN_CODE_ERROR = 418;

			const STATUS_IN_PROCESS = 1;
			const STATUS_APPROVED = 2;
			const STATUS_DENIED = 3;
			const STATUS_WAITING_CONFIRMATION = 5;

			const API_USER_LOGIN_PATH = 'api/user/login';
			const API_CREATE_PAYMENT_PATH = 'api/transaction/creates/payments';

			public $supports = [
				'products',
			];

			public function __construct() {

				$this->id                 = 'bingopay_gateway';
				$this->icon               = '';
				$this->has_fields         = true;
				$this->method_title       = __( 'BingoPay', 'wc-bingopay' );
				$this->method_description = __( 'Make payments with BingoPay', 'wc-bingopay' );

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Define user set variables
				$this->title        = $this->get_option( 'title' );
				$this->description  = $this->get_option( 'description' );
				$this->instructions = $this->get_option( 'instructions', $this->description );

				// Actions
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
					[ $this, 'process_admin_options' ] );
				add_action( 'woocommerce_credit_card_form_start', [ $this, 'pay_description' ] );
				add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'add_instructions' ] );
				add_action( 'woocommerce_email_before_order_table', [ $this, 'add_instructions' ] );
				add_filter( 'transaction_details_by_order_id', [ $this, 'transaction_details_by_order_id' ], 10, 2 );
				add_action( 'woocommerce_api_wc_gateway_bingopay', [ $this, 'check_bingopay_response' ] );

				$this->init();
			}

			protected function init() {
				if ( ! $this->check_environment() ) {
					return;
				}

				if ( get_site_option( 'BINGOPAY_DB_VERSION' ) != BINGOPAY_DB_VERSION ) {
					$this->install_db();
				}
			}

			public function install_db() {
				global $wpdb;

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

				$installed_ver = get_option( "BINGOPAY_DB_VERSION" );
				if ( $installed_ver != BINGOPAY_DB_VERSION ) {
					// See https://codex.wordpress.org/Creating_Tables_with_Plugins
					$sql = "CREATE TABLE " . $wpdb->prefix . "bingopay_transactions (
                     id int(11) NOT NULL AUTO_INCREMENT,
                     order_id int(11) NOT NULL,
                     transaction_id char(20) COLLATE utf8_unicode_ci DEFAULT NULL,
                     data text COLLATE utf8_unicode_ci DEFAULT NULL,
                     date_time datetime NOT NULL,
                     PRIMARY KEY  (id),
                     UNIQUE KEY  transaction_id (transaction_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
					dbDelta( $sql );

					update_option( 'BINGOPAY_DB_VERSION', BINGOPAY_DB_VERSION );
				}
			}

			/**
			 * Initialize Gateway Settings Form Fields
			 */
			public function init_form_fields() {

				$this->form_fields = apply_filters( 'wc_bingopay_form_fields', [

					'enabled' => [
						'title'   => __( 'Enable/Disable', 'wc-bingopay' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable BingoPay', 'wc-bingopay' ),
						'default' => 'yes',
					],

					'title' => [
						'title'       => __( 'Title', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'This controls the title for the payment method the customer sees during checkout.',
							'wc-bingopay' ),
						'default'     => __( 'BingoPay', 'wc-bingopay' ),
						'desc_tip'    => true,
					],

					'description' => [
						'title'       => __( 'Description', 'wc-bingopay' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your checkout page.',
							'wc-bingopay' ),
						'default'     => '',
						'desc_tip'    => true,
					],

					'instructions' => [
						'title'       => __( 'Instructions', 'wc-bingopay' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page and emails.',
							'wc-bingopay' ),
						'default'     => '',
						'desc_tip'    => true,
					],

					'account_settings' => [
						'title'       => __( 'Account Settings', 'wc-bingopay' ),
						'type'        => 'title',
						'description' => '',
					],

					'bingopay_api_url' => [
						'title'       => __( 'API Url', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'Contact BingoPay integration team to get correct URL.', 'wc-bingopay' ),
					],

					'bingopay_login' => [
						'title'       => __( 'Login', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'Contact BingoPay integration team to get Your login.',
							'wc-bingopay' ),
					],

					'bingopay_password' => [
						'title'       => __( 'Password', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'Contact BingoPay integration team to get Your password.',
							'wc-bingopay' ),
					],

					'bingopay_payer_id' => [
						'title'       => __( 'Payer ID', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'Contact BingoPay integration team to get Your Payer ID.',
							'wc-bingopay' ),
					],

					'bingopay_token' => [
						'title'       => __( 'Token', 'wc-bingopay' ),
						'type'        => 'text',
						'description' => __( 'To reset the token leave this field empty.', 'wc-bingopay' ),
					],

				] );
			}

			public function payment_fields() {
				$this->form();
			}

			/**
			 * Outputs fields for entering credit card information
			 *
			 * @since 2.6.0
			 */
			public function form() {
				wp_enqueue_script( 'wc-credit-card-form' );

				$fields = [];

				$default_fields = [
					'card-number-field'      => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number',
							'wc-bingopay' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
                </p>',
					'card-expiry-field'      => '<p class="form-row form-row-first">
                    <label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)',
							'wc-bingopay' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="7" placeholder="' . esc_attr__( 'MM / YY',
							'wc-bingopay' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
                </p>',
					'<p class="form-row form-row-last">
                    <label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'wc-bingopay' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC',
						'wc-bingopay' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
                </p>',
					'card-holder-name-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( $this->id ) . '-card-holder-name">' . esc_html__( 'Holder Name',
							'wc-bingopay' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-holder-name" class="input-text wc-credit-card-form-card-holder-name" autocomplete="cc-holder-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="' . esc_attr__( 'Holder Name',
							'wc-bingopay' ) . '" ' . $this->field_name( 'card-holder-name' ) . ' />
                </p>',
				];

				$fields = wp_parse_args( $fields,
					apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
				?>

				<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
                <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form"
                          class='wc-credit-card-form wc-payment-form'>
					<?php
					foreach ( $fields as $field ) {
						_e( $field );
					}
					?>
                    <div class="clear"></div>
                </fieldset>
				<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
				<?php
			}

			/**
			 * Add instructions
			 *
			 * @access public
			 */
			public function add_instructions() {
				if ( $instructions = $this->get_option( 'instructions' ) ) {
					_e( wpautop( wptexturize( $instructions . PHP_EOL ) ) );
				}
			}

			/**
			 * Add pay description
			 *
			 * @access public
			 */
			public function pay_description() {
				if ( $description = $this->get_option( 'description' ) ) {
					_e( wpautop( wptexturize( $description . PHP_EOL ) ) );
				}
			}

			public function check_bingopay_response() {
				$transaction_number = sanitize_text_field( $_GET( 'transactionNumber' ) );
				$transaction_status = sanitize_text_field( $_GET( 'transactionStatus' ) );

				wc_get_logger()->info(
					json_encode( [ $transaction_number, $transaction_status ], JSON_PRETTY_PRINT ),
					[
						'source' => 'bingopay-info',
					]
				);
				switch ( $transaction_status ) {
					case self::STATUS_APPROVED:
						$transaction = $this->transaction_details_by_transaction_id( $transaction_number );
						$order       = wc_get_order( $transaction['order_id'] );
						$order->payment_complete( $transaction_number );
						break;
				}
			}

			/**
			 * Process the payment and return the result
			 *
			 * @param int $order_id
			 *
			 * @return mixed
			 */
			public function process_payment( $order_id ) {

				$order    = wc_get_order( $order_id );
				$currency = $order->get_currency();
				$amount   = $order->get_total();

				$settings = [
					'url'      => rtrim( $this->get_option( 'bingopay_api_url' ), '/' ),
					'login'    => $this->get_option( 'bingopay_login' ),
					'password' => $this->get_option( 'bingopay_password' ),
					'payer_id' => $this->get_option( 'bingopay_payer_id' ),
					'token'    => $this->get_option( 'bingopay_token' ),
				];

				$settings = apply_filters( 'bingopay_settings', $settings );

				if ( empty( $settings['url'] ) || empty( $settings['login'] ) || empty( $settings['password'] ) || empty( $settings['payer_id'] ) ) {
					wc_add_notice( esc_html__( 'Incorrect BingoPay Gateway Settings', 'wc-bingopay' ),
						'error' );

					return false;
				}

				if ( empty( $settings['token'] ) ) {
					$response = WC_Gateway_BingoPay_API::get_token( $settings );
					if ( ! empty( $response['status'] ) && $response['status'] == self::RETURN_CODE_OK ) {
						$settings['token'] = sanitize_text_field( $response['result'] ?? '' );
						$this->update_option( 'token', $settings['token'] );
					} else {
						wc_add_notice( esc_html__( 'Cannot get token', 'wc-bingopay' ),
							'error' );

						return false;
					}
				}

				$card_number   = str_replace( ' ', '', sanitize_text_field( $_POST['bingopay_gateway-card-number'] ) );
				$payload = [
					'order_id' => $order_id,
					'amount' => $amount,
					'currency' => $currency,
					'card_number' => $card_number,
                    'card_expire' => str_replace( ' ', '', sanitize_text_field( $_POST['bingopay_gateway-card-expiry'] ) ),
					'card_cvc' => sanitize_text_field( $_POST['bingopay_gateway-card-cvc'] ),
					'card_holder' => sanitize_text_field( $_POST['bingopay_gateway-card-holder-name'] ),
                    'masked_cc_num' => substr( $card_number, 0, 4 ) . '****' . substr( $card_number, - 4 ),
                    'log_date' => date( 'c' ),
                ];
//				$card_expire   = str_replace( ' ', '', sanitize_text_field( $_POST['bingopay_gateway-card-expiry'] ) );
//				$card_holder   = sanitize_text_field( $_POST['bingopay_gateway-card-holder-name'] );
//				$card_cvc      = sanitize_text_field( $_POST['bingopay_gateway-card-cvc'] );
//				$masked_cc_num = substr( $card_number, 0, 4 ) . '****' . substr( $card_number, - 4 );
//				$log_date      = date( 'c' );
				$log_info      = "
Order ID: {$payload['order_id']}
Payer ID: {$settings['payer_id']}
Amount: {$payload['amount']}
Date: {$payload['log_date']}
CC#: {$payload['masked_cc_num']}
CVV#: {$payload['card_cvc']}
Expire: {$payload['card_expire']}
Card Holder: {$payload['card_holder']}
Currency: {$payload['currency']}
";

//				$args = [
//					'headers' => [
//						'Authorization' => 'Bearer ' . $settings['token'],
//						'Content-Type'  => 'application/json',
//					],
//					'body'    => json_encode(
//						[
//							'payer_id'     => $settings['payer_id'],
//							'owner'        => $payload['card_holder'],
//							'card_number'  => $payload['card_number'],
//							'cvv'          => $payload['card_cvc'],
//							'validity'     => $payload['card_expire'],
//							'amount'       => $payload['amount'],
//							'currency'     => $payload['currency'],
//							'callback_url' => BINGOPAY_CALLBACK_URL,
//						]
//					),
//				];
//				$url  = sprintf( '%s/%s?order_id=%d', $settings['url'], self::API_CREATE_PAYMENT_PATH, $order_id );
//				wc_get_logger()->info(
//					json_encode( [ $url, $args ], JSON_PRETTY_PRINT ),
//					[
//						'source' => 'bingopay-info',
//					]
//				);
//				$remote_response = wp_remote_post( $url, $args );
//				$body            = wp_remote_retrieve_body( $remote_response );
//				$response        = json_decode( $body, true );
//				wc_get_logger()->info(
//					json_encode( $response ),
//					[
//						'source' => 'bingopay-info',
//					]
//				);
				$response = WC_Gateway_BingoPay_API::create_transaction( $settings, $payload );
				if ($response) {
					switch ( $response['status'] ) {
						case self::RETURN_CODE_OK:
							$transaction_id = $response['result']['transaction'];
							switch ( $response['result']['status'] ) {
								case self::STATUS_IN_PROCESS:
								case self::STATUS_APPROVED:
									$this->save_transaction( $order_id, $transaction_id, $log_info );
									$order->payment_complete( 1 );
									// Remove cart
									WC()->cart->empty_cart();

									// Redirect to Thank You page
									return [
										'result'   => 'success',
										'redirect' => $this->get_return_url( $order ),
									];
								case self::STATUS_DENIED:
									$this->save_transaction( $order_id, $transaction_id, $log_info );
									wc_add_notice( esc_html__( 'The transaction was NOT completed due to errors' ),
										'error' );
									wc_get_logger()->critical(
										sprintf( 'Transaction Error: payment denied, %s', $log_info ),
										[
											'source' => 'bingopay-errors',
										]
									);

									return false;
								case self::STATUS_WAITING_CONFIRMATION:
									$this->save_transaction( $order_id, $transaction_id, $log_info );
									$order->update_status( 'on-hold',
										__( 'Awaiting payment confirmation', 'wc-bingopay' ) );
									// Remove cart
									WC()->cart->empty_cart();

									// Return thankyou redirect
									return [
										'result'   => 'success',
										'redirect' => $this->get_return_url( $order ),
									];
							}
							break;
						case self::RETURN_CODE_ERROR:
							$errors = '[' . implode( ', ', $response['errors'] ) . ']';
							wc_add_notice( esc_html__( $errors ), 'error' );
							wc_get_logger()->critical(
								sprintf( 'Transaction Error: %s, %s', $errors, $log_info ),
								[
									'source' => 'bingopay-errors',
								]
							);

							return false;
						default:
							$error = $response['errors'] ?? 'Unknown error';
							wc_add_notice( esc_html__( $error ), 'error' );
							wc_get_logger()->critical(
								sprintf( 'Transaction Error: %s, %s', $error, $log_info ),
								[
									'source' => 'bingopay-errors',
								]
							);

							return false;
					}
				} else {
					$error = 'Unknown error';
					wc_add_notice( esc_html__( $error ), 'error' );
					wc_get_logger()->critical(
						sprintf( 'Transaction Error: %s, %s', $error, $log_info ),
						[
							'source' => 'bingopay-errors',
						]
					);

					return false;
				}
			}

			private function save_transaction( $order_id, $transaction_id, $details ) {
				global $wpdb;
				// save transaction response
				$wpdb->insert( $wpdb->prefix . 'bingopay_transactions', [
					'order_id'       => $order_id,
					'transaction_id' => $transaction_id,
					'data'           => $details,
					'date_time'      => date( 'Y-m-d H:i:s' ),
				] );
			}

			private function transaction_details_by_transaction_id( $transaction_id ) {
				global $wpdb;

				return $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bingopay_transactions WHERE transaction_id=%d",
						$transaction_id ),
					ARRAY_A
				);
			}

			public function transaction_details_by_order_id( $order_id, $details = [] ) {
				global $wpdb;

				$res = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bingopay_transactions WHERE order_id=%d", $order_id ),
					ARRAY_N
				);
				if ( ! empty( $res ) ) {
					$details['transaction'] = $res[0];
				}

				return $details;
			}

			/**
			 * Check if environment meets requirements
			 *
			 * @access public
			 * @return bool
			 */
			public function check_environment() {
				$is_ok = true;

				// Check PHP version
				if ( ! version_compare( PHP_VERSION, BINGOPAY_SUPPORT_PHP, '>=' ) ) {
					// Add notice
					add_action( 'admin_notices', function () {
						echo '<div class="error"><p>'
						     . esc_html__( sprintf( 'WooCommerce BingoPay Gateway requires PHP version %s or later.',
								BINGOPAY_SUPPORT_PHP ), 'wc-bingopay' )
						     . '</p></div>';
					} );
					$is_ok = false;
				}

				// Check WordPress version
				if ( ! $this->wp_version_gte( BINGOPAY_SUPPORT_WP ) ) {
					add_action( 'admin_notices', function () {
						echo '<div class="error"><p>'
						     . esc_html__( sprintf( 'WooCommerce BingoPay Gateway requires WordPress version %s or later. Please update WordPress to use this plugin.',
								BINGOPAY_SUPPORT_WP ), 'wc-bingopay' )
						     . '</p></div>';
					} );
					$is_ok = false;
				}

				// Check if WooCommerce is installed and enabled
				if ( ! class_exists( 'WooCommerce' ) ) {
					add_action( 'admin_notices', function () {
						echo '<div class="error"><p>'
						     . esc_html__( 'WooCommerce BingoPay Gateway requires WooCommerce to be active.',
								'wc-bingopay' )
						     . '</p></div>';
					} );
					$is_ok = false;
				} elseif ( ! $this->wc_version_gte( BINGOPAY_SUPPORT_WC ) ) {
					add_action( 'admin_notices', function () {
						echo '<div class="error"><p>'
						     . esc_html__( sprintf( 'WooCommerce BingoPay Gateway requires WooCommerce version %s or later.',
								BINGOPAY_SUPPORT_WC ), 'wc-bingopay' )
						     . '</p></div>';
					} );
					$is_ok = false;
				}

				return $is_ok;
			}

			/**
			 * Check WooCommerce version
			 *
			 * @access public
			 *
			 * @param string $version
			 *
			 * @return bool
			 */
			public static function wc_version_gte( $version ) {
				if ( defined( 'WC_VERSION' ) && WC_VERSION ) {
					return version_compare( WC_VERSION, $version, '>=' );
				} elseif ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) {
					return version_compare( WOOCOMMERCE_VERSION, $version, '>=' );
				} else {
					return false;
				}
			}

			/**
			 * Check WordPress version
			 *
			 * @access public
			 *
			 * @param string $version
			 *
			 * @return bool
			 */
			public static function wp_version_gte( $version ) {
				$wp_version = get_bloginfo( 'version' );

				// Treat release candidate strings
				$wp_version = preg_replace( '/-RC.+/i', '', $wp_version );

				if ( $wp_version ) {
					return version_compare( $wp_version, $version, '>=' );
				}

				return false;
			}

			/**
			 * Check PHP version
			 *
			 * @access public
			 *
			 * @param string $version
			 *
			 * @return bool
			 */
			public static function php_version_gte( $version ) {
				return version_compare( PHP_VERSION, $version, '>=' );
			}
		}

		class WC_Gateway_BingoPay_API {

			const API_USER_LOGIN_PATH = 'api/user/login';
			const API_CREATE_PAYMENT_PATH = 'api/transaction/creates/payments';

			public static function get_token( $settings ) {
				$args   = [
					'headers' => [
						'Content-Type' => 'application/json',
					],
					'body'    => json_encode(
						[
							'login'    => $settings['login'],
							'password' => $settings['password'],
						]
					),
				];
				$url    = sprintf( '%s/%s', $settings['url'], self::API_USER_LOGIN_PATH );
				$result = wp_remote_post( $url, $args );
				if ( ! is_wp_error( $result ) ) {
					return json_decode( wp_remote_retrieve_body( $result ), true );
				}

				return false;
			}

			public static function create_transaction( $settings, $payload ) {
				$args = [
					'headers' => [
						'Authorization' => 'Bearer ' . $settings['token'],
						'Content-Type'  => 'application/json',
					],
					'body'    => json_encode(
						[
							'payer_id'     => $settings['payer_id'],
							'owner'        => $payload['card_holder'],
							'card_number'  => $payload['card_number'],
							'cvv'          => $payload['card_cvc'],
							'validity'     => $payload['card_expire'],
							'amount'       => $payload['amount'],
							'currency'     => $payload['currency'],
							'callback_url' => BINGOPAY_CALLBACK_URL,
						]
					),
				];
				$url  = sprintf( '%s/%s?order_id=%d', $settings['url'], self::API_CREATE_PAYMENT_PATH, $payload['order_id'] );
				$result = wp_remote_post( $url, $args );
				if ( ! is_wp_error( $result ) ) {
					return json_decode( wp_remote_retrieve_body( $result ), true );
				}

				return false;
			}
		}
	}
}