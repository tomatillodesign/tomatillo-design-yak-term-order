=== Yak Term Order ===
Contributors: tomatillodesign
Tags: taxonomy, terms, order, drag and drop, hierarchy, admin, facetwp
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manually order taxonomy terms (parents + children) with a fast, accessible, drag-and-drop UI. Uses term meta (no DB schema changes). Plays nice with FacetWP.

== Description ==

Yak Term Order lets editors reorder **siblings** inside any hierarchical taxonomy you enable.  
It stores order as term meta (e.g. `10, 20, 30…`) so it’s simple, portable, and theme-friendly.

**Highlights**
- Inline **drag & drop** on the taxonomy screen (`edit-tags.php`) — no separate manager required.
- **Admin autosort**: list tables & pickers reflect your custom order (safe post-query sort, no SQL hacks).
- **Front-end**: keep your own queries, or honor Yak by reading the saved meta. (FacetWP compatible.)
- **No schema changes**: uninstall anytime; WordPress falls back to its default order.
- **Accessible**: keyboard ▲/▼ moves, live region announcements.
- **Per-branch normalization**: every save rewrites siblings to clean `10/20/30…` increments.

== Installation ==

1. Upload the plugin and **Activate** it.
2. Go to **Settings → Yak Term Order**:
   - Check the taxonomies you want to manage.
   - Turn on **Admin autosort** (recommended).
3. Visit that taxonomy’s screen (e.g. **Posts → Categories** or your CPT taxonomy).
   - Click **Show ordering panel**.
   - Choose a **Parent**, drag items, **Save Order**. Done.

== FAQ ==

= Does this work with FacetWP? =  
Yes. Admin autosort is safe, and the front end can keep FacetWP’s queries. If you want Yak’s order on the front, read the saved term meta (or enable Yak’s front-end autosort if you’ve opted in).

= What happens if I deactivate the plugin? =  
WordPress automatically reverts to its default order. Your saved term meta remains intact for later.

= Will unnumbered terms disappear? =  
No. Admin autosort sorts **after** fetching terms, so nothing is hidden.

= Can I opt out on a specific query? =  
Yes. Pass `ignore_term_order => true` in your `get_terms()` args.

= Does this reorder posts? =  
No. Yak Term Order focuses on **terms**. (A sister plugin can handle posts.)

== Developer Notes ==

**Constants**
- `YTO_META_KEY` — meta key for order (default `_yak_term_order`).
- `YTO_IGNORE_ARG` — set to `true` in `get_terms()` args to skip autosort.
- `YTO_CAP` — capability to manage ordering UI (default `manage_categories`).

**Filters**
- `yak_term_order/allowed_taxonomies` → array of slugs.
- `yak_term_order/autosort_enabled` → (bool) per taxonomy & context.
- `yak_term_order/secondary_orderby` → SQL fragment for tiebreakers.

**Actions**
- `yak_term_order/updated` → fires after saving a branch.

== Changelog ==

= 0.1.1 =
- Inline drag-and-drop on taxonomy screen with AJAX save.
- Admin autosort (post-query) for lists & pickers.
- Term-meta backend, automatic normalization, keyboard a11y.
