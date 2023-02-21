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
			'refunds',
		];

		private $addresses = [
			'billing'  => [
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
				'email',
				'phone',
			],
			'shipping' => [
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
				'phone',
			],
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
			$this->instructions = $this->get_option( 'instructions' );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				[ $this, 'process_admin_options' ] );
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

            <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
				<?php _e( wpautop( wptexturize( $this->get_option( 'description' ) ) ) ); ?>
				<?php
				foreach ( $fields as $field ) {
					_e( $field );
				}
				?>
                <div class="clear"></div>
            </fieldset>
			<?php
		}

		/**
		 * Add pay instructions
		 *
		 * @access public
		 */
		public function add_instructions() {
			_e( wpautop( wptexturize( $this->get_option( 'instructions' ) . PHP_EOL ) ) );
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

		public function check_response() {
			$transaction_number = sanitize_text_field( $_GET['transactionNumber'] );
			$transaction_status = sanitize_text_field( $_GET['transactionStatus'] );
			$order_id           = sanitize_text_field( $_GET['order_id'] );
			if ( BINGOPAY_DEBUG ) {
				Logger::info( [ $transaction_number, $transaction_status, $order_id ] );
			}
			if ( $transaction_status == Api::STATUS_APPROVED ) {
				$transaction_details = DBHelper::transaction_details_by_transaction_id( $transaction_number );
				// restore session & cart
				wp_set_current_user( $transaction_details['data']['payment']['user_id'] );
				WC()->cart->set_cart_contents( $transaction_details['data']['cart'] );
				WC()->cart->calculate_totals();
				$set_billing_address = false;
				if ( empty( $order_id ) ) {
					$order_id            = ( new \WC_Checkout() )->create_order( [] );
					$set_billing_address = true;
				}
				$order = wc_get_order( $order_id );
				$order->set_payment_method( $this->id );
				$order->set_transaction_id( $transaction_number );
				if ( $set_billing_address ) {
					$this->set_address( $order, $transaction_details['data'] ?? [], 'billing' );
					$this->set_address( $order, $transaction_details['data'] ?? [], 'shipping' );
					$order->save();
				}
				$order->payment_complete();
				$order->update_status( 'processing' );
				// save for refund operation
				update_post_meta( $order_id, '_transaction_id', $transaction_number );
				$transaction_details['data']['payment']['order_id'] = $order_id;
				DBHelper::update_transaction( $transaction_number, $transaction_details['data'] );
				$url = add_query_arg( [ 'redirect' => 'parent' ], $this->get_return_url( $order ) );
			} else {
				wc_add_notice( Api::get_error_message( $transaction_status ) );
				$url = add_query_arg( [ 'redirect' => 'parent' ], wc_get_endpoint_url( 'cart' ) );
			}
			wp_safe_redirect( $url );
			exit;
		}

		public function check_3ds() {
			$settings = $this->get_settings();
			$payload  = [
				'user_id'     => get_current_user_id(),
				'order_id'    => sanitize_text_field( $_POST['order_id'] ),
				'amount'      => sanitize_text_field( $_POST['amount'] ),
				'currency'    => sanitize_text_field( $_POST['bingopay_gateway-card-currency-code'] ),
				'card_number' => sanitize_text_field( $_POST['bingopay_gateway-card-number'] ),
				'card_expire' => str_replace( ' ', '',
					sanitize_text_field( $_POST['bingopay_gateway-card-expiry'] ) ),
				'card_cvc'    => sanitize_text_field( $_POST['bingopay_gateway-card-cvc'] ),
				'card_holder' => sanitize_text_field( $_POST['bingopay_gateway-card-holder-name'] ),
			];

			$response = Api::create_transaction( $settings, $payload );
			if ( ! empty( $response['result'] ) ) {
				// mask card number
				$payload['card_number'] = '****' . substr( $payload['card_number'], - 4 );
				$details                = [
					'payment'  => $payload,
					'cart'     => WC()->cart->get_cart_contents(),
					'billing'  => $this->get_address( 'billing' ),
					'shipping' => $this->get_address( 'shipping' ),
				];
				DBHelper::create_transaction( $response['result']['transaction'], $details );

				return $response['result'];
			} else {
                return [
                    'error' => true,
                    'message' => Api::get_error_by_status( $response ),
                ];
			}
		}

		private function get_address( $source ) {
			$address = [];

			foreach ( $this->addresses[ $source ] as $key ) {
				$address[ $key ] = sanitize_text_field( $_POST["{$source}_{$key}"] );
			}

			return $address;
		}

		private function set_address( $order, $data, $source ) {
			foreach ( $data[ $source ] ?? [] as $key => $value ) {
				if ( is_callable( [ $order, "set_{$source}_{$key}" ] ) ) {
					$order->{"set_{$source}_{$key}"}( $value );
				}
			}
		}

		/**
		 * @param int $order_id
		 * @param float|null $amount
		 * @param string $reason
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order          = wc_get_order( $order_id );
			$transaction_id = $order->get_meta( '_transaction_id', true );
			$settings       = $this->get_settings();
			$payload        = [
				'transaction_id' => $transaction_id,
			];
			$response       = Api::refund( $settings, $payload );

			return ( ! empty( $response['status'] ) && $response['status'] == Api::RETURN_CODE_OK );
		}
	}
}