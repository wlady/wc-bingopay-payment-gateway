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

const BINGOPAY_DEBUG =  true;

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
    echo <<<EOB
        <script>
            var currentOrderTotal = {$cart_total};
        </script>
EOB;
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
?>
	<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">
					<iframe id="bingopay-3ds-window" width="100%" height="200vh" src="" frameborder="0"></iframe>
				</div>
			</div>
		</div>
	</div>
<?php
});

define( 'BINGOPAY_CALLBACK_URL', add_query_arg( 'wc-api', 'WC_Gateway_BingoPay', home_url( '/' ) ) );
