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

namespace WCBingopay;


defined( 'ABSPATH' ) or exit;

define( 'BINGOPAY_VERSION', '1.0.0' );
define( 'BINGOPAY_SUPPORT_PHP', '7.3' );
define( 'BINGOPAY_SUPPORT_WP', '5.0' );
define( 'BINGOPAY_SUPPORT_WC', '3.0' );
define( 'BINGOPAY_DB_VERSION', '1.0' );

const BINGOPAY_DEBUG =  false;

define( 'BINGOPAY_PLUGIN_NAME', plugin_basename( __FILE__ ) );

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

add_action( 'plugins_loaded', function() {
	new Gateway();
}, 11 );

add_filter( 'wp_ajax_bignopay_3ds_form', function() {
	$res =  (new Gateway)->check_3ds();
	echo json_encode( [
		'data' => $res,
	] );
	wp_die();
});

add_filter( 'wp_ajax_nopriv_bignopay_3ds_form', function() {
	$res =  (new Gateway)->check_3ds();
	echo json_encode( [
		'data' => $res,
	] );
	wp_die();
});

add_action( 'woocommerce_checkout_before_order_review', function() {
	$cart_total = WC()->cart->get_cart_contents_total();
    echo <<<EOB1
        <script>
            var currentOrderId = '';
            var currentOrderTotal = {$cart_total};
        </script>
EOB1;
});

add_action( 'woocommerce_pay_order_before_submit', function() {
    $order    = wc_get_order( wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) ) );
    $amount   = $order->get_total();
    echo <<<EOB2
        <script>
            var currentOrderId = {$order->get_id()};
            var currentOrderTotal = {$amount};
        </script>
EOB2;
});

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script(
		'bootstrap',
		plugins_url( 'assets/bootstrap.min.js', BINGOPAY_PLUGIN_NAME ),
		[ 'jquery' ],
		BINGOPAY_VERSION
	);
	wp_enqueue_script(
		'bingopay-script',
		plugins_url( 'assets/script.js', BINGOPAY_PLUGIN_NAME ),
		[ 'jquery' ],
		BINGOPAY_VERSION
	);
	wp_enqueue_style(
		'bootstrap',
		plugins_url( 'assets/bootstrap.min.css', BINGOPAY_PLUGIN_NAME ),
		[],
		BINGOPAY_VERSION
	);
	wp_localize_script( 'jquery', 'ajax', [
		'url'   => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'bingopay_ajax_nonce' ),
	] );
});

add_action( 'wp_footer', function() {
	if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) ) {
?>
    <script>
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        const window_param = urlParams.get('window')
        if (window_param == 'parent') {
            url = window.location.href.replace('&window=parent', '');
            window.parent.location = url;
        }
    </script>
<?php
    }
?>
    <style>
        .loader {
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
        .iframe-loader {
            width: 100%;
            height: 100%;
        }
    </style>
	<div class="modal fade" id="bingoPayModal" tabindex="-1" aria-labelledby="bingoPayModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">
                    <div class="iframe-loader"><span class="loader"></span></div>
					<iframe id="bingopay-3ds-window" width="100%" height="200vh" src="" frameborder="0"></iframe>
				</div>
			</div>
		</div>
	</div>
<?php
});

define( 'BINGOPAY_CALLBACK_URL', add_query_arg( 'wc-api', 'WC_Gateway_BingoPay', home_url( '/' ) ) );
