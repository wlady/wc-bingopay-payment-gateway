<?php

namespace WCBingopay;

class Checker {

	/**
	 * Check if environment meets requirements
	 *
	 * @access public
	 * @return bool
	 */
	public static function check_environment() {
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
		if ( ! self::wp_version_gte( BINGOPAY_SUPPORT_WP ) ) {
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
		} elseif ( ! self::wc_version_gte( BINGOPAY_SUPPORT_WC ) ) {
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