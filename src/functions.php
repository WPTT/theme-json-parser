<?php
/**
 * Conditionally adds functions from the Gutenberg plugin.
 *
 * @package WPTT/theme-json-parser.
 */

if ( ! function_exists( 'gutenberg_supports_block_templates' ) ) {
	/**
	 * Returns whether the current theme is FSE-enabled or not.
	 *
	 * @return boolean Whether the current theme is FSE-enabled or not.
	 */
	function gutenberg_supports_block_templates() {
		return current_theme_supports( 'block-templates' );
	}
}

if ( ! function_exists( 'gutenberg_get_default_block_editor_settings' ) ) {
	/**
	 * Returns the default block editor settings.
	 *
	 * This is a temporary solution until the Gutenberg plugin sets
	 * the required WordPress version to 5.8.
	 *
	 * @see https://core.trac.wordpress.org/ticket/52920
	 *
	 * @since 10.5.0
	 *
	 * @return array The default block editor settings.
	 */
	function gutenberg_get_default_block_editor_settings() {
		// Media settings.
		$max_upload_size = wp_max_upload_size();
		if ( ! $max_upload_size ) {
			$max_upload_size = 0;
		}

		/** This filter is documented in wp-admin/includes/media.php */
		$image_size_names = apply_filters(
			'image_size_names_choose',
			array(
				'thumbnail' => __( 'Thumbnail', 'gutenberg' ),
				'medium'    => __( 'Medium', 'gutenberg' ),
				'large'     => __( 'Large', 'gutenberg' ),
				'full'      => __( 'Full Size', 'gutenberg' ),
			)
		);

		$available_image_sizes = array();
		foreach ( $image_size_names as $image_size_slug => $image_size_name ) {
			$available_image_sizes[] = array(
				'slug' => $image_size_slug,
				'name' => $image_size_name,
			);
		}

		$default_size       = get_option( 'image_default_size', 'large' );
		$image_default_size = in_array( $default_size, array_keys( $image_size_names ), true ) ? $default_size : 'large';

		$image_dimensions = array();
		$all_sizes        = wp_get_registered_image_subsizes();
		foreach ( $available_image_sizes as $size ) {
			$key = $size['slug'];
			if ( isset( $all_sizes[ $key ] ) ) {
				$image_dimensions[ $key ] = $all_sizes[ $key ];
			}
		}

		$editor_settings = array(
			'__unstableEnableFullSiteEditingBlocks' => gutenberg_supports_block_templates(),
			'alignWide'                             => get_theme_support( 'align-wide' ),
			'allowedBlockTypes'                     => true,
			'allowedMimeTypes'                      => get_allowed_mime_types(),
			'disableCustomColors'                   => get_theme_support( 'disable-custom-colors' ),
			'disableCustomFontSizes'                => get_theme_support( 'disable-custom-font-sizes' ),
			'disableCustomGradients'                => get_theme_support( 'disable-custom-gradients' ),
			'enableCustomLineHeight'                => get_theme_support( 'custom-line-height' ),
			'enableCustomSpacing'                   => get_theme_support( 'custom-spacing' ),
			'enableCustomUnits'                     => get_theme_support( 'custom-units' ),
			'isRTL'                                 => is_rtl(),
			'imageDefaultSize'                      => $image_default_size,
			'imageDimensions'                       => $image_dimensions,
			'imageEditing'                          => true,
			'imageSizes'                            => $available_image_sizes,
			'maxUploadFileSize'                     => $max_upload_size,
		);

		// Theme settings.
		$color_palette = current( (array) get_theme_support( 'editor-color-palette' ) );
		if ( false !== $color_palette ) {
			$editor_settings['colors'] = $color_palette;
		}

		$font_sizes = current( (array) get_theme_support( 'editor-font-sizes' ) );
		if ( false !== $font_sizes ) {
			$editor_settings['fontSizes'] = $font_sizes;
		}

		$gradient_presets = current( (array) get_theme_support( 'editor-gradient-presets' ) );
		if ( false !== $gradient_presets ) {
			$editor_settings['gradients'] = $gradient_presets;
		}

		return $editor_settings;
	}
}
