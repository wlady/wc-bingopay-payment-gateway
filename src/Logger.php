<?php

namespace WCBingopay;


class Logger {

	public static function error( $error, $data = null ) {
		wc_get_logger()->critical(
			sprintf( 'Transaction Error: %s\n%s',
				$error,
				self::dumper( $data )
			),
			[
				'source' => 'bingopay-errors',
			]
		);
	}

	public static function info( $data = null ) {
		wc_get_logger()->info(
			self::dumper( $data ),
			[
				'source' => 'bingopay-info',
			]
		);
	}

	public static function dumper( $data ) {
		ob_start();
		print_r( $data );
		$v = ob_get_contents();
		ob_end_clean();

		return $v;
	}
}