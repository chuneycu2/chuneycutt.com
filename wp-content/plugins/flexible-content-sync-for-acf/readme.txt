=== Flexible Content Sync for ACF ===
Contributors: rimkio
Tags: acf, export, import, content
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export/import a post or page with every ACF field as a portable JSON file.

== Description ==

Flexible Content Sync for ACF copies one post or page, with all of its content and every ACF field value, from one WordPress site to another. Export downloads a single JSON file; import reads that file and creates a brand new draft post with everything rebuilt — including images, repeaters, flexible content, relationships, and taxonomy terms.

= Key Features =

* Export a single post/page as a self-contained JSON file.
* Import creates a new draft post — your current content is never overwritten.
* Full ACF field support: text-style fields, image/file, gallery, repeater, flexible content, group, clone, link, relationship, post object, page link, taxonomy field, and user field — nested at any depth (repeater inside group, group inside flexible content, and so on).
* Images and files are re-downloaded into the destination site's Media Library on import.
* Relationship/post object references are matched by post title + post type on the destination site; taxonomy terms are matched by name (created if missing). A Link field pointing at a post on the source site is rebuilt as a link to the matching post on the destination site.
* Choose which post types the Export/Import box appears on, from the Flexible Sync menu.
* Quick "Import" button next to the Filter button on the post/page list table (opens an inline upload popover, no page reload), for enabled post types.
* "Export" row action link on each post/page in the list table, for enabled post types.

= Requirements =

Requires Advanced Custom Fields (or ACF PRO) to be installed and active. Core post fields (title, content, excerpt, featured image, taxonomies) still export/import without ACF, but ACF field values will not.

= Known Limitations =

* Relationship, post object, page link, and internal Link field references are matched on the destination site by exact post title + post type. If no matching post exists there, that reference is left empty (a Link field instead keeps its original source-site URL) and a warning is shown after import.
* User fields are matched by email address. If no matching user exists on the destination site, that reference is left empty and a warning is shown after import.
* Fields are matched between sites by ACF field *group* key, then field name within it — assuming the destination site's field groups share the same keys as the source site (true when both use the same theme/plugin or ACF Local JSON sync). A field group that only exists on the source site (different key, or missing entirely) is simply skipped on import.
* Import always creates a brand-new post; there is no "overwrite existing post" mode.

== Installation ==

1. Upload the `flexible-content-sync-for-acf` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Make sure Advanced Custom Fields is active.
4. Open any enabled post/page for editing — the **Flexible Sync** box in the sidebar has an **Export This Page** button.
5. To import an exported file, go to the **Flexible Sync** menu (top-level, in the main admin sidebar) — importing always creates a new draft post, so it never overwrites the one you're editing.
6. Optionally, also in the **Flexible Sync** menu, choose which post types show the Export box (Post and Page are enabled by default).

== Frequently Asked Questions ==

= Does this replace ACF's own field group export/import? =

No. ACF's built-in export/import moves *field group definitions* (the schema). This plugin moves the *content* of one specific post — the actual field values — assuming the destination site already has matching ACF field groups set up (for example, via ACF's Local JSON, or because both sites share the same theme/plugin).

= What happens to images in the post content editor (the WYSIWYG field) or a Repeater/Flexible Content sub-field? =

Image URLs inside rich text content are left as absolute URLs pointing back to the source site. ACF image/file/gallery *fields* (not raw HTML) are the ones that get downloaded into the destination Media Library.

= Can I import into an existing post instead of creating a new one? =

Not in this version — import always creates a new draft post, so you never risk losing existing content.

== Screenshots ==

1. Export/Import box in the post edit screen sidebar.
2. Settings page for choosing which post types are enabled.

== Changelog ==

= 1.0.0 =
* Initial release.
* Export a single post/page (core fields, featured image, taxonomies, and every ACF field) to JSON.
* Import creates a new draft post, re-downloading media and remapping relationship/taxonomy/user references.
* Settings page to choose enabled post types.
* Import button on the post/page list table, next to Filter, for enabled post types.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
