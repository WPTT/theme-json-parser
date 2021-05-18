<?php // phpcs:ignore WordPress.Files.FileName

namespace WPTT;

/**
 * Compatibility lay6er for global styles.
 */
class CompatibilityJSONParser {

	/**
	 * Include files to init the global-styles compatibility layer.
	 *
	 * @access public
	 */
	public function __construct() {
        add_action( 'after_setup_theme', [ $this, 'include_files' ] );
	}

	/**
	 * Require files.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function include_files() {
		require_once __DIR__ . '/functions.php';

		if ( ! function_exists( 'gutenberg_experimental_set' ) ) {
			require_once __DIR__ . '/lib/utils.php';
		}

		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			require_once __DIR__ . '/lib/class-wp-theme-json-resolver.php';
		}

		if ( ! interface_exists( 'WP_Theme_JSON_Schema' ) ) {
			require_once __DIR__ . '/lib/interface-wp-theme-json-schema.php';
		}

		if ( ! class_exists( 'WP_Theme_JSON_Schema_V0' ) ) {
			require_once __DIR__ . '/lib/class-wp-theme-json-schema-v0.php';
		}

		if ( ! class_exists( 'WP_Theme_JSON' ) ) {
			require_once __DIR__ . '/lib/class-wp-theme-json.php';
		}

		if ( ! function_exists( 'gutenberg_experimental_global_styles_get_stylesheet' ) ) {
			require_once __DIR__ . '/lib/global-styles.php';
		}
	}
}
