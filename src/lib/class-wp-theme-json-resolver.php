<?php
/**
 * Process the different data sources for site-level
 * config and offers and API to work with them.
 *
 * @package gutenberg
 */

/**
 * Class that abstracts the processing
 * of the different data sources.
 */
class WP_Theme_JSON_Resolver {

	/**
	 * Container for data coming from core.
	 *
	 * @var WP_Theme_JSON
	 */
	private static $core = null;

	/**
	 * Container for data coming from the theme.
	 *
	 * @var WP_Theme_JSON
	 */
	private static $theme = null;

	/**
	 * Whether or not the theme supports theme.json.
	 *
	 * @var boolean
	 */
	private static $theme_has_support = null;

	/**
	 * Structure to hold i18n metadata.
	 *
	 * @var Array
	 */
	private static $theme_json_i18n = null;

	/**
	 * Processes a file that adheres to the theme.json
	 * schema and returns an array with its contents,
	 * or a void array if none found.
	 *
	 * @param string $file_path Path to file. Empty if no file.
	 *
	 * @return array Contents that adhere to the theme.json schema.
	 */
	private static function read_json_file( $file_path ) {
		$config = array();
		if ( $file_path ) {
			$decoded_file = json_decode(
				file_get_contents( $file_path ),
				true
			);

			$json_decoding_error = json_last_error();
			if ( JSON_ERROR_NONE !== $json_decoding_error ) {
				error_log( 'Error when decoding file schema: ' . json_last_error_msg() );
				return $config;
			}

			if ( is_array( $decoded_file ) ) {
				$config = $decoded_file;
			}
		}
		return $config;
	}

	/**
	 * Converts a tree as in i18n-theme.json into a linear array
	 * containing metadata to translate a theme.json file.
	 *
	 * For example, given this input:
	 *
	 * {
	 *   "settings": {
	 *     "*": {
	 *       "typography": {
	 *         "fontSizes": [ { "name": "Font size name" } ],
	 *         "fontStyles": [ { "name": "Font size name" } ]
	 *       }
	 *     }
	 *   }
	 * }
	 *
	 * will return this output:
	 *
	 * [
	 *   0 => [
	 *     'path'    => [ 'settings', '*', 'typography', 'fontSizes' ],
	 *     'key'     => 'name',
	 *     'context' => 'Font size name'
	 *   ],
	 *   1 => [
	 *     'path'    => [ 'settings', '*', 'typography', 'fontStyles' ],
	 *     'key'     => 'name',
	 *     'context' => 'Font style name'
	 *   ]
	 * ]
	 *
	 * @param array $i18n_partial A tree that follows the format of i18n-theme.json.
	 * @param array $current_path Keeps track of the path as we walk down the given tree.
	 *
	 * @return array A linear array containing the paths to translate.
	 */
	private static function extract_paths_to_translate( $i18n_partial, $current_path = array() ) {
		$result = array();
		foreach ( $i18n_partial as $property => $partial_child ) {
			if ( is_numeric( $property ) ) {
				foreach ( $partial_child as $key => $context ) {
					return array(
						array(
							'path'    => $current_path,
							'key'     => $key,
							'context' => $context,
						),
					);
				}
			}
			$result = array_merge(
				$result,
				self::extract_paths_to_translate( $partial_child, array_merge( $current_path, array( $property ) ) )
			);
		}
		return $result;
	}

	/**
	 * Returns a data structure used in theme.json translation.
	 *
	 * @return array An array of theme.json fields that are translatable and the keys that are translatable
	 */
	public static function get_fields_to_translate() {
		if ( null === self::$theme_json_i18n ) {
			$file_structure        = self::read_json_file( __DIR__ . '/experimental-i18n-theme.json' );
			self::$theme_json_i18n = self::extract_paths_to_translate( $file_structure );
		}
		return self::$theme_json_i18n;
	}

	/**
	 * Translates a chunk of the loaded theme.json structure.
	 *
	 * @param array  $array_to_translate The chunk of theme.json to translate.
	 * @param string $key                The key of the field that contains the string to translate.
	 * @param string $context            The context to apply in the translation call.
	 * @param string $domain             Text domain. Unique identifier for retrieving translated strings.
	 *
	 * @return array Returns the modified $theme_json chunk.
	 */
	private static function translate_theme_json_chunk( array $array_to_translate, $key, $context, $domain ) {
		foreach ( $array_to_translate as $item_key => $item_to_translate ) {
			if ( empty( $item_to_translate[ $key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain
			$array_to_translate[ $item_key ][ $key ] = translate_with_gettext_context( $array_to_translate[ $item_key ][ $key ], $context, $domain );
		}

		return $array_to_translate;
	}

	/**
	 * Given a theme.json structure modifies it in place
	 * to update certain values by its translated strings
	 * according to the language set by the user.
	 *
	 * @param array  $theme_json The theme.json to translate.
	 * @param string $domain    Optional. Text domain. Unique identifier for retrieving translated strings.
	 *                          Default 'default'.
	 *
	 * @return array Returns the modified $theme_json_structure.
	 */
	private static function translate( $theme_json, $domain = 'default' ) {
		$fields = self::get_fields_to_translate();
		foreach ( $fields as $field ) {
			$path    = $field['path'];
			$key     = $field['key'];
			$context = $field['context'];

			/*
			 * We need to process the paths that include '*' separately.
			 * One example of such a path would be:
			 * [ 'settings', 'blocks', '*', 'color', 'palette' ]
			 */
			$nodes_to_iterate = array_keys( $path, '*', true );
			if ( ! empty( $nodes_to_iterate ) ) {
				/*
				 * At the moment, we only need to support one '*' in the path, so take it directly.
				 * - base will be [ 'settings', 'blocks' ]
				 * - data will be [ 'color', 'palette' ]
				 */
				$base_path = array_slice( $path, 0, $nodes_to_iterate[0] );
				$data_path = array_slice( $path, $nodes_to_iterate[0] + 1 );
				$base_tree = _wp_array_get( $theme_json, $base_path, array() );
				foreach ( $base_tree as $node_name => $node_data ) {
					$array_to_translate = _wp_array_get( $node_data, $data_path, null );
					if ( is_null( $array_to_translate ) ) {
						continue;
					}

					// Whole path will be [ 'settings', 'blocks', 'core/paragraph', 'color', 'palette' ].
					$whole_path       = array_merge( $base_path, array( $node_name ), $data_path );
					$translated_array = self::translate_theme_json_chunk( $array_to_translate, $key, $context, $domain );
					gutenberg_experimental_set( $theme_json, $whole_path, $translated_array );
				}
			} else {
				$array_to_translate = _wp_array_get( $theme_json, $path, null );
				if ( is_null( $array_to_translate ) ) {
					continue;
				}

				$translated_array = self::translate_theme_json_chunk( $array_to_translate, $key, $context, $domain );
				gutenberg_experimental_set( $theme_json, $path, $translated_array );
			}
		}

		return $theme_json;
	}

	/**
	 * Return core's origin config.
	 *
	 * @return WP_Theme_JSON Entity that holds core data.
	 */
	public static function get_core_data() {
		if ( null !== self::$core ) {
			return self::$core;
		}

		$config     = self::read_json_file( __DIR__ . '/experimental-default-theme.json' );
		$config     = self::translate( $config );
		self::$core = new WP_Theme_JSON( $config );

		return self::$core;
	}

	/**
	 * Returns the theme's data.
	 *
	 * Data from theme.json can be augmented via the
	 * $theme_support_data variable. This is useful, for example,
	 * to backfill the gaps in theme.json that a theme has declared
	 * via add_theme_supports.
	 *
	 * Note that if the same data is present in theme.json
	 * and in $theme_support_data, the theme.json's is not overwritten.
	 *
	 * @param array $theme_support_data Theme support data in theme.json format.
	 *
	 * @return WP_Theme_JSON Entity that holds theme data.
	 */
	public static function get_theme_data( $theme_support_data = array() ) {
		if ( null === self::$theme ) {
			$theme_json_data = self::read_json_file( self::get_file_path_from_theme( 'theme.json' ) );
			// Fallback to experimental-theme.json.
			if ( empty( $theme_json_data ) ) {
				$theme_json_data = self::read_json_file( self::get_file_path_from_theme( 'experimental-theme.json' ) );
			}
			$theme_json_data = self::translate( $theme_json_data, wp_get_theme()->get( 'TextDomain' ) );
			self::$theme     = new WP_Theme_JSON( $theme_json_data );
		}

		if ( empty( $theme_support_data ) ) {
			return self::$theme;
		}

		/*
		 * We want the presets and settings declared in theme.json
		 * to override the ones declared via add_theme_support.
		 */
		$with_theme_supports = new WP_Theme_JSON( $theme_support_data );
		$with_theme_supports->merge( self::$theme );

		return $with_theme_supports;
	}

	/**
	 * There are three sources of data (origins) for a site:
	 * core and theme. The theme's has higher priority
	 * than core's.
	 *
	 * Unlike the getters {@link get_core_data} and {@link get_theme_data},
	 * this method returns data after it has been merged
	 * with the previous origins. This means that if the same piece of data
	 * is declared in different origins (theme and core),
	 * the last origin overrides the previous.
	 *
	 * @param array $settings Existing block editor settings.
	 *                        Empty array by default.
	 *
	 * @return WP_Theme_JSON
	 */
	public static function get_merged_data( $settings = array() ) {
		$theme_support_data = WP_Theme_JSON::get_from_editor_settings( $settings );

		$result = new WP_Theme_JSON();
		$result->merge( self::get_core_data() );
		$result->merge( self::get_theme_data( $theme_support_data ) );

		return $result;
	}

	/**
	 * Whether the current theme has a theme.json file.
	 *
	 * @return boolean
	 */
	public static function theme_has_support() {
		if ( ! isset( self::$theme_has_support ) ) {
			self::$theme_has_support = (bool) self::get_file_path_from_theme( 'theme.json' );
			if ( ! self::$theme_has_support ) {
				// Fallback to experimental-theme.json.
				self::$theme_has_support = (bool) self::get_file_path_from_theme( 'experimental-theme.json' );
			}
		}

		return self::$theme_has_support;
	}

	/**
	 * Builds the path to the given file
	 * and checks that it is readable.
	 *
	 * If it isn't, returns an empty string,
	 * otherwise returns the whole file path.
	 *
	 * @param string $file_name Name of the file.
	 * @return string The whole file path or empty if the file doesn't exist.
	 */
	private static function get_file_path_from_theme( $file_name ) {
		// This used to be a locate_template call.
		// However, that method proved problematic
		// due to its use of constants (STYLESHEETPATH)
		// that threw errors in some scenarios.
		//
		// When the theme.json merge algorithm properly supports
		// child themes, this should also fallback
		// to the template path, as locate_template did.
		$located   = '';
		$candidate = get_stylesheet_directory() . '/' . $file_name;
		if ( is_readable( $candidate ) ) {
			$located = $candidate;
		}
		return $located;
	}

	/**
	 * Cleans the cached data so it can be recalculated.
	 */
	public static function clean_cached_data() {
		self::$core              = null;
		self::$theme             = null;
		self::$theme_has_support = null;
		self::$theme_json_i18n   = null;
	}

}

add_action( 'switch_theme', array( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) );
