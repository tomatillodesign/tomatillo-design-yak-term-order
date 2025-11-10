=== Yak Term Order ===
Contributors: tomatillodesign, chrisliubeers
Tags: taxonomy, terms, order, drag and drop, hierarchy, admin, facetwp
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop custom ordering for taxonomy terms. Works with FacetWP. No database changes.

== Description ==

Order your taxonomy terms however you want with an inline drag-and-drop interface. Order is stored as term meta, so it's portable and non-destructive.

**Key Features**
- **Inline ordering** – Reorder directly on taxonomy screens, no separate page
- **Hierarchical support** – Order siblings within any parent
- **FacetWP compatible** – Works seamlessly with FacetWP facets
- **Safe & portable** – Uses term meta, no database schema changes
- **Accessible** – Full keyboard navigation and screen reader support

== Installation ==

1. **Activate** the plugin
2. Go to **Settings → Yak Term Order**
   - Select which taxonomies to enable
   - Enable **Admin autosort** and/or **Front-end autosort**
3. Visit your taxonomy screen (e.g., **Posts → Categories**)
   - Click **Show ordering panel**
   - Select a parent, drag to reorder, click **Save Order**

== FAQ ==

= Does this work with FacetWP? =  
Yes. Enable **Front-end autosort** in settings. When FacetWP facets are set to "Sort by: Term Order", your custom ordering is applied automatically.

= What happens if I deactivate? =  
WordPress reverts to default ordering. Your term meta is preserved for later reactivation.

= Can I skip ordering on specific queries? =  
Yes. Add `'ignore_term_order' => true` to your `get_terms()` arguments.

= Does this affect database structure? =  
No. Order is stored in term meta only. Uninstall anytime with zero database changes.

== Developer Notes ==

**Filters**
- `yak_term_order/allowed_taxonomies` – Modify which taxonomies can be ordered
- `yak_term_order/autosort_enabled` – Control autosort by taxonomy/context
- `yak_term_order/secondary_orderby` – Custom SQL for tiebreaker sorting

**Actions**
- `yak_term_order/updated` – Fires after saving term order for a branch

**Constants**
- `YTO_META_KEY` – Term meta key (default: `_yak_term_order`)
- `YTO_DEBUG` – Enable debug logging (define as `true` in wp-config.php)

== Changelog ==

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
