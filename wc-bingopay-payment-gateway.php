<?php
/**
 * Plugin Name: WooCommerce BingoPay Gateway
 * Plugin URI: https://github.com/wlady/payment-gateway-bingopay/
 * Description: WooCommerce BingoPay Gateway
 * Author: Vladimir Zabara <wlady2001@gmail.com>
 * Author URI: https://github.com/wlady/
 * Version: 1.1.0
 * Text Domain: wc-bingopay
 * Requires PHP: 7.4
 * Requires at least: 4.7
 * Tested up to: 6.0
 * WC requires at least: 3.0
 * WC tested up to: 6.2
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WCBingopay;


defined( 'ABSPATH' ) or exit;

define( 'BINGOPAY_VERSION', '1.1.0' );
define( 'BINGOPAY_SUPPORT_PHP', '7.4' );
define( 'BINGOPAY_SUPPORT_WP', '5.0' );
define( 'BINGOPAY_SUPPORT_WC', '3.0' );
define( 'BINGOPAY_PLUGIN_NAME', plugin_basename( __FILE__ ) );

const BINGOPAY_DEBUG = false;

include( dirname( __FILE__ ) . '/vendor/autoload.php' );

/**
 * Add the gateway to WC Available Gateways
 *
 * @param array $gateways all available WC gateways
 *
 * @return array $gateways all WC gateways + offline gateway
 * @since 1.0.0
 *
 */
add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
	$gateways[] = 'WCBingopay\Gateway';

	return $gateways;
}
);

/**
 * Adds plugin page links
 *
 * @param array $links all plugin links
 *
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 * @since 1.0.0
 *
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bingopay_gateway' ) . '">' .
		esc_html__( 'Settings', 'wc-bingopay' ) . '</a>',
	];

	return array_merge( $plugin_links, $links );
}
);

add_action( 'plugins_loaded', function () {

	define( 'BINGOPAY_CALLBACK_URL', add_query_arg( 'wc-api', Gateway::WC_GATEWAY_NAME, home_url() ) );

	$ajax_callback = function () {
		$res = ( new Gateway )->check_3ds();
		echo json_encode( [
			'data' => $res,
		] );
		wp_die();
	};

	add_filter( 'wp_ajax_bignopay_3ds_form', $ajax_callback );
	add_filter( 'wp_ajax_nopriv_bignopay_3ds_form', $ajax_callback );

	add_action( 'woocommerce_checkout_before_order_review', function () {
		$cart_total = WC()->cart->get_cart_contents_total();
		echo <<<EOB
        <script>
            var currentOrderId = '';
            var currentOrderTotal = {$cart_total};
        </script>
EOB;
	} );

	add_action( 'woocommerce_pay_order_before_submit', function () {
		$order  = wc_get_order( wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) ) );
		$amount = $order->get_total();
		echo <<<EOB
        <script>
            var currentOrderId = {$order->get_id()};
            var currentOrderTotal = {$amount};
        </script>
EOB;
	} );

	add_action( 'wp_head', function () {
		if ( is_checkout() || is_cart() ) {
			$window = sanitize_text_field( $_GET['redirect'] ?? '' );
			if ( $window === 'parent' ) {
				$parts = parse_url( $_SERVER['REQUEST_URI'] );
				parse_str( $parts['query'], $params );
				unset( $params['redirect'] );
				$parts['query'] = http_build_query( $params );
				$url            = ( $parts['path'] ?? '/' ).
				                  ( ! empty( $parts['query'] ) ? ( '?' . $parts['query'] ) : '' );
				echo "<script>window.top.location = '{$url}';</script>";
				exit;
			}
		}
	} );

	add_action( 'wp_footer', function () {
		if ( is_checkout() ) {
			?>
            <style>
                .bingopay-loader {
                    width: 48px;
                    height: 48px;
                    border: 5px solid #ddd;
                    border-bottom-color: #999;
                    border-radius: 50%;
                    display: block;
                    box-sizing: border-box;
                    position: absolute;
                    left: 45%;
                    top: 45%;
                    animation: rotation 1s linear infinite;
                }
                @keyframes rotation {
                    0% {
                        transform: rotate(0deg);
                    }
                    100% {
                        transform: rotate(360deg);
                    }
                }
                .bingopay-iframe-loader {
                    width: 100%;
                    height: 100%;
                }
                .bingopay-error-message {
                    color: red;
                }
            </style>
            <div class="modal fade" id="bingoPayModal" tabindex="-1" aria-labelledby="bingoPayModalLabel"
                 aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="bingopay-iframe-loader"><span class="bingopay-loader"></span></div>
                            <iframe id="bingopay-3ds-window" width="100%" height="600vh" src="" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}
	} );

}, 11 );

