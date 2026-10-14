<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu and the answers screen: a list and a simple edit form.
 */
final class Admin {

	public const CAPABILITY = 'manage_options';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_dymek_save_node', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_dymek_delete_node', array( __CLASS__, 'delete' ) );
	}

	public static function menu(): void {
		add_menu_page( 'Dymek', 'Dymek', self::CAPABILITY, 'dymek', array( __CLASS__, 'render' ), 'dashicons-format-chat', 58 );
		add_submenu_page( 'dymek', __( 'Answers', 'dymek' ), __( 'Answers', 'dymek' ), self::CAPABILITY, 'dymek', array( __CLASS__, 'render' ) );
		add_submenu_page( 'dymek', __( 'Import and export', 'dymek' ), __( 'Import / export', 'dymek' ), self::CAPABILITY, 'dymek-import', array( Import::class, 'render' ) );
		add_submenu_page( 'dymek', __( 'Settings', 'dymek' ), __( 'Settings', 'dymek' ), self::CAPABILITY, 'dymek-settings', array( Settings::class, 'render' ) );
	}

	public static function render(): void {
		$flash  = self::take_flash();
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		$id     = isset( $_GET['node'] ) ? absint( $_GET['node'] ) : 0;

		echo '<div class="wrap">';
		if ( 'edit' === $action ) {
			self::render_form( $id, $flash );
		} else {
			self::render_list( $flash );
		}
		echo '</div>';
	}

	public static function save(): void {
		self::authorize( 'dymek_save_node' );

		$id  = isset( $_POST['node'] ) ? absint( $_POST['node'] ) : 0;
		$raw = array(
			'label'    => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'type'     => sanitize_key( $_POST['type'] ?? '' ),
			'content'  => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),
			'url'      => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'parent'   => sanitize_key( $_POST['parent'] ?? '' ),
			'slug'     => sanitize_key( $_POST['slug'] ?? '' ),
			'keywords' => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
			'sort'     => (int) ( $_POST['sort'] ?? 0 ),
		);

		$result = Nodes::save( $raw, $id );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message(), $raw );
			self::redirect(
				array(
					'action' => 'edit',
					'node'   => $id,
				)
			);
		}

		self::flash( 'success', __( 'Answer saved.', 'dymek' ) );
		self::redirect();
	}

	public static function delete(): void {
		self::authorize( 'dymek_delete_node' );

		$result = Nodes::delete( isset( $_GET['node'] ) ? absint( $_GET['node'] ) : 0 );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} else {
			self::flash( 'success', __( 'Answer deleted.', 'dymek' ) );
		}
		self::redirect();
	}

	public static function authorize( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'dymek' ), 403 );
		}
		check_admin_referer( $action );
	}

	public static function flash( string $type, string $message, array $data = array() ): void {
		set_transient(
			'dymek_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'data'    => $data,
			),
			MINUTE_IN_SECONDS
		);
	}

	public static function take_flash(): ?array {
		$key   = 'dymek_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		delete_transient( $key );
		return is_array( $flash ) ? $flash : null;
	}

	public static function notice( ?array $flash ): void {
		if ( $flash ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $flash['type'] ), esc_html( $flash['message'] ) );
		}
	}

	private static function render_list( ?array $flash ): void {
		$nodes = Nodes::ordered( Nodes::all() );
		$types = Nodes::types();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Answers', 'dymek' ); ?></h1>
		<a href="<?php echo esc_url( self::url( array( 'action' => 'edit' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add answer', 'dymek' ); ?></a>
		<hr class="wp-header-end">
		<?php self::notice( $flash ); ?>

		<?php if ( ! $nodes ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: URL of the add answer screen, 2: URL of the import screen */
					wp_kses( __( 'No answers yet. <a href="%1$s">Add the start answer</a> or <a href="%2$s">import a CSV file</a>.', 'dymek' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( self::url( array( 'action' => 'edit' ) ) ),
					esc_url( admin_url( 'admin.php?page=dymek-import' ) )
				);
				?>
			</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="column-primary"><?php esc_html_e( 'Answer', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'dymek' ); ?></th>
						<th scope="col"><?php esc_html_e( 'ID', 'dymek' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $nodes as $node ) : ?>
						<?php
						$edit_url   = self::url(
							array(
								'action' => 'edit',
								'node'   => $node['id'],
							)
						);
						$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=dymek_delete_node&node=' . $node['id'] ), 'dymek_delete_node' );
						?>
						<tr>
							<td class="column-primary">
								<strong><a class="row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( str_repeat( '— ', $node['depth'] ) . $node['label'] ); ?></a></strong>
								<div class="row-actions">
									<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'dymek' ); ?></a> | </span>
									<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" onclick="return confirm( '<?php echo esc_js( __( 'Delete this answer?', 'dymek' ) ); ?>' );"><?php esc_html_e( 'Delete', 'dymek' ); ?></a></span>
								</div>
							</td>
							<td><?php echo esc_html( $types[ $node['type'] ] ?? $node['type'] ); ?></td>
							<td><code><?php echo esc_html( $node['slug'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private static function render_form( int $id, ?array $flash ): void {
		$set     = Nodes::all();
		$current = $id ? (string) array_search( $id, array_column( $set, 'id', 'slug' ), true ) : '';

		if ( $id && '' === $current ) {
			self::notice(
				array(
					'type'    => 'error',
					'message' => __( 'This answer no longer exists.', 'dymek' ),
				)
			);
			return;
		}

		$start   = (string) array_search( '', array_column( $set, 'parent', 'slug' ), true );
		$node    = ! empty( $flash['data'] ) ? $flash['data'] : ( $set[ $current ] ?? array( 'parent' => $start ) );
		$node    = wp_parse_args(
			$node,
			array(
				'slug'     => '',
				'parent'   => '',
				'type'     => 'message',
				'label'    => '',
				'content'  => '',
				'keywords' => '',
				'url'      => '',
				'sort'     => 0,
			)
		);
		$parents = array_filter(
			Nodes::ordered( $set ),
			static function ( array $other ) use ( $set, $current ): bool {
				return '' === $current || ! self::descends_from( $set, $other['slug'], $current );
			}
		);
		?>
		<h1><?php echo esc_html( $id ? __( 'Edit answer', 'dymek' ) : __( 'Add answer', 'dymek' ) ); ?></h1>
		<?php self::notice( $flash ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dymek_save_node">
			<input type="hidden" name="node" value="<?php echo esc_attr( (string) $id ); ?>">
			<?php wp_nonce_field( 'dymek_save_node' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dymek-label"><?php esc_html_e( 'Button label', 'dymek' ); ?></label></th>
					<td>
						<input type="text" id="dymek-label" name="label" value="<?php echo esc_attr( $node['label'] ); ?>" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'The text of the button that leads to this answer.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-type"><?php esc_html_e( 'Type', 'dymek' ); ?></label></th>
					<td>
						<select id="dymek-type" name="type">
							<?php foreach ( Nodes::types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $node['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-content"><?php esc_html_e( 'Answer', 'dymek' ); ?></label></th>
					<td>
						<textarea id="dymek-content" name="content" rows="5" class="large-text"><?php echo esc_textarea( $node['content'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Basic HTML such as links and bold text is allowed.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-url"><?php esc_html_e( 'Link address', 'dymek' ); ?></label></th>
					<td>
						<input type="url" id="dymek-url" name="url" value="<?php echo esc_attr( $node['url'] ); ?>" class="regular-text" placeholder="https://">
						<p class="description"><?php esc_html_e( 'Only for the Link type. Opens in a new tab.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-parent"><?php esc_html_e( 'Shown after', 'dymek' ); ?></label></th>
					<td>
						<select id="dymek-parent" name="parent">
							<option value=""><?php esc_html_e( '(start answer)', 'dymek' ); ?></option>
							<?php foreach ( $parents as $parent ) : ?>
								<option value="<?php echo esc_attr( $parent['slug'] ); ?>" <?php selected( $node['parent'], $parent['slug'] ); ?>><?php echo esc_html( str_repeat( '— ', $parent['depth'] ) . $parent['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'The answer under which this button appears. The chat opens with the start answer.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-keywords"><?php esc_html_e( 'Keywords', 'dymek' ); ?></label></th>
					<td>
						<input type="text" id="dymek-keywords" name="keywords" value="<?php echo esc_attr( $node['keywords'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Words customers may use for this topic, separated with commas.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-slug"><?php esc_html_e( 'ID', 'dymek' ); ?></label></th>
					<td>
						<input type="text" id="dymek-slug" name="slug" value="<?php echo esc_attr( $node['slug'] ); ?>" class="regular-text code">
						<p class="description"><?php esc_html_e( 'Used in CSV files. Leave empty to create it from the label.', 'dymek' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dymek-sort"><?php esc_html_e( 'Order', 'dymek' ); ?></label></th>
					<td>
						<input type="number" id="dymek-sort" name="sort" value="<?php echo esc_attr( (string) $node['sort'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Buttons with lower numbers come first.', 'dymek' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( $id ? __( 'Update answer', 'dymek' ) : __( 'Add answer', 'dymek' ) ); ?>
		</form>
		<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'Back to answers', 'dymek' ); ?></a></p>
		<?php
	}

	private static function descends_from( array $set, string $slug, string $ancestor ): bool {
		while ( '' !== $slug && isset( $set[ $slug ] ) ) {
			if ( $ancestor === $slug ) {
				return true;
			}
			$slug = $set[ $slug ]['parent'];
		}
		return false;
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array( 'page' => 'dymek' ) + array_filter( $args ), admin_url( 'admin.php' ) );
	}

	private static function redirect( array $args = array() ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}
}
