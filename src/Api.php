<?php

namespace WCBingopay;


class Api {

	const RETURN_CODE_OK = 200;
	const RETURN_CODE_ERROR = 418;
	const RETURN_CODE_FATAL = 500;

	const STATUS_IN_PROCESS = 1;
	const STATUS_APPROVED = 2;
	const STATUS_DENIED = 3;
	const STATUS_WAITING_CONFIRMATION = 5;

	const API_URL = 'https://api1.adataprotect.com';
	const USER_LOGIN_PATH = 'api/user/login';
	const CREATE_PAYMENT_PATH = 'api/transaction/creates/payments';
	const CREATE_REFUND = 'api/transaction/refunds';

	public static function get_token( $settings ) {
		$args = [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => json_encode(
				[
					'login'    => $settings['login'],
					'password' => $settings['password'],
				]
			),
		];
		$url  = sprintf( '%s/%s', self::API_URL, self::USER_LOGIN_PATH );

		return self::make_post_request( $url, $args );
	}

	public static function create_transaction( $settings, $payload ) {
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $settings['token'],
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode(
				[
					'payer_id'     => $settings['payer_id'],
					'owner'        => $payload['card_holder'],
					'card_number'  => $payload['card_number'],
					'cvv'          => $payload['card_cvc'],
					'validity'     => $payload['card_expire'],
					'amount'       => $payload['amount'],
					'currency'     => $payload['currency'],
					'callback_url' => BINGOPAY_CALLBACK_URL,
				]
			),
		];
		$url  = sprintf( '%s/%s?order_id=%s', self::API_URL, self::CREATE_PAYMENT_PATH, $payload['order_id'] );

		return self::make_post_request( $url, $args );
	}

	public static function refund( $settings, $payload ) {
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $settings['token'],
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode(
				[
					'payer_id'       => $settings['payer_id'],
					'transaction_id' => $payload['transaction_id'],
				]
			),
		];
		$url  = sprintf( '%s/%s', self::API_URL, self::CREATE_REFUND );

		return self::make_post_request( $url, $args );
	}

	public static function get_error_message( $status ) {
		switch ( $status ) {
			case self::STATUS_IN_PROCESS:
				return _( 'Transaction in progress, check status after a while', 'wc-bingopay' );
			case self::STATUS_APPROVED:
				return _( 'Transaction completed', 'wc-bingopay' );
			case self::STATUS_DENIED:
				return _( 'The transaction was NOT completed due to reasons beyond our control', 'wc-bingopay' );
			case self::STATUS_WAITING_CONFIRMATION:
				return _( 'Waiting for confirmation from the user ', 'wc-bingopay' );
			default:
				return _( 'Unknown error', 'wc-bingopay' );
		}
	}

	public static function get_error_by_status( $response ) {
		switch ( $response['status'] ) {
			case self::RETURN_CODE_OK:
				return _( 'Everything is OK', 'wc-bingopay' );
			default:
				return is_array( $response['errors'] ?? [] ) ? implode(', ', $response['errors'] ) : $response['errors'];
		}
	}

	private static function make_post_request( $url, $args ) {
		if ( BINGOPAY_DEBUG ) {
			Logger::info( [ $url, $args ] );
		}
		$result = wp_remote_post( $url, $args );
		if ( BINGOPAY_DEBUG ) {
			Logger::info( $result );
		}

		return json_decode( wp_remote_retrieve_body( $result ), true );
	}
}