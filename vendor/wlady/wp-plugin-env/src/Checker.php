<?php

namespace Plugin\Env;

class Checker {

	private $plugin;
	
	public function __construct( PluginInterface $plugin ) {
		$this->plugin = $plugin;
	}
	
	/**
	 * Check if environment meets requirements
	 *
	 * @access public
	 * @return bool
	 */
	public function check() {
		$is_ok = true;

		// Check PHP version
		if ( ! version_compare( PHP_VERSION, $this->plugin->supported_php(), '>=' ) ) {
			// Add notice
			\add_action( 'admin_notices', function () {
				echo '<div class="error"><p>'
				     . \esc_html__(
					     sprintf( '%s requires PHP version %s or later.',
						     $this->plugin->plugin_name(),
						     $this->plugin->supported_php() ),
					     $this->plugin->plugin_text_domain() )
				     . '</p></div>';
			} );
			$is_ok = false;
		}

		// Check WordPress version
		if ( ! self::wp_version_gte( $this->plugin->supported_wp() ) ) {
			\add_action( 'admin_notices', function () {
				echo '<div class="error"><p>'
				     . \esc_html__(
					     sprintf( '%s requires WordPress version %s or later. Please update WordPress to use this plugin.',
						     $this->plugin->plugin_name(),
						     $this->plugin->supported_wp() ),
					     $this->plugin->plugin_text_domain() )
				     . '</p></div>';
			} );
			$is_ok = false;
		}

		// Check if WooCommerce is installed and enabled
		if ( ! class_exists( 'WooCommerce' ) ) {
			\add_action( 'admin_notices', function () {
				echo '<div class="error"><p>'
				     . \esc_html__(
					     sprintf( '%s requires WooCommerce to be active.',
						     $this->plugin->plugin_name() ),
					     $this->plugin->plugin_text_domain() )
				     . '</p></div>';
			} );
			$is_ok = false;
		} elseif ( ! self::wc_version_gte( $this->plugin->supported_wc() ) ) {
			\add_action( 'admin_notices', function () {
				echo '<div class="error"><p>'
				     . \esc_html__(
					     sprintf( '%s requires WooCommerce version %s or later.',
						     $this->plugin->plugin_name(),
						     $this->plugin->supported_wc() ),
					     $this->plugin->plugin_text_domain() )
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
	public function wc_version_gte( $version ) {
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
	public function wp_version_gte( $version ) {
		$wp_version = \get_bloginfo( 'version' );

		// Treat release candidate strings
		$wp_version = preg_replace( '/-RC.+/i', '', $wp_version );

		if ( $wp_version ) {
			return version_compare( $wp_version, $version, '>=' );
		}

		return false;
	}
}