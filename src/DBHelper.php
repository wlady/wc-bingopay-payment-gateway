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
			$sql = "CREATE TABLE {$wpdb->prefix}bingopay_transactions (
                     id int(11) NOT NULL AUTO_INCREMENT,
                     transaction_id char(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                     data text COLLATE utf8_unicode_ci DEFAULT NULL,
                     date_time datetime NOT NULL,
                     PRIMARY KEY  (id),
                     UNIQUE KEY  transaction_id (transaction_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
			dbDelta( $sql );

			update_option( 'BINGOPAY_DB_VERSION', BINGOPAY_DB_VERSION );
		}
	}

	public static function create_transaction( string $transaction_id, $details ) {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'bingopay_transactions',
			[
				'transaction_id' => $transaction_id,
				'data'           => serialize( $details ),
				'date_time'      => date( 'Y-m-d H:i:s' ),
			]
		);
	}

	public static function update_transaction( string $transaction_id, $details ) {
		global $wpdb;

		$wpdb->update( $wpdb->prefix . 'bingopay_transactions',
			[
				'data'           => serialize( $details ),
				'date_time'      => date( 'Y-m-d H:i:s' ),
			],
			[
				'transaction_id' => $transaction_id,
			]
		);
	}

	public static function transaction_details_by_transaction_id( string $transaction_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bingopay_transactions WHERE transaction_id='%s'",
				$transaction_id ),
			ARRAY_A
		);
		if ( $row ) {
			$row['data'] = unserialize( $row['data'] );
		}

		return $row;
	}
}
