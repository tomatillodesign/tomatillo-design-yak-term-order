# Yak Term Order

Custom drag-and-drop ordering for WordPress taxonomy terms. Safe, accessible, and FacetWP-compatible.

## Features

- **Drag-and-drop interface** – Inline ordering on taxonomy screens
- **Hierarchical support** – Order siblings within any parent independently
- **FacetWP integration** – Custom order works with FacetWP facets
- **Non-destructive** – Uses term meta, zero database changes
- **Safe sorting** – Post-query PHP sorting, no SQL modifications
- **Accessible** – Full keyboard support and screen reader announcements

## Installation

1. Activate the plugin
2. Go to **Settings → Yak Term Order**
3. Enable taxonomies and autosort options
4. Visit your taxonomy screen and click **Show ordering panel**

## Requirements

- WordPress 6.3+
- PHP 8.0+

## FacetWP Integration

When using with FacetWP:
- Enable **Front-end autosort** in plugin settings
- Set your FacetWP facet to **Sort by: Term Order**
- Custom ordering applies automatically
- Other sort options (count, alphabetical) work normally

## Developer Hooks

### Filters

```php
// Modify allowed taxonomies
add_filter('yak_term_order/allowed_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});

// Control autosort by context
add_filter('yak_term_order/autosort_enabled', function($enabled, $taxonomy, $context) {
    return ($context === 'frontend') ? true : $enabled;
}, 10, 3);
```

### Actions

```php
// Hook after order is saved
add_action('yak_term_order/updated', function($taxonomy, $parent_id, $ordered_ids, $user_id) {
    // Your code here
}, 10, 4);
```

### Opt-out of ordering

```php
$terms = get_terms([
    'taxonomy' => 'category',
    'ignore_term_order' => true, // Skip Yak ordering
]);
```

## Debug Mode

Enable detailed logging:

```php
// In wp-config.php or your plugin
define('YTO_DEBUG', true);
```

Logs appear in `wp-content/debug.log` with `YTO:` prefix.

## How It Works

1. **Storage** – Order stored as integers (10, 20, 30...) in term meta (`_yak_term_order`)
2. **Sorting** – Hooks into `get_terms` filter and sorts results in PHP
3. **Normalization** – Each save rewrites siblings to clean increments
4. **FacetWP** – Hooks into `facetwp_facet_render_args` to sort choices

Terms with explicit order appear first (ascending), followed by unordered terms (alphabetical).

## Credits

**Author:** Chris Liu-Beers, Tomatillo Design  
**License:** GPL-2.0-or-later  
**Version:** 1.0.0

## Support

For issues, feature requests, or contributions, please use the GitHub repository.

