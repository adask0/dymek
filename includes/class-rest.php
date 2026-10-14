<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/dymek/v1/tree, loaded by the widget when the chat is opened.
 */
final class Rest {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'dymek/v1',
			'/tree',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'tree' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function tree(): \WP_REST_Response {
		return new \WP_REST_Response( Nodes::tree(), 200 );
	}
}
