<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Dymek > Settings.
 */
final class Settings {

	public const OPTION = 'dymek_settings';

	private const PAGE = 'dymek-settings';

	private const DEFAULTS = array(
		'title'     => '',
		'color'     => '#2271b1',
		'position'  => 'right',
		'keep_data' => 'no',
	);

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function get( string $key ): string {
		$values = (array) get_option( self::OPTION, array() );
		return (string) ( $values[ $key ] ?? self::DEFAULTS[ $key ] ?? '' );
	}

	public static function register(): void {
		register_setting(
			'dymek',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::DEFAULTS,
			)
		);

		add_settings_section( 'dymek_appearance', __( 'Appearance', 'dymek' ), '__return_false', self::PAGE );
		add_settings_field( 'title', __( 'Chat title', 'dymek' ), array( __CLASS__, 'field_title' ), self::PAGE, 'dymek_appearance', array( 'label_for' => 'dymek-title' ) );
		add_settings_field( 'color', __( 'Colour', 'dymek' ), array( __CLASS__, 'field_color' ), self::PAGE, 'dymek_appearance', array( 'label_for' => 'dymek-color' ) );
		add_settings_field( 'position', __( 'Position', 'dymek' ), array( __CLASS__, 'field_position' ), self::PAGE, 'dymek_appearance', array( 'label_for' => 'dymek-position' ) );

		add_settings_section( 'dymek_data', __( 'Data', 'dymek' ), '__return_false', self::PAGE );
		add_settings_field( 'keep_data', __( 'Uninstall', 'dymek' ), array( __CLASS__, 'field_keep_data' ), self::PAGE, 'dymek_data' );
	}

	public static function sanitize( $input ): array {
		$input = (array) $input;
		$color = sanitize_hex_color( $input['color'] ?? '' );

		return array(
			'title'     => sanitize_text_field( $input['title'] ?? '' ),
			'color'     => $color ? $color : self::DEFAULTS['color'],
			'position'  => 'left' === ( $input['position'] ?? '' ) ? 'left' : 'right',
			'keep_data' => empty( $input['keep_data'] ) ? 'no' : 'yes',
		);
	}

	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'dymek' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'dymek' );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function field_title(): void {
		printf(
			'<input type="text" id="dymek-title" name="%s[title]" value="%s" class="regular-text" placeholder="%s">',
			esc_attr( self::OPTION ),
			esc_attr( self::get( 'title' ) ),
			esc_attr__( 'How can we help?', 'dymek' )
		);
	}

	public static function field_color(): void {
		printf(
			'<input type="color" id="dymek-color" name="%s[color]" value="%s"><p class="description">%s</p>',
			esc_attr( self::OPTION ),
			esc_attr( self::get( 'color' ) ),
			esc_html__( 'The text colour on buttons is picked automatically for good contrast.', 'dymek' )
		);
	}

	public static function field_position(): void {
		$current = self::get( 'position' );
		printf( '<select id="dymek-position" name="%s[position]">', esc_attr( self::OPTION ) );
		foreach ( array(
			'right' => __( 'Bottom right', 'dymek' ),
			'left'  => __( 'Bottom left', 'dymek' ),
		) as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function field_keep_data(): void {
		printf(
			'<label><input type="checkbox" name="%s[keep_data]" value="1" %s> %s</label>',
			esc_attr( self::OPTION ),
			checked( self::get( 'keep_data' ), 'yes', false ),
			esc_html__( 'Keep answers and settings when the plugin is deleted', 'dymek' )
		);
	}
}
