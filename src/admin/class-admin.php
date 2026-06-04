<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

use WP_Post;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the admin-specific functionality of the plugin.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */
class Admin {

	/**
	 * An Array of block registered within this WordPress instance.
	 *
	 * @var \WP_Block_Type[] $registered_blocks
	 */
	private static $registered_blocks;

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Admin|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Admin The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Admin();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_post_types' ] );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_post_type_posts' ], 100 );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_block_templates' ], 999 );
		add_filter( 'default_content', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'set_default_content' ], 10, 2 );
		add_action( 'admin_notices', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'stale_template_notice' ] );
		add_action( 'admin_post_abet_trash_stale_template', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'trash_stale_template' ] );
		add_action( 'admin_post_abet_trash_all_stale_templates', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'trash_all_stale_templates' ] );
		add_filter( 'post_row_actions', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'remove_row_actions' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'enqueue_admin_assets' ] );
		add_action( 'admin_menu', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'admin_menu' ] );
		add_filter( 'display_post_states', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'add_display_post_states' ], 10, 2 );

		if ( ! wp_is_block_theme() ) {
			add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_taxonomy_posts' ], 100 );
			add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_special_pages' ], 100 );
			add_filter( 'archive_template', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'get_custom_archive' ] );

			// Set default 404 post template.
			add_filter( 'template_include', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'set_404_template' ], 99 );
		}
	}

	/**
	 * Post type definitions.
	 *
	 * @since 1.0.4
	 *
	 * @return array<string, array<string, string|boolean>>
	 */
	public static function post_types() {
		static $post_types;

		if ( ! $post_types ) {
			$post_types = [
				'block-templates'    => [
					'single'               => _x( 'Post Type Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Post Type Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Post Type Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype',
					'for'                  => 'post_type',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
				'pt-arch-templates'  => [
					'single'               => _x( 'Post Type Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Post Type Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Post Type Archive Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype_archive',
					'for'                  => 'post_type',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'tax-arch-templates' => [
					'single'               => _x( 'Taxonomy Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Taxonomy Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Taxonomy Archive Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_taxonomy_archive',
					'for'                  => 'taxonomy',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'special-templates'  => [
					'single'               => _x( 'Special Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Special Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Special Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_special',
					'for'                  => 'special',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
			];
			if ( wp_is_block_theme() ) {
				unset( $post_types['pt-arch-templates'], $post_types['tax-arch-templates'], $post_types['special-templates'] );
			}
		}

		return $post_types;
	}

	/**
	 * Register block templates for all post types.
	 *
	 * @return void
	 */
	public static function register_block_templates() {
		self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		// Prototype toggle: the default_content approach (see self::set_default_content())
		// prefills new posts with the template's markup verbatim, so the post-type template —
		// which rebuilds each block via createBlock and therefore drops markup-sourced content —
		// is no longer needed. Filter 'abet_use_default_content' to false to keep using $object->template.
		if ( self::use_default_content() ) {
			return;
		}

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			$post_type = get_post_meta( $post_id, '_template_for_posttype', true );
			$object    = get_post_type_object( $post_type );

			if ( ! $object ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( $post && has_blocks( $post->post_content ) ) {
				$blocks   = parse_blocks( $post->post_content );
				$template = self::blocks_to_template( $blocks );

				if ( count( $template ) ) {
					$object->template = $template;
				}
			}
		}
	}

	/**
	 * Convert Gutenberg blocks to a block template.
	 *
	 * @param array<mixed> $blocks An array of blocks as provide by parse_blocks().
	 *
	 * @return array<mixed> A block template.
	 */
	private static function blocks_to_template( $blocks ) {
		$template = [];
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			// parse_blocks() only returns attributes stored in the block delimiter's JSON.
			// Attributes declared with a "source" (e.g. the "html"-sourced text of a heading
			// or paragraph) live in the markup itself, and the template format rebuilds each
			// block from its attributes alone, so without this their content would be lost.
			$block['attrs'] = self::add_sourced_attributes( $block );

			if ( isset( $block['attrs']['textAsPlaceholder'], self::$registered_blocks[ $block['blockName'] ] ) && $block['attrs']['textAsPlaceholder'] ) {
				$attributes = self::$registered_blocks[ $block['blockName'] ]->get_attributes();
				foreach ( $attributes as $attribute_name => $attribute ) {
					if ( isset( $attributes[ $attribute_name . 'Placeholder' ], $block['attrs'][ $attribute_name ] ) ) {
						$block['attrs'][ $attribute_name . 'Placeholder' ] = $block['attrs'][ $attribute_name ];
						unset( $block['attrs'][ $attribute_name ] );
					}
				}
			}

			$sub_template = [
				$block['blockName'],
				$block['attrs'] ?? [],
				self::blocks_to_template( $block['innerBlocks'] ),
				$block['innerHTML'] ?? '',
				$block['innerContent'] ?? [],
			];
			$template[]   = $sub_template;
		}

		return $template;
	}

	/**
	 * Read attributes that are sourced from the block markup back into the attributes array.
	 *
	 * Block attributes declared with a "source" (e.g. "html", "rich-text", "text" or
	 * "attribute") are not stored in the block delimiter's JSON but in the markup, so
	 * parse_blocks() leaves them out of $block['attrs']. Since the post type template
	 * rebuilds each block from its attributes only, this restores those values from the
	 * block's innerHTML so content such as a heading or paragraph text is preserved.
	 *
	 * Only the simple CSS selectors used by block.json definitions are supported
	 * (comma separated tag, ".class" and "#id" selectors); anything else is skipped.
	 *
	 * @param array<mixed> $block A single block as provided by parse_blocks().
	 *
	 * @return array<mixed> The block attributes, including any markup-sourced values.
	 */
	private static function add_sourced_attributes( $block ) {
		$attrs = $block['attrs'] ?? [];

		if ( empty( $block['innerHTML'] ) || ! isset( self::$registered_blocks[ $block['blockName'] ] ) ) {
			return $attrs;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="abet-root">' . $block['innerHTML'] . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		$xpath = new \DOMXPath( $dom );

		foreach ( self::$registered_blocks[ $block['blockName'] ]->get_attributes() as $name => $definition ) {
			if ( isset( $attrs[ $name ] ) || empty( $definition['source'] ) ) {
				continue;
			}

			// Build an XPath query from the (simple) CSS selector. Without a selector the block root is used.
			$query = '//*[@id="abet-root"]';
			if ( ! empty( $definition['selector'] ) ) {
				$branches = [];
				foreach ( explode( ',', $definition['selector'] ) as $piece ) {
					$piece = trim( $piece );
					if ( '' === $piece ) {
						continue;
					}
					if ( 0 === strpos( $piece, '.' ) ) {
						$branches[] = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . substr( $piece, 1 ) . ' ")]';
					} elseif ( 0 === strpos( $piece, '#' ) ) {
						$branches[] = '//*[@id="' . substr( $piece, 1 ) . '"]';
					} else {
						$branches[] = '//' . $piece;
					}
				}
				$query = implode( ' | ', $branches );
			}

			$nodes = $xpath->query( $query );
			if ( false === $nodes || ! $nodes->length ) {
				continue;
			}
			$node = $nodes->item( 0 );

			switch ( $definition['source'] ) {
				case 'attribute':
					if ( ! empty( $definition['attribute'] ) && $node instanceof \DOMElement && $node->hasAttribute( $definition['attribute'] ) ) {
						$attrs[ $name ] = $node->getAttribute( $definition['attribute'] );
					}
					break;
				case 'text':
					$attrs[ $name ] = $node->textContent;
					break;
				case 'html':
				case 'rich-text':
					$html = '';
					foreach ( $node->childNodes as $child ) {
						$html .= $dom->saveHTML( $child );
					}
					$attrs[ $name ] = $html;
					break;
			}
		}

		return $attrs;
	}

	/**
	 * Whether new posts should be prefilled with the template content instead of a post-type template.
	 *
	 * @return bool True to use the default_content approach, false to register $object->template.
	 */
	private static function use_default_content() {
		return (bool) apply_filters( 'abet_use_default_content', true );
	}

	/**
	 * Get the (cached) IDs of all post-type template posts.
	 *
	 * @return int[] The template post IDs.
	 */
	private static function get_post_type_template_posts() {
		$cache_key      = 'abet_posts_with_meta_' . md5( '_template_for_posttype' );
		$template_posts = wp_cache_get( $cache_key );

		if ( false === $template_posts ) {
			$template_posts = get_posts(
				[
					'numberposts' => -1,
					'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
					'post_status' => 'any',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
					'meta_key'    => '_template_for_posttype',
					'fields'      => 'ids',
				]
			);
			wp_cache_set( $cache_key, $template_posts, '', HOUR_IN_SECONDS );
		}

		return $template_posts ?: [];
	}

	/**
	 * Prefill a new post with its post-type template content.
	 *
	 * Unlike registering $object->template (which the editor rebuilds with createBlock, dropping
	 * any markup-sourced attributes such as a heading's or paragraph's text), this copies the
	 * template's blocks verbatim into the new post. serialize_blocks() round-trips the original
	 * markup, so sourced content is preserved without having to read it back out of the HTML.
	 *
	 * @param string  $content The default post content.
	 * @param WP_Post $post    The post being created.
	 *
	 * @return string The (possibly prefilled) default content.
	 */
	public static function set_default_content( $content, $post ) {
		// Only act on an empty new post when the feature is on.
		if ( ! self::use_default_content() || ! empty( $content ) || ! $post instanceof WP_Post || empty( $post->post_type ) ) {
			return $content;
		}

		if ( ! self::$registered_blocks ) {
			self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		}

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			if ( $post->post_type !== get_post_meta( $post_id, '_template_for_posttype', true ) ) {
				continue;
			}

			$template_post = get_post( $post_id );
			if ( ! $template_post || ! has_blocks( $template_post->post_content ) ) {
				break;
			}

			// parse_blocks() -> serialize_blocks() round-trips the original markup as-is, so
			// markup-sourced content (heading/paragraph text) is preserved with no HTML parsing.
			$prefilled = serialize_blocks( self::apply_placeholders( parse_blocks( $template_post->post_content ) ) );

			// Fall back to the original content rather than blanking the post if serialization yields nothing.
			return '' !== trim( $prefilled ) ? $prefilled : $content;
		}

		return $content;
	}

	/**
	 * Move the value of placeholder-enabled attributes into their "...Placeholder" counterpart.
	 *
	 * Mirrors the textAsPlaceholder handling of self::blocks_to_template() so the verbatim copy
	 * keeps showing the configured text as a placeholder rather than as real content.
	 *
	 * @param array<mixed> $blocks Blocks as provided by parse_blocks().
	 *
	 * @return array<mixed> The blocks with placeholder attributes applied.
	 */
	private static function apply_placeholders( $blocks ) {
		foreach ( $blocks as &$block ) {
			if ( empty( $block['blockName'] ) || ! isset( self::$registered_blocks[ $block['blockName'] ] ) ) {
				continue;
			}

			if ( ! empty( $block['attrs']['textAsPlaceholder'] ) ) {
				$attributes = self::$registered_blocks[ $block['blockName'] ]->get_attributes();
				foreach ( $attributes as $attribute_name => $attribute ) {
					if ( isset( $attributes[ $attribute_name . 'Placeholder' ], $block['attrs'][ $attribute_name ] ) ) {
						$block['attrs'][ $attribute_name . 'Placeholder' ] = $block['attrs'][ $attribute_name ];
						unset( $block['attrs'][ $attribute_name ] );
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::apply_placeholders( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Enqueue assets for dynamic blocks for the admin.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		// Only load on the block-templates editor (new and existing posts), not on the list screen.
		// get_queried_object_id() is unreliable in wp-admin, so rely on the current screen instead.
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'block-templates' !== $screen->post_type ) {
			return;
		}

		if ( ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.js' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Surface a missing build instead of enqueuing a 404.
			error_log( 'block-editor-templates-admin (admin.js) isn`t found. Forgot to run `npm run build`?' );

			return;
		}

		$script_asset_path = ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [];

		wp_enqueue_script(
			'block-editor-templates-admin',
			ABET_ASSETS_URL . 'admin.js',
			$script_asset['dependencies'] ?? [],
			$script_asset['version'] ?? ABET_VERSION,
			false
		);
	}

	/**
	 * Whether a post type is edited with the block editor.
	 *
	 * show_in_rest does not by itself imply block-editor support, so a post type can be exposed to
	 * the REST API yet have no editor. Use WordPress' own check when it is available and fall back
	 * to the block editor's minimum requirements (REST support and an editor) otherwise.
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return bool True when the post type uses the block editor.
	 */
	private static function uses_block_editor( $post_type ) {
		// @todo: Check if `$post_type` is an post_type since there are also taxonomy templates.

		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			return use_block_editor_for_post_type( $post_type );
		}

		// Before WordPress 6.1 use_block_editor_for_post_type() lived in wp-admin/includes/post.php, which
		// is not loaded yet on the 'init' hook where create_post_type_posts() runs. In that case fall back
		// to the same requirements it checks (REST support and an editor), minus its filter.
		$object = get_post_type_object( $post_type );

		return $object && $object->show_in_rest && post_type_supports( $post_type, 'editor' );
	}

	/**
	 * Find Post Type Template posts created for a post type that no longer uses the block editor.
	 *
	 * Unregistered post types are skipped on purpose (e.g. a temporarily-deactivated plugin), so the
	 * editor is only nudged about templates that exist for a post type which is present but editor-less.
	 *
	 * @return array<int, string> Map of post ID to template title.
	 */
	private static function get_stale_post_type_templates() {
		$stale = [];

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			if ( 'block-templates' !== get_post_type( $post_id ) || 'trash' === get_post_status( $post_id ) ) {
				continue;
			}

			$post_type = get_post_meta( $post_id, '_template_for_posttype', true );
			if ( empty( $post_type ) || 'general_template' === $post_type ) {
				continue;
			}

			if ( post_type_exists( $post_type ) && ! self::uses_block_editor( $post_type ) ) {
				$stale[ $post_id ] = get_the_title( $post_id );
			}
		}

		return $stale;
	}

	/**
	 * Show an admin notice for stale Post Type Templates, with a per-template "Move to Trash" button.
	 *
	 * The notice leaves the decision to the editor; nothing is trashed automatically.
	 *
	 * @return void
	 */
	public static function stale_template_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-block-templates' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success count for display.
		$trashed = isset( $_GET['abet_trashed'] ) ? absint( wp_unslash( $_GET['abet_trashed'] ) ) : 0;
		if ( $trashed > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of templates moved to the trash. */
				esc_html( sprintf( _n( '%d template moved to the trash.', '%d templates moved to the trash.', $trashed, 'block-editor-templates' ), $trashed ) )
			);
		}

		// Only list templates the current user is actually allowed to trash.
		$stale = [];
		foreach ( self::get_stale_post_type_templates() as $post_id => $title ) {
			if ( current_user_can( 'delete_post', $post_id ) ) {
				$stale[ $post_id ] = $title;
			}
		}
		if ( empty( $stale ) ) {
			return;
		}

		echo '<div class="notice notice-error">';

		if ( count( $stale ) > 1 ) {
			// Multiple templates: an intro line followed by a real list, so the relationship is conveyed semantically.
			printf( '<p>%s</p>', esc_html__( 'These Post Type Templates exist for post types that no longer use the block editor. You can move them to the trash:', 'block-editor-templates' ) );
			echo '<ul style="list-style:disc;margin-left:20px;">';
			foreach ( $stale as $post_id => $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts are escaped in the helpers.
				printf( '<li>%1$s &mdash; %2$s</li>', esc_html( self::stale_template_label( $post_id, $title ) ), self::stale_template_trash_link( $post_id, $title ) );
			}
			echo '</ul>';

			// Bulk action. The handler re-derives the stale set server-side, so only templates without
			// a block editor are trashed regardless of what is submitted.
			printf(
				'<p><a href="%1$s" class="button button-link-delete">%2$s</a></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=abet_trash_all_stale_templates' ), 'abet_trash_all_stale_templates' ) ),
				esc_html__( 'Move all to Trash', 'block-editor-templates' )
			);
		} else {
			// A single template reads better as a sentence; a one-item list is just noise for screen readers.
			reset( $stale );
			$post_id = key( $stale );
			$title   = current( $stale );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts are escaped (sentence via esc_html, link in the helper).
			printf(
				'<p>%1$s %2$s</p>',
				esc_html(
					sprintf(
						/* translators: %s: template title. */
						__( 'The Post Type Template “%s” exists for a post type that no longer uses the block editor.', 'block-editor-templates' ),
						self::stale_template_label( $post_id, $title )
					)
				),
				self::stale_template_trash_link( $post_id, $title )
			);
		}

		echo '</div>';
	}

	/**
	 * Build the human label for a stale template, falling back to the post ID when it has no title.
	 *
	 * @param int    $post_id The template post ID.
	 * @param string $title   The template post title.
	 *
	 * @return string The label.
	 */
	private static function stale_template_label( $post_id, $title ) {
		/* translators: %d: template post ID. */
		return '' !== trim( (string) $title ) ? (string) $title : sprintf( __( 'Template #%d', 'block-editor-templates' ), $post_id );
	}

	/**
	 * Build a nonce-protected "Move to Trash" link for a stale template.
	 *
	 * The visible text is identical for every template, so the template name is exposed to assistive
	 * technology through screen-reader-text. That keeps each link uniquely identifiable when navigating
	 * by links, without repeating the name visually.
	 *
	 * @param int    $post_id The template post ID.
	 * @param string $title   The template post title.
	 *
	 * @return string Escaped anchor markup.
	 */
	private static function stale_template_trash_link( $post_id, $title ) {
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'abet_trash_stale_template',
					'post'   => $post_id,
				],
				admin_url( 'admin-post.php' )
			),
			'abet_trash_stale_template_' . $post_id
		);

		return sprintf(
			'<a href="%1$s" class="button-link-delete">%2$s<span class="screen-reader-text">%3$s</span></a>',
			esc_url( $url ),
			esc_html__( 'Move to Trash', 'block-editor-templates' ),
			esc_html( ': ' . self::stale_template_label( $post_id, $title ) )
		);
	}

	/**
	 * Handle the "Move to Trash" action from self::stale_template_notice().
	 *
	 * @return void
	 */
	public static function trash_stale_template() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to trash this template.', 'block-editor-templates' ) );
		}

		check_admin_referer( 'abet_trash_stale_template_' . $post_id );

		wp_trash_post( $post_id );

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'    => 'block-templates',
					'abet_trashed' => 1,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "Move all to Trash" action from self::stale_template_notice().
	 *
	 * The stale set is re-derived here rather than taken from the request, so only Post Type Templates
	 * whose post type no longer uses the block editor are trashed, and only those the user may delete.
	 *
	 * @return void
	 */
	public static function trash_all_stale_templates() {
		check_admin_referer( 'abet_trash_all_stale_templates' );

		$trashed = 0;
		foreach ( self::get_stale_post_type_templates() as $post_id => $title ) {
			if ( current_user_can( 'delete_post', $post_id ) ) {
				wp_trash_post( $post_id );
				++$trashed;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'    => 'block-templates',
					'abet_trashed' => $trashed,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Create a block template for each registered post type and also an Archive Template for each registered post type
	 * that has an archive.
	 *
	 * @return void
	 */
	public static function create_post_type_posts() {
		// Get all registered post types.
		$registered_post_types = array_merge(
			get_post_types(
				[
					'_builtin'     => true,
					'public'       => true,
					'show_in_rest' => true,
				],
				'objects'
			),
			get_post_types(
				[
					'_builtin'     => false,
					'show_in_rest' => true,
				],
				'objects'
			)
		);
		unset( $registered_post_types['attachment'] );
		foreach ( self::post_types() as $slug => $settings ) {
			unset( $registered_post_types[ $slug ] );
		}

		foreach ( self::post_types() as $slug => $settings ) {
			if ( 'post_type' !== $settings['for'] ) {
				continue;
			}

			$filtered_registered_post_types = $registered_post_types;
			if ( true === $settings['only_for_has_archive'] ) {
				foreach ( $filtered_registered_post_types as $pt_slug => $obj ) {
					if ( false === $obj->has_archive ) {
						unset( $filtered_registered_post_types[ $pt_slug ] );
					}
				}
			}

			$filtered_registered_post_types = array_keys( $filtered_registered_post_types );

			// A Post Type Template is edited in the block editor, so only offer it for post types
			// that actually use the block editor. show_in_rest alone does not imply editor support.
			if ( false === $settings['general_template'] ) {
				$filtered_registered_post_types = array_values( array_filter( $filtered_registered_post_types, [ self::class, 'uses_block_editor' ] ) );
			}

			// Get all posts that have the meta field.
			// Get all posts that have the meta field.
			$cache_key            = 'abet_posts_with_meta_' . md5( (string) $settings['meta_field'] );
			$posts_with_templates = wp_cache_get( $cache_key );

			if ( false === $posts_with_templates ) {
				$posts_with_templates = get_posts(
					[
						'numberposts' => -1,
						'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
						'post_status' => 'any',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
						'meta_key'    => (string) $settings['meta_field'],
						'fields'      => 'ids',
					]
				);
				wp_cache_set( $cache_key, $posts_with_templates, '', HOUR_IN_SECONDS );
			}

			// Extract the meta values.
			$created_templates = [];
			foreach ( $posts_with_templates as $post_id ) {
				$meta_value = get_post_meta( $post_id, (string) $settings['meta_field'], true );
				if ( ! empty( $meta_value ) ) {
					$created_templates[] = $meta_value;
				}
			}

			if ( $settings['general_template'] ) {
				if ( ! in_array( 'general_template', $created_templates, true ) ) {
					wp_insert_post(
						[
							'post_type'   => $slug,
							'post_title'  => __( '_General Template', 'block-editor-templates' ),
							'post_status' => 'draft',
							'meta_input'  => [
								$settings['meta_field'] => 'general_template',
							],
						]
					);
				}
				unset( $created_templates[ array_search( 'general_template', $created_templates, true ) ] );
			}

			$difference = array_merge( array_diff( $filtered_registered_post_types, $created_templates ), array_diff( $created_templates, $filtered_registered_post_types ) );
			if ( count( $difference ) ) {
				foreach ( $difference as $post_type ) {
					if ( in_array( $post_type, $filtered_registered_post_types, true ) ) {
						// We need to create a new post.
						$obj = get_post_type_object( $post_type );
						wp_insert_post(
							[
								'post_type'   => $slug,
								'post_title'  => $obj->labels->name,
								'post_status' => 'draft',
								'meta_input'  => [
									$settings['meta_field'] => $post_type,
								],
							]
						);
					}
				}
			}
		}
	}

	/**
	 * Create an Archive Template for each registered taxonomy.
	 *
	 * @return void
	 */
	public static function create_taxonomy_posts() {
		// Get all registered taxonomies.
		$registered_taxonomies = get_taxonomies( [ 'public' => true ] );
		$registered_taxonomies = array_values( $registered_taxonomies );

		foreach ( self::post_types() as $slug => $settings ) {
			if ( 'taxonomy' !== $settings['for'] ) {
				continue;
			}
			// Get all posts that have the meta field.
			$cache_key            = 'abet_posts_with_meta_' . md5( (string) $settings['meta_field'] );
			$posts_with_templates = wp_cache_get( $cache_key );

			if ( false === $posts_with_templates ) {
				$posts_with_templates = get_posts(
					[
						'numberposts' => -1,
						'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
						'post_status' => 'any',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
						'meta_key'    => (string) $settings['meta_field'],
						'fields'      => 'ids',
					]
				);
				wp_cache_set( $cache_key, $posts_with_templates, '', HOUR_IN_SECONDS );
			}

			// Extract the meta values.
			$created_templates = [];
			foreach ( $posts_with_templates as $post_id ) {
				$meta_value = get_post_meta( $post_id, (string) $settings['meta_field'], true );
				if ( ! empty( $meta_value ) ) {
					$created_templates[] = $meta_value;
				}
			}

			if ( $settings['general_template'] ) {
				if ( ! in_array( 'general_template', $created_templates, true ) ) {
					wp_insert_post(
						[
							'post_type'   => $slug,
							'post_title'  => __( '_General Template', 'block-editor-templates' ),
							'post_status' => 'draft',
							'meta_input'  => [
								$settings['meta_field'] => 'general_template',
							],
						]
					);
				}
				unset( $created_templates[ array_search( 'general_template', $created_templates, true ) ] );
			}

			$difference = array_merge( array_diff( $registered_taxonomies, $created_templates ), array_diff( $created_templates, $registered_taxonomies ) );
			if ( count( $difference ) ) {
				foreach ( $difference as $taxonomy ) {
					if ( in_array( $taxonomy, $registered_taxonomies, true ) ) {
						// We need to create a new post.
						$obj = get_taxonomy( $taxonomy );
						if ( $obj ) {
							wp_insert_post(
								[
									'post_type'   => $slug,
									'post_title'  => $obj->labels->name,
									'post_status' => 'draft',
									'meta_input'  => [
										$settings['meta_field'] => $taxonomy,
									],
								]
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Create a special page for each registered special page.
	 *
	 * @return void
	 */
	public static function create_special_pages() {
		global $wpdb;

		$special_pages = [
			'404' => __( '404 page', 'block-editor-templates' ),
		];

		// Get all created templates.
		$created_templates = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s", '_template_for_special' ),
			ARRAY_A
		);
		$created_templates = array_column( $created_templates, 'meta_value' );

		foreach ( $special_pages as $special_page_slug => $special_page_name ) {
			// Force the slug to be a string.
			$special_page_slug = (string) $special_page_slug;

			if ( ! in_array( $special_page_slug, $created_templates, true ) ) {
				wp_insert_post(
					[
						'post_type'   => 'special-templates',
						'post_title'  => $special_page_name,
						'post_status' => 'draft',
						'meta_input'  => [
							'_template_for_special' => $special_page_slug,
						],
					]
				);
			}
		}
	}

	/**
	 * Remove trash option from row actions.
	 *
	 * See: https://wordpress.stackexchange.com/a/295184
	 *
	 * @param string[] $actions An array of row action links.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return string[]
	 */
	public static function remove_row_actions( $actions, $post ) {
		if ( array_key_exists( $post->post_type, self::post_types() ) ) {
			unset( $actions['clone'] );

			// If the post is the General template, then remove the trash link.
			if (
				'general_template' === get_post_meta( $post->ID, '_template_for_posttype_archive', true )
				|| 'general_template' === get_post_meta( $post->ID, '_template_for_taxonomy_archive', true )
			) {
				unset( $actions['trash'] );
			}

			// Replace the view link with a link to the actual frontend page, or remove it.
			$preview_link = 'publish' === $post->post_status && ! empty( $post->post_content ) ? self::get_template_preview_link( $post ) : false;
			if ( $preview_link ) {
				$actions['view'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $preview_link ),
					esc_html__( 'View', 'block-editor-templates' )
				);
			} else {
				unset( $actions['view'] );
			}
		}

		return $actions;
	}

	/**
	 * Register the post types for this plugin.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		foreach ( self::post_types() as $post_type_slug => $settings ) {
			$post_type_single = (string) $settings['single'];
			$post_type_plural = (string) $settings['plural'];

			$labels = [
				'name'               => $post_type_single,
				'singular_name'      => $post_type_single,
				'add_new'            => __( 'Add New', 'block-editor-templates' ),
				/* translators: %s: CPT name */
				'add_new_item'       => sprintf( __( 'Add New %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'edit_item'          => sprintf( __( 'Edit %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'new_item'           => sprintf( __( 'New %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'all_items'          => sprintf( __( 'All %s', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'view_item'          => sprintf( __( 'View %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'search_items'       => sprintf( __( 'Search %s', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'not_found'          => sprintf( __( 'No %s found', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'not_found_in_trash' => sprintf( __( 'No %s found in trash', 'block-editor-templates' ), $post_type_plural ),
				'parent_item_colon'  => '',
				'menu_name'          => $post_type_single,
			];
			$args   = [
				'label'               => $post_type_single,
				'description'         => (string) $settings['description'],
				'labels'              => $labels,
				'supports'            => [ 'title', 'editor' ],
				'taxonomies'          => [],
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'menu_position'       => 100,
				// 5 - below Posts,       10 - below Media,       15 - below Links,
				// 20 - below Pages,       25 - below comments,    60 - below first separator,
				// 65 - below Plugins,     70 - below Users,       75 - below Tools,
				// 80 - below Settings,    100 - below second separator.
				'menu_icon'           => 'dashicons-media-code',
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				/**
				 * Filters the capability_type of the post_type.
				 *
				 * Allow overriding the base capability type for finer access control.
				 *
				 * @since 1.0.0
				 *
				 * @param string|array $capability_type The capability type as defined by WordPress, 'post' by default.
				 *                                      Filter can return a string or a 2-element array.
				 *                                      See function get_post_type_capabilities for extensive documentation.
				 * @param string $post_type_slug The post type for which the capability is overridden.
				 *
				 * @see   get_post_type_capabilities
				 */
				'capability_type'     => apply_filters( 'acato/block_editor_templates/post_type/capability_type', 'post', $post_type_slug ),
				// See: https://stackoverflow.com/a/16675677 .
				'capabilities'        => [
					'create_posts' => 'do_not_allow',
				],
				'map_meta_cap'        => true,
				'show_in_rest'        => true,
			];
			register_post_type( $post_type_slug, $args );
		}
	}

	/**
	 * Add an admin menu for the Block templates.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page( 'Block Templates', 'Block Templates', 'manage_options', 'edit.php?post_type=block-templates', '', 'dashicons-media-code' );
		foreach ( self::post_types() as $slug => $settings ) {
			add_submenu_page( 'edit.php?post_type=block-templates', (string) $settings['plural'], (string) $settings['plural'], 'manage_options', 'edit.php?post_type=' . $slug );
		}
	}

	/**
	 * Check if a custom Archive Template is available and if so return the path to the correct template file.
	 *
	 * @param string $archive_template The current archive template.
	 *
	 * @return string
	 */
	public static function get_custom_archive( $archive_template ) {
		global $abet_template_post;

		if ( is_post_type_archive() ) {
			global $wp_query;

			$post_type  = 'pt-arch-templates';
			$meta_key   = '_template_for_posttype_archive';
			$meta_value = $wp_query->get( 'post_type' );
			$templates  = [
				'abet-' . $meta_value . '-archive.php',
				'abet-posttype-archive.php',
			];
		} elseif ( is_tax() || is_category() || is_tag() ) {
			global $wp_query;

			$tax        = $wp_query->get_queried_object();
			$post_type  = 'tax-arch-templates';
			$meta_key   = '_template_for_taxonomy_archive';
			$meta_value = $tax->taxonomy;
			$templates  = [
				'abet-' . $meta_value . '-archive.php',
				'abet-taxonomy-archive.php',
			];
		} else {
			return $archive_template;
		}

		$cache_key = 'abet_posts_' . md5( $post_type . $meta_key . $meta_value );
		$posts     = wp_cache_get( $cache_key );

		if ( false === $posts ) {
			$posts = get_posts(
				[
					'fields'     => 'ids',
					'post_type'  => $post_type,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- It is cached.
					'meta_query' => [
						[
							'key'     => $meta_key,
							'value'   => [ 'general_template', $meta_value ],
							'compare' => 'IN',
						],
					],
				]
			);
			wp_cache_set( $cache_key, $posts, '', HOUR_IN_SECONDS );
		}

		$abet_template_post = false;
		switch ( count( $posts ) ) {
			case 0:
				return $archive_template;
			case 1:
				$abet_template_post = $posts[0];
				break;
			default:
				foreach ( $posts as $_post ) {
					$meta = get_post_meta( $_post, $meta_key, true );
					if ( 'general_template' !== $meta ) {
						$abet_template_post = $_post;
						break 2;
					}
				}

				return $archive_template;
		}
		if ( $abet_template_post ) {
			$templates[] = 'abet-archive.php';
			$template    = locate_template( $templates );

			if ( ! $template ) {
				$template = plugin_dir_path( dirname( __DIR__ ) ) . 'templates/abet-archive.php';
			}

			return $template;
		}

		return $archive_template;
	}

	/**
	 * Add a display state to the post list.
	 *
	 * @param string[] $post_states An array of post states.
	 * @param WP_Post  $post        The post object.
	 *
	 * @return string[]
	 */
	public static function add_display_post_states( $post_states, $post ) {
		$post_type = get_post_type( $post );

		// Check if we are on the correct post type.
		if ( ! in_array( $post_type, [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ], true ) ) {
			return $post_states;
		}

		switch ( $post_type ) {
			default:
			case 'block-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_posttype', true );
				$item_exists = post_type_exists( $item_type );
				break;
			case 'pt-arch-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_posttype_archive', true );
				$item_exists = post_type_exists( $item_type );
				break;
			case 'tax-arch-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_taxonomy_archive', true );
				$item_exists = taxonomy_exists( $item_type );
				break;
		}

		// Check if the post is a general template.
		if ( 'general_template' === $item_type ) {
			return $post_states;
		}

		// Check if the post type exists.
		if ( ! $item_exists ) {
			$post_states['deleted'] = esc_html__( 'Item is deleted', 'block-editor-templates' );
		}

		return $post_states;
	}

	/**
	 * Get the frontend preview link for a template post.
	 *
	 * @param WP_Post $post The template post.
	 *
	 * @return string|false The preview URL, or false if not available.
	 */
	private static function get_template_preview_link( $post ) {
		switch ( $post->post_type ) {
			case 'pt-arch-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_posttype_archive', true );
				if ( 'general_template' === $meta_value || empty( $meta_value ) ) {
					return false;
				}
				return get_post_type_archive_link( $meta_value );

			case 'tax-arch-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_taxonomy_archive', true );
				if ( 'general_template' === $meta_value || empty( $meta_value ) ) {
					return false;
				}
				$terms = get_terms(
					[
						'taxonomy'   => $meta_value,
						'number'     => 1,
						'hide_empty' => false,
					]
				);
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					return get_term_link( $terms[0] );
				}
				return false;

			case 'special-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_special', true );
				if ( '404' === $meta_value ) {
					return home_url( '/abet-404-preview' );
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Set the 404 template.
	 *
	 * @param string $template The template to use.
	 *
	 * @return string The template to use.
	 */
	public static function set_404_template( $template ) {
		global $abet_template_post;

		// Check if we are on a 404 page.
		if ( ! is_404() ) {
			return $template;
		}

		$abet_template_404 = get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => 'special-templates',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'post_name'      => '404',
			]
		);

		if ( $abet_template_404 ) {
			$post_id            = reset( $abet_template_404 );
			$abet_template_post = get_post( $post_id );

			$templates[] = 'abet-404.php';
			$template    = locate_template( $templates );

			if ( ! $template ) {
				$template = ABET_ABSPATH . 'templates/abet-404.php';
			}

			return $template;
		}

		return get_404_template();
	}
}
