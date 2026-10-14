<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end chat button and window.
 */
final class Widget {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue(): void {
		if ( '' === Nodes::tree()['start'] ) {
			return;
		}

		$accent = Settings::get( 'color' );
		$title  = Settings::get( 'title' );

		wp_enqueue_style( 'dymek', DYMEK_URL . 'assets/widget.css', array(), DYMEK_VERSION );
		wp_add_inline_style( 'dymek', sprintf( '.dymek{--dymek-accent:%s;--dymek-accent-text:%s}', $accent, self::text_color( $accent ) ) );

		wp_enqueue_script(
			'dymek',
			DYMEK_URL . 'assets/widget.js',
			array(),
			DYMEK_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'dymek',
			'dymekConfig',
			array(
				'endpoint' => esc_url_raw( rest_url( 'dymek/v1/tree' ) ),
				'position' => Settings::get( 'position' ),
				'title'    => '' === $title ? __( 'How can we help?', 'dymek' ) : $title,
				'i18n'     => array(
					'open'     => __( 'Open chat', 'dymek' ),
					'close'    => __( 'Close chat', 'dymek' ),
					'restart'  => __( 'Back to start', 'dymek' ),
					'openLink' => __( 'Open page', 'dymek' ),
					'error'    => __( 'The chat could not load. Please try again later.', 'dymek' ),
				),
			)
		);
	}

	/**
	 * Picks the text colour with better contrast against the accent (WCAG relative luminance).
	 */
	private static function text_color( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$channels = array_map(
			static function ( string $pair ): float {
				$value = hexdec( $pair ) / 255;
				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( $hex, 2 )
		);

		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

		return ( $luminance + 0.05 ) / 0.05 > 1.05 / ( $luminance + 0.05 ) ? '#1e1e1e' : '#ffffff';
	}
}
