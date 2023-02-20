<?php

namespace WCBingopay;

class DBHelper {

	/**
	 * 	See https://codex.wordpress.org/Creating_Tables_with_Plugins
	 */
	public static function install_db() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$installed_ver = get_option( "BINGOPAY_DB_VERSION" );
		if ( $installed_ver != BINGOPAY_DB_VERSION ) {
			$sql = "CREATE TABLE " . $wpdb->prefix . "bingopay_transactions (
                     id int(11) NOT NULL AUTO_INCREMENT,
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

	public static function save_transaction( string $transaction_id, $details ) {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'bingopay_transactions', [
			'transaction_id' => $transaction_id,
			'data'           => Logger::dumper( $details ),
			'date_time'      => date( 'Y-m-d H:i:s' ),
		] );
	}

	public static function transaction_details_by_transaction_id( string $transaction_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bingopay_transactions WHERE transaction_id=%s",
				$transaction_id ),
			ARRAY_A
		);
	}
}