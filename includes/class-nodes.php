<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Answer nodes: storage, validation and the tree served to the widget.
 *
 * Outside the database a set of nodes is an array keyed by slug, where
 * "parent" holds the parent's slug. The editor and the CSV import both build
 * such a set and run it through validate_set(), so they follow the same rules.
 */
final class Nodes {

	private const CACHE_KEY = 'dymek_tree';
	private const FORMATS   = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d' );

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'dymek_nodes';
	}

	public static function types(): array {
		return array(
			'message' => __( 'Message', 'dymek' ),
			'link'    => __( 'Link', 'dymek' ),
		);
	}

	public static function sanitize( array $raw ): array {
		return array(
			'slug'     => sanitize_key( $raw['slug'] ?? '' ),
			'parent'   => sanitize_key( $raw['parent'] ?? '' ),
			'type'     => sanitize_key( $raw['type'] ?? '' ),
			'label'    => sanitize_text_field( $raw['label'] ?? '' ),
			'content'  => wp_kses_post( $raw['content'] ?? '' ),
			'keywords' => sanitize_text_field( $raw['keywords'] ?? '' ),
			'url'      => esc_url_raw( $raw['url'] ?? '', array( 'http', 'https' ) ),
			'sort'     => (int) ( $raw['sort'] ?? 0 ),
		);
	}

	public static function all(): array {
		global $wpdb;

		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort, id', self::table() ), ARRAY_A );
		$slugs = wp_list_pluck( $rows, 'slug', 'id' );
		$set   = array();

		foreach ( $rows as $row ) {
			$set[ $row['slug'] ] = array(
				'id'       => (int) $row['id'],
				'slug'     => $row['slug'],
				'parent'   => $slugs[ $row['parent_id'] ] ?? '',
				'type'     => $row['type'],
				'label'    => $row['label'],
				'content'  => $row['content'],
				'keywords' => $row['keywords'],
				'url'      => $row['url'],
				'sort'     => (int) $row['sort'],
			);
		}

		return $set;
	}

	public static function find( int $id ): ?array {
		foreach ( self::all() as $node ) {
			if ( $id === $node['id'] ) {
				return $node;
			}
		}
		return null;
	}

	/**
	 * Depth-first order with a "depth" key, as shown in the admin and exported.
	 */
	public static function ordered( array $set ): array {
		$children = array();
		foreach ( $set as $slug => $node ) {
			$children[ $node['parent'] ][] = $slug;
		}

		$list = array();
		$walk = static function ( string $parent, int $depth ) use ( &$walk, &$list, $children, $set ) {
			foreach ( $children[ $parent ] ?? array() as $slug ) {
				$list[] = $set[ $slug ] + array( 'depth' => $depth );
				$walk( $slug, $depth + 1 );
			}
		};
		$walk( '', 0 );

		return $list;
	}

	/**
	 * @return array<string, string[]> Error messages keyed by slug; general errors under ''.
	 */
	public static function validate_set( array $set ): array {
		$errors = array();
		$roots  = array();

		foreach ( $set as $slug => $node ) {
			$messages = array();

			if ( '' === $node['label'] ) {
				$messages[] = __( 'The label is missing.', 'dymek' );
			}

			if ( ! isset( self::types()[ $node['type'] ] ) ) {
				/* translators: %s: node type from the form or the CSV file */
				$messages[] = sprintf( __( 'Unknown type "%s". Use message or link.', 'dymek' ), $node['type'] );
			} elseif ( 'link' === $node['type'] && '' === $node['url'] ) {
				$messages[] = __( 'A link needs an http or https address.', 'dymek' );
			}

			if ( '' === $node['parent'] ) {
				$roots[] = $slug;
			} elseif ( ! isset( $set[ $node['parent'] ] ) ) {
				/* translators: %s: parent ID */
				$messages[] = sprintf( __( 'The parent "%s" does not exist.', 'dymek' ), $node['parent'] );
			} elseif ( self::in_cycle( $set, $slug ) ) {
				$messages[] = __( 'The parents form a loop.', 'dymek' );
			}

			if ( $messages ) {
				$errors[ $slug ] = $messages;
			}
		}

		if ( $set && ! $roots ) {
			$errors[''][] = __( 'One answer must be the start answer (with no parent).', 'dymek' );
		}

		foreach ( array_slice( $roots, 1 ) as $slug ) {
			$errors[ $slug ][] = __( 'There is already a start answer. Choose a parent.', 'dymek' );
		}

		return $errors;
	}

	/**
	 * @return int|\WP_Error The node ID.
	 */
	public static function save( array $raw, int $id = 0 ) {
		global $wpdb;

		$node = self::sanitize( $raw );
		if ( '' === $node['slug'] ) {
			$node['slug'] = sanitize_key( sanitize_title( $node['label'] ) );
		}
		if ( '' === $node['slug'] ) {
			return new \WP_Error( 'dymek_invalid', __( 'The label is missing.', 'dymek' ) );
		}

		$set      = self::all();
		$old_slug = '';

		if ( $id ) {
			$old_slug = (string) array_search( $id, array_column( $set, 'id', 'slug' ), true );
			if ( '' === $old_slug ) {
				return new \WP_Error( 'dymek_not_found', __( 'This answer no longer exists.', 'dymek' ) );
			}
			unset( $set[ $old_slug ] );
		}

		if ( isset( $set[ $node['slug'] ] ) ) {
			/* translators: %s: node ID */
			return new \WP_Error( 'dymek_invalid', sprintf( __( 'The ID "%s" is already used by another answer.', 'dymek' ), $node['slug'] ) );
		}

		foreach ( $set as $slug => $other ) {
			if ( '' !== $old_slug && $old_slug === $other['parent'] ) {
				$set[ $slug ]['parent'] = $node['slug'];
			}
		}
		$set[ $node['slug'] ] = $node;

		$errors = self::validate_set( $set );
		if ( $errors ) {
			$messages = $errors[ $node['slug'] ] ?? reset( $errors );
			return new \WP_Error( 'dymek_invalid', implode( ' ', $messages ) );
		}

		$row = self::row( $node, '' === $node['parent'] ? 0 : $set[ $node['parent'] ]['id'] );
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ), self::FORMATS, array( '%d' ) );
		} else {
			$wpdb->insert( self::table(), $row, self::FORMATS );
			$id = (int) $wpdb->insert_id;
		}

		self::flush();
		return $id;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function delete( int $id ) {
		global $wpdb;

		$children = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE parent_id = %d', self::table(), $id ) );
		if ( $children ) {
			return new \WP_Error( 'dymek_has_children', __( 'Delete or move the answers under it first.', 'dymek' ) );
		}

		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		self::flush();
		return true;
	}

	/**
	 * Writes a validated set. Parents are inserted before their children.
	 *
	 * @param string $mode 'replace' removes all answers first, 'append' keeps them.
	 * @return int Number of imported answers.
	 */
	public static function import( array $set, string $mode ): int {
		global $wpdb;

		if ( 'replace' === $mode ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::table() ) );
			$ids = array();
		} else {
			$ids = array_column( self::all(), 'id', 'slug' );
		}

		$count = 0;
		while ( $set ) {
			$pending = count( $set );

			foreach ( $set as $slug => $node ) {
				if ( '' !== $node['parent'] && ! isset( $ids[ $node['parent'] ] ) ) {
					continue;
				}
				$wpdb->insert( self::table(), self::row( $node, $ids[ $node['parent'] ] ?? 0 ), self::FORMATS );
				$ids[ $slug ] = (int) $wpdb->insert_id;
				unset( $set[ $slug ] );
				++$count;
			}

			if ( count( $set ) === $pending ) {
				break;
			}
		}

		self::flush();
		return $count;
	}

	/**
	 * The tree as the widget needs it, cached until the next change.
	 *
	 * @return array{start:string,nodes:array}
	 */
	public static function tree(): array {
		$tree = get_transient( self::CACHE_KEY );
		if ( is_array( $tree ) ) {
			return $tree;
		}

		$tree = array(
			'start' => '',
			'nodes' => array(),
		);

		$set = self::all();
		foreach ( $set as $slug => $node ) {
			$tree['nodes'][ $slug ] = array(
				'type'     => $node['type'],
				'label'    => $node['label'],
				'content'  => wpautop( wp_kses_post( $node['content'] ) ),
				'url'      => $node['url'],
				'children' => array(),
			);
		}
		foreach ( $set as $slug => $node ) {
			if ( '' === $node['parent'] ) {
				$tree['start'] = $slug;
			} else {
				$tree['nodes'][ $node['parent'] ]['children'][] = $slug;
			}
		}

		set_transient( self::CACHE_KEY, $tree );
		return $tree;
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	private static function row( array $node, int $parent_id ): array {
		return array(
			'slug'      => $node['slug'],
			'parent_id' => $parent_id,
			'type'      => $node['type'],
			'label'     => $node['label'],
			'content'   => $node['content'],
			'keywords'  => $node['keywords'],
			'url'       => $node['url'],
			'sort'      => $node['sort'],
		);
	}

	private static function in_cycle( array $set, string $slug ): bool {
		$seen = array();
		while ( '' !== $slug && isset( $set[ $slug ] ) ) {
			if ( isset( $seen[ $slug ] ) ) {
				return true;
			}
			$seen[ $slug ] = true;
			$slug          = $set[ $slug ]['parent'];
		}
		return false;
	}
}
