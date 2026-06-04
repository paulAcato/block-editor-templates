=== Block Editor Templates ===
Contributors: acato, rockfire
Tags: block editor, gutenberg, block templates
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.7
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Templates for the WordPress Block Editor.

== Description ==

WordPress offers the ability to [register block templates for the block editor programmatically](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/). This plugin adds a UI to the WP Admin to add block templates without having to be able to program. So if you want every new item of a specific post type to start with a default set of blocks, you can make it happen with this plugin. No programming skills required!

Furthermore, for classic themes (unfortunately not for block themes), it adds the option to edit the content of post type archives and taxonomy archives.

== Installation ==

After installation and activation: Go to wp-admin > Block Templates, here you have the option to choose between:

1. **Post Type Templates**: These are templates that are used whenever a post of the specified post type is being created. There are templates for each of the public post types registered on your site.
1. **Post Type Archive Template**: These are templates that are used whenever an archive page of the specified post type is being requested. There are templates for each of the public post types registered on your site, plus a General Template that will be used when there is no active Post Type Archive Template for the currently requested post type archive.
1. **Taxonomy Archive Templates**: These are templates that are used whenever an archive page of the specified Taxonomy is being requested. There are templates for each of the public Taxonomies registered on your site, plus a General Template that will be used when there is no active Taxonomy Archive Template for the currently requested Taxonomy archive.
1. **Special Templates**: Here you can find templates for special situations, for example the 404 page.

The last two are only available if you use a classic theme, they are not supported for block themes.

== Frequently Asked Questions ==

= I only want to use a template for one post type / post type archive or taxonomy archive, is that possible? =

Yes! By default all templates are created as concepts. Only when you publish them they become active.

= I activated a template, but don't want to use it anymore. How can I deactivate it? =

Simply change the template form published to concept, or delete it. It will no longer be active.

= If I delete a template, will I be able to restore it at a later point? =

Yes! If you delete a template, just as with regular posts you can get it from the trash and restore it. If you permanently delete it, a new clean template will automatically be created as concept.

= The archive templates (for post types and taxonomies) use a php template provided by the plugin, I want to customize it, is that possible? =

Yes! You can create your own template inside your (sub)theme. Simply name it one of the following ways:
- abet-<post_type_slug>-archive.php: for an archive page for a specific post type.
- abet-posttype-archive.php: for all archive pages for post types.
- abet-<taxonomy-slug>-archive.php: for an archive page for a specific taxonomy.
- abet-taxonomy-archive.php: for all archive pages for taxonomies.
- abet-archive.php: for all archive pages.

= You say that some functionality is only supported for classic themes and not for block themes, but what is the difference? =

You can read more about the distinction between these types of themes in [the WordPress Developer Resources](https://developer.wordpress.org/themes/getting-started/what-is-a-theme/#theme-types).

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.]( https://patchstack.com/database/vdp/398f3310-d285-4489-ae3b-07b8ab344119 )

== Changelog ==

= 1.0.7 =
Release Date: March 9th, 2026

Feature: Added a view link to the templates list for archive and special templates, so you can easily view the template after publishing it.

= 1.0.6 =
Release Date: January 12th, 2026

Feature: Added support for customizing the 404-page template, when not using a block theme.

= 1.0.5 =
Release Date: July 30th, 2025

Fix: Changes in the plugin for posting to WordPress.org include using get_posts instead of a direct DB query for checking the existence of templates. This however had the effect of sometimes not detecting existing templates, which caused the plugin to create new templates even if one already existed. This has been fixed by using the correct query parameters in get_posts.

= 1.0.4 =
Release Date: May 26th, 2025

First public version.
