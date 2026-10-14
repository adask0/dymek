<?php
namespace Dymek;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes answers as CSV.
 *
 * Accepts UTF-8 with or without BOM and detects ";" (Polish Excel) or ",".
 * Row numbers count CSV records, so they match the rows shown by Excel.
 */
final class Csv {

	public const COLUMNS = array( 'id', 'parent_id', 'type', 'label', 'content', 'keywords', 'url' );

	private const REQUIRED = array( 'id', 'type', 'label' );
	private const MAX_ROWS = 2000;

	/**
	 * @return array{nodes:array, rows:array<string,int>, errors:array<int,string[]>}
	 *               nodes keyed by slug, rows maps a slug to its row number.
	 */
	public static function parse( string $path ): array {
		$result = array(
			'nodes'  => array(),
			'rows'   => array(),
			'errors' => array(),
		);

		$file = new \SplFileObject( $path );
		$head = (string) $file->fgets();
		$file->rewind();
		$file->setFlags( \SplFileObject::READ_CSV );
		$file->setCsvControl( substr_count( $head, ';' ) >= substr_count( $head, ',' ) ? ';' : ',', '"', '' );

		$columns = null;
		$row     = 0;

		foreach ( $file as $cells ) {
			++$row;
			if ( ! is_array( $cells ) || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}

			if ( null === $columns ) {
				$cells[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $cells[0] );
				$columns  = array_flip( array_map( 'strtolower', array_map( 'trim', array_map( 'strval', $cells ) ) ) );

				foreach ( self::REQUIRED as $required ) {
					if ( ! isset( $columns[ $required ] ) ) {
						/* translators: %s: CSV column name */
						$result['errors'][ $row ][] = sprintf( __( 'The column "%s" is missing.', 'dymek' ), $required );
					}
				}
				if ( $result['errors'] ) {
					return $result;
				}
				continue;
			}

			if ( ! preg_match( '//u', implode( '', array_map( 'strval', $cells ) ) ) ) {
				$result['errors'][ $row ][] = __( 'This row is not valid UTF-8. Save the file as "CSV UTF-8".', 'dymek' );
				continue;
			}

			$value = static function ( string $column ) use ( $columns, $cells ): string {
				return isset( $columns[ $column ] ) ? trim( (string) ( $cells[ $columns[ $column ] ] ?? '' ) ) : '';
			};

			$node = Nodes::sanitize(
				array(
					'slug'     => $value( 'id' ),
					'parent'   => $value( 'parent_id' ),
					'type'     => $value( 'type' ),
					'label'    => $value( 'label' ),
					'content'  => $value( 'content' ),
					'keywords' => $value( 'keywords' ),
					'url'      => $value( 'url' ),
					'sort'     => $row,
				)
			);

			if ( '' === $node['slug'] ) {
				$result['errors'][ $row ][] = __( 'The ID is missing.', 'dymek' );
				continue;
			}
			if ( isset( $result['nodes'][ $node['slug'] ] ) ) {
				/* translators: %s: node ID */
				$result['errors'][ $row ][] = sprintf( __( 'The ID "%s" is used more than once.', 'dymek' ), $node['slug'] );
				continue;
			}
			if ( count( $result['nodes'] ) >= self::MAX_ROWS ) {
				/* translators: %d: maximum number of rows */
				$result['errors'][ $row ][] = sprintf( __( 'The file has more than %d answers.', 'dymek' ), self::MAX_ROWS );
				break;
			}

			$result['nodes'][ $node['slug'] ] = $node;
			$result['rows'][ $node['slug'] ]  = $row;
		}

		if ( null === $columns ) {
			$result['errors'][0][] = __( 'The file is empty.', 'dymek' );
		}

		return $result;
	}

	/**
	 * Sends the answers to the output with a BOM and ";", so Excel opens them correctly.
	 */
	public static function export( array $nodes ): void {
		$output = new \SplFileObject( 'php://output', 'w' );
		$output->fwrite( "\xEF\xBB\xBF" );
		$output->fputcsv( self::COLUMNS, ';', '"', '' );

		foreach ( $nodes as $node ) {
			$output->fputcsv(
				array( $node['slug'], $node['parent'], $node['type'], $node['label'], $node['content'], $node['keywords'], $node['url'] ),
				';',
				'"',
				''
			);
		}
	}
}
