<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Dymek > Import / export. An upload is parsed into a preview first and
 * written only after the user confirms it.
 */
final class Import {

	private const MAX_SIZE    = MB_IN_BYTES;
	private const PREVIEW_TTL = 15 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'admin_post_dymek_import_upload', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_dymek_import_confirm', array( __CLASS__, 'confirm' ) );
		add_action( 'admin_post_dymek_import_cancel', array( __CLASS__, 'cancel' ) );
		add_action( 'admin_post_dymek_export', array( __CLASS__, 'export' ) );
	}

	public static function render(): void {
		$flash   = Admin::take_flash();
		$preview = get_transient( self::preview_key() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import and export', 'dymek' ); ?></h1>
			<?php
			Admin::notice( $flash );
			if ( is_array( $preview ) ) {
				self::render_preview( $preview );
			} else {
				self::render_forms();
			}
			?>
		</div>
		<?php
	}

	public static function upload(): void {
		Admin::authorize( 'dymek_import_upload' );

		if ( ! isset( $_FILES['dymek_csv']['tmp_name'], $_FILES['dymek_csv']['error'], $_FILES['dymek_csv']['size'] )
			|| UPLOAD_ERR_OK !== $_FILES['dymek_csv']['error']
			|| ! is_uploaded_file( $_FILES['dymek_csv']['tmp_name'] ) ) {
			self::fail( __( 'Choose a CSV file to import.', 'dymek' ) );
		}
		if ( $_FILES['dymek_csv']['size'] > self::MAX_SIZE ) {
			/* translators: %s: maximum file size, e.g. "1 MB" */
			self::fail( sprintf( __( 'The file is larger than %s.', 'dymek' ), size_format( self::MAX_SIZE ) ) );
		}

		$preview         = Csv::parse( $_FILES['dymek_csv']['tmp_name'] );
		$preview['mode'] = 'append' === sanitize_key( $_POST['mode'] ?? '' ) ? 'append' : 'replace';

		set_transient( self::preview_key(), $preview, self::PREVIEW_TTL );
		self::redirect();
	}

	public static function confirm(): void {
		Admin::authorize( 'dymek_import_confirm' );

		$preview = get_transient( self::preview_key() );
		delete_transient( self::preview_key() );

		if ( ! is_array( $preview ) ) {
			self::fail( __( 'The preview has expired. Please upload the file again.', 'dymek' ) );
		}
		if ( self::errors( $preview ) ) {
			self::fail( __( 'The file has problems. Fix them and upload it again.', 'dymek' ) );
		}

		$count = Nodes::import( $preview['nodes'], $preview['mode'] );
		/* translators: %d: number of imported answers */
		Admin::flash( 'success', sprintf( _n( 'Imported %d answer.', 'Imported %d answers.', $count, 'dymek' ), $count ) );
		self::redirect();
	}

	public static function cancel(): void {
		Admin::authorize( 'dymek_import_cancel' );
		delete_transient( self::preview_key() );
		self::redirect();
	}

	public static function export(): void {
		Admin::authorize( 'dymek_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=dymek-' . gmdate( 'Y-m-d' ) . '.csv' );
		Csv::export( Nodes::ordered( Nodes::all() ) );
		exit;
	}

	/**
	 * Problems found while reading the file plus those of the resulting set of answers.
	 *
	 * @return array<int, string[]> Messages keyed by row number; 0 for the file as a whole.
	 */
	private static function errors( array $preview ): array {
		$errors = $preview['errors'];
		$set    = $preview['nodes'];

		if ( ! $set && ! $errors ) {
			$errors[0][] = __( 'The file has no answers.', 'dymek' );
		}

		if ( 'append' === $preview['mode'] ) {
			$existing = Nodes::all();
			foreach ( array_keys( array_intersect_key( $set, $existing ) ) as $slug ) {
				/* translators: %s: node ID */
				$errors[ $preview['rows'][ $slug ] ][] = sprintf( __( 'The ID "%s" already exists.', 'dymek' ), $slug );
			}
			$set = $existing + $set;
		}

		foreach ( Nodes::validate_set( $set ) as $slug => $messages ) {
			$row            = $preview['rows'][ $slug ] ?? 0;
			$errors[ $row ] = array_merge( $errors[ $row ] ?? array(), $messages );
		}

		ksort( $errors );
		return $errors;
	}

	private static function render_forms(): void {
		?>
		<h2><?php esc_html_e( 'Export', 'dymek' ); ?></h2>
		<p><?php esc_html_e( 'Download all answers as a CSV file that opens in Excel. You can edit it and import it back.', 'dymek' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dymek_export">
			<?php wp_nonce_field( 'dymek_export' ); ?>
			<?php submit_button( __( 'Download CSV', 'dymek' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Import', 'dymek' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: list of CSV columns */
				esc_html__( 'The first row must contain the column names: %s. Leave parent_id empty for the start answer.', 'dymek' ),
				'<code>' . esc_html( implode( ';', Csv::COLUMNS ) ) . '</code>'
			);
			?>
		</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dymek_import_upload">
			<?php wp_nonce_field( 'dymek_import_upload' ); ?>
			<p><input type="file" name="dymek_csv" accept=".csv,text/csv" required></p>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Import mode', 'dymek' ); ?></legend>
				<label><input type="radio" name="mode" value="replace" checked> <?php esc_html_e( 'Replace all answers', 'dymek' ); ?></label><br>
				<label><input type="radio" name="mode" value="append"> <?php esc_html_e( 'Add to the existing answers', 'dymek' ); ?></label>
			</fieldset>
			<?php submit_button( __( 'Preview import', 'dymek' ) ); ?>
		</form>
		<?php
	}

	private static function render_preview( array $preview ): void {
		$errors = self::errors( $preview );
		$count  = count( $preview['nodes'] );
		$types  = Nodes::types();
		?>
		<h2><?php esc_html_e( 'Preview', 'dymek' ); ?></h2>
		<p>
			<?php
			/* translators: %d: number of answers in the file */
			echo esc_html( sprintf( _n( '%d answer in the file.', '%d answers in the file.', $count, 'dymek' ), $count ) . ' ' );
			echo esc_html( 'append' === $preview['mode'] ? __( 'They will be added to the existing answers.', 'dymek' ) : __( 'They will replace all current answers.', 'dymek' ) );
			?>
		</p>

		<?php if ( $errors ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'Fix these problems in the file and upload it again:', 'dymek' ); ?></p>
				<ul>
					<?php foreach ( $errors as $row => $messages ) : ?>
						<?php foreach ( $messages as $message ) : ?>
							<li>
								<?php
								echo esc_html(
									$row
										/* translators: 1: row number in the CSV file, 2: problem description */
										? sprintf( __( 'Row %1$d: %2$s', 'dymek' ), $row, $message )
										: $message
								);
								?>
							</li>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $count ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" style="width:4em"><?php esc_html_e( 'Row', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'ID', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shown after', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Button label', 'dymek' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $preview['nodes'] as $slug => $node ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $preview['rows'][ $slug ] ); ?></td>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td><?php echo '' === $node['parent'] ? esc_html__( '(start answer)', 'dymek' ) : '<code>' . esc_html( $node['parent'] ) . '</code>'; ?></td>
							<td><?php echo esc_html( $types[ $node['type'] ] ?? $node['type'] ); ?></td>
							<td><?php echo esc_html( $node['label'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dymek_import_confirm">
			<?php wp_nonce_field( 'dymek_import_confirm' ); ?>
			<p class="submit">
				<?php if ( ! $errors ) : ?>
					<?php submit_button( __( 'Import', 'dymek' ), 'primary', 'submit', false ); ?>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dymek_import_cancel' ), 'dymek_import_cancel' ) ); ?>"><?php esc_html_e( 'Cancel', 'dymek' ); ?></a>
			</p>
		</form>
		<?php
	}

	private static function preview_key(): string {
		return 'dymek_import_' . get_current_user_id();
	}

	private static function fail( string $message ): void {
		Admin::flash( 'error', $message );
		self::redirect();
	}

	private static function redirect(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=dymek-import' ) );
		exit;
	}
}
