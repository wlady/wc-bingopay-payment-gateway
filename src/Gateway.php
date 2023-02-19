<?php

namespace WCBingopay;

/**
 * BingoPay Payment Gateway
 *
 * @class        WCBingoPayGateway
 * @extends      WC_Payment_Gateway_CC
 * @package      WooCommerce/Classes/Payment
 * @author       Vladimir Zabara
 */
if ( class_exists( "WC_Payment_Gateway_CC", false ) ) {
	class Gateway extends \WC_Payment_Gateway_CC {

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
			add_action( 'woocommerce_credit_card_form_start', [ $this, 'add_description' ] );
			add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'add_instructions' ] );
			add_action( 'woocommerce_email_before_order_table', [ $this, 'add_instructions' ] );
			add_filter( 'transaction_details_by_order_id', [ $this, 'transaction_details_by_order_id' ], 10, 2 );
			add_action( 'woocommerce_api_wc_gateway_bingopay', [ $this, 'check_response' ] );

			$this->init();
		}

		private function init() {
			if ( ! Checker::check_environment() ) {
				return;
			}

			if ( get_site_option( 'BINGOPAY_DB_VERSION' ) != BINGOPAY_DB_VERSION ) {
				DBHelper::install_db();
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
					'description' => __( 'The title for the payment method.', 'wc-bingopay' ),
					'default'     => __( 'BingoPay', 'wc-bingopay' ),
					'desc_tip'    => true,
				],

				'description' => [
					'title'       => __( 'Description', 'wc-bingopay' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description.', 'wc-bingopay' ),
					'default'     => '',
					'desc_tip'    => true,
				],

				'instructions' => [
					'title'       => __( 'Instructions', 'wc-bingopay' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions will be added to the emails.', 'wc-bingopay' ),
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
					'description' => __( 'Contact BingoPay integration team to get Your login.', 'wc-bingopay' ),
				],

				'bingopay_password' => [
					'title'       => __( 'Password', 'wc-bingopay' ),
					'type'        => 'text',
					'description' => __( 'Contact BingoPay integration team to get Your password.', 'wc-bingopay' ),
				],

				'bingopay_payer_id' => [
					'title'       => __( 'Payer ID', 'wc-bingopay' ),
					'type'        => 'text',
					'description' => __( 'Contact BingoPay integration team to get Your Payer ID.', 'wc-bingopay' ),
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
				'card-currency-field'    => '<input id="' . esc_attr( $this->id ) . '-card-currency" type="hidden" value="' . get_woocommerce_currency() . '" ' . $this->field_name( 'card-currency-code' ) . ' />',
			];

			$fields = wp_parse_args( $fields,
				apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
			?>

			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
            <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
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
		 * Add pay instructions
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
		public function add_description() {
			if ( $description = $this->get_option( 'description' ) ) {
				_e( wpautop( wptexturize( $description . PHP_EOL ) ) );
			}
		}

		public function check_response() {
			$transaction_number = sanitize_text_field( $_GET['transactionNumber'] );
			$transaction_status = sanitize_text_field( $_GET['transactionStatus'] );
			if ( BINGOPAY_DEBUG ) {
				Logger::info( [ $transaction_number, $transaction_status ] );
			}
			switch ( $transaction_status ) {
				case Api::STATUS_APPROVED:
					$transaction = DBHelper::transaction_details_by_transaction_id( $transaction_number );
					if ( BINGOPAY_DEBUG ) {
						Logger::info( $transaction );
					}
					if ( $transaction ) {
						$order = wc_get_order( $transaction['order_id'] );
						$order->payment_complete( $transaction_number );
					} else {
						Logger::error( esc_html__( 'Transaction is not found', 'wc-bingopay' ), $transaction_number );
					}
					break;
			}
		}

		private function get_settings() {
			$settings = [
				'url'      => rtrim( $this->get_option( 'bingopay_api_url' ), '/' ),
				'login'    => $this->get_option( 'bingopay_login' ),
				'password' => $this->get_option( 'bingopay_password' ),
				'payer_id' => $this->get_option( 'bingopay_payer_id' ),
				'token'    => $this->get_option( 'bingopay_token' ),
			];

			$settings = apply_filters( 'bingopay_settings', $settings );

			if ( empty( $settings['url'] ) || empty( $settings['login'] ) || empty( $settings['password'] ) || empty( $settings['payer_id'] ) ) {
				wc_add_notice( esc_html__( 'Incorrect BingoPay Gateway Settings', 'wc-bingopay' ), 'error' );

				return false;
			}
			if ( empty( $settings['token'] ) ) {
				$response = Api::get_token( $settings );
				if ( ! empty( $response['status'] ) && $response['status'] == Api::RETURN_CODE_OK ) {
					$settings['bingopay_token'] = sanitize_text_field( $response['result'] ?? '' );
					$this->update_option( 'bingopay_token', $settings['bingopay_token'] );
				} else {
					$error = 'Cannot get token';
					wc_add_notice( esc_html__( $error, 'wc-bingopay' ), 'error' );
					Logger::error( $error, $settings );

					return false;
				}
			}

			return $settings;
		}

		public function check_3ds() {
			$settings = $this->get_settings();
			$payload  = [
				'amount'      => sanitize_text_field( $_POST['amount'] ),
				'currency'    => sanitize_text_field( $_POST['bingopay_gateway-card-currency-code'] ),
				'card_number' => sanitize_text_field( $_POST['bingopay_gateway-card-number'] ),
				'card_expire' => str_replace( ' ', '',
					sanitize_text_field( $_POST['bingopay_gateway-card-expiry'] ) ),
				'card_cvc'    => sanitize_text_field( $_POST['bingopay_gateway-card-cvc'] ),
				'card_holder' => sanitize_text_field( $_POST['bingopay_gateway-card-holder-name'] ),
			];

			$response = Api::create_transaction( $settings, $payload );
			if ( BINGOPAY_DEBUG ) {
				Logger::info( $response );
			}
			if ( ! empty( $response['result'] ) ) {
				return $response['result'];
			}

			return false;
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

			$settings = $this->get_settings();

			$card_number = str_replace( ' ', '', sanitize_text_field( $_POST['bingopay_gateway-card-number'] ) );
			$payload     = [
				'order_id'      => $order_id,
				'amount'        => $amount,
				'currency'      => $currency,
				'card_number'   => $card_number,
				'card_expire'   => str_replace( ' ', '',
					sanitize_text_field( $_POST['bingopay_gateway-card-expiry'] ) ),
				'card_cvc'      => sanitize_text_field( $_POST['bingopay_gateway-card-cvc'] ),
				'card_holder'   => sanitize_text_field( $_POST['bingopay_gateway-card-holder-name'] ),
				'masked_cc_num' => substr( $card_number, 0, 4 ) . '****' . substr( $card_number, - 4 ),
				'log_date'      => date( 'c' ),
			];
			$log_info    = [
				'Order ID'    => $payload['order_id'],
				'Payer ID'    => $settings['payer_id'],
				'Amount'      => $payload['amount'],
				'Date'        => $payload['log_date'],
				'CC#'         => $payload['masked_cc_num'],
				'CVV#'        => $payload['card_cvc'],
				'Expire'      => $payload['card_expire'],
				'Card Holder' => $payload['card_holder'],
				'Currency'    => $payload['currency'],
			];

			$response = Api::create_transaction( $settings, $payload );
			if ( BINGOPAY_DEBUG ) {
				Logger::info( $response );
			}
			if ( $response ) {
				switch ( $response['status'] ) {
					case Api::RETURN_CODE_OK:
						$transaction_id = $response['result']['transaction'];
						switch ( $response['result']['status'] ) {
							case Api::STATUS_IN_PROCESS:
							case Api::STATUS_APPROVED:
								DBHelper::save_transaction( $order_id, $transaction_id, $log_info );
								$order->payment_complete( 1 );
								// Remove cart
								WC()->cart->empty_cart();

								// Redirect to Thank You page
								return [
									'result'   => 'success',
									'redirect' => $this->get_return_url( $order ),
								];
							case Api::STATUS_DENIED:
								DBHelper::save_transaction( $order_id, $transaction_id, $log_info );
								$error = 'The transaction was NOT completed due to errors';
								wc_add_notice( esc_html__( $error ), 'error' );
								Logger::error( $error, $log_info );

								return false;
							case Api::STATUS_WAITING_CONFIRMATION:
								DBHelper::save_transaction( $order_id, $transaction_id, $log_info );
								$order->update_status( 'on-hold',
									__( 'Awaiting payment confirmation', 'wc-bingopay' ) );
								// show 3DS window
								$url = $response['result']['redirect_url'];
								// Remove cart
								WC()->cart->empty_cart();

								// Return thankyou redirect
								return [
									'result'   => 'success',
									'redirect' => $this->get_return_url( $order ),
								];
						}
						break;
					case Api::RETURN_CODE_ERROR:
						$error = '[' . (
							is_array( $response['errors'] )
								? implode( ', ', $response['errors'] )
								: $response['errors']
							) . ']';
						wc_add_notice( esc_html__( $error ), 'error' );
						Logger::error( $error, $log_info );

						return false;
					default:
						$error = $response['errors'] ?? 'Unknown error';
						wc_add_notice( esc_html__( $error ), 'error' );
						Logger::error( $error, $log_info );

						return false;
				}
			} else {
				$error = 'Unknown error';
				wc_add_notice( esc_html__( $error ), 'error' );
				Logger::error( $error, $log_info );

				return false;
			}
		}
	}
}