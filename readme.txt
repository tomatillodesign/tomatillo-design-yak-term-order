=== Yak Term Order ===
Contributors: tomatillodesign, chrisliubeers
Tags: taxonomy, terms, order, drag and drop, posts, custom post types, facetwp
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop ordering for taxonomy terms AND posts/custom post types. FacetWP-compatible. Non-destructive.

== Description ==

Order your taxonomy terms and posts however you want with inline drag-and-drop interfaces.

**Term Ordering**
- Reorder directly on taxonomy screens
- Hierarchical support – order siblings within any parent
- Stored as term meta – portable and non-destructive

**Post Type Ordering (NEW in 1.2)**
- Drag-and-drop on any post type list screen
- Autosave – changes saved immediately after each drag
- Uses WordPress's built-in menu_order field
- Perfect for Resources, Products, Team Members, etc.

**General**
- FacetWP compatible – works with facets and templates
- Accessible – full keyboard navigation and screen reader support
- No database schema changes

== Installation ==

1. **Activate** the plugin
2. Go to **Settings → Yak Term Order**
   - Select which taxonomies to enable for term ordering
   - Select which post types to enable for post ordering
   - Configure autosort options
3. **For terms:** Visit your taxonomy screen, drag to reorder, click Save
4. **For posts:** Visit your post type list, drag rows to reorder (autosaves)

== FAQ ==

= Does this work with FacetWP? =
Yes! For terms, enable Front-end autosort and set facets to "Sort by: Term Order". For posts, enable Front-end post autosort and your templates will use the custom order.

= What happens if I deactivate? =
WordPress reverts to default ordering. Your term meta and menu_order values are preserved for later reactivation.

= Can I skip ordering on specific queries? =
Yes. For terms, add `'ignore_term_order' => true` to `get_terms()`. For posts, add `'ignore_menu_order' => true` to `WP_Query`.

= Does this affect database structure? =
No. Term order uses term meta, post order uses the existing menu_order column. Uninstall anytime with zero database changes.

= Which post types can I order? =
Any public post type except attachments. Enable them in Settings → Yak Term Order.

== Developer Notes ==

**Filters**
- `yak_term_order/allowed_taxonomies` – Modify which taxonomies can be ordered
- `yak_term_order/allowed_post_types` – Modify which post types can be ordered
- `yak_term_order/autosort_enabled` – Control term autosort by taxonomy/context

**Actions**
- `yak_term_order/updated` – Fires after saving term order
- `yak_term_order/post_order_updated` – Fires after saving post order

**Constants**
- `YTO_META_KEY` – Term meta key (default: `_yak_term_order`)
- `YTO_DEBUG` – Enable debug logging (define as `true` in wp-config.php)

== Changelog ==

= 1.2.0 =
- **NEW:** Post type ordering with drag-and-drop on admin list screens
- **NEW:** Autosave – post order saves immediately after each drag
- **NEW:** FacetWP integration for post templates
- Uses WordPress's built-in menu_order field
- Admin lists automatically sort by menu_order for enabled post types

= 1.0.0 =
- Full FacetWP integration – respects facet "Sort by" settings
- Comprehensive debug logging system
- Post-query sorting for safety and compatibility
- First stable release

= 0.1.1 =
- Initial beta release
- Drag-and-drop ordering interface
- Term meta storage with normalization
- Keyboard accessibility support
