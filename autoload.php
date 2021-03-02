<?php
/**
 * Autoload package classes.
 *
 * @package MCT_Lead_Form
 */

spl_autoload_register(
	static function ( $class_name ) {
		if ( false === strpos( $class_name, 'MCT_Lead_Form' ) ) {
			return;
		}

		$file_parts = explode( '\\', $class_name );

		array_shift( $file_parts );

		$file_path  = $file_parts[0] . '/';
		$file_path .= 'Classes' === $file_parts[0] ? 'class-' : '';
		$file_path .= $file_parts[1] . '.php';
		$file_path  = strtolower( $file_path );
		$file_path  = dirname( __FILE__ ) . '/' . $file_path;

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html( 'Failed to autoload class ' . $class_name . '. File not found.' ) );
		}

		require $file_path;
	}
);
