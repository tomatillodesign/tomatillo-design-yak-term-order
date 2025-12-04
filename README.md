# Yak Term Order

Drag-and-drop ordering for WordPress taxonomy terms AND posts/custom post types. Safe, accessible, and FacetWP-compatible.

## Features

### Term Ordering
- **Drag-and-drop interface** – Inline ordering on taxonomy screens
- **Hierarchical support** – Order siblings within any parent independently
- **Non-destructive** – Uses term meta, zero database changes
- **Safe sorting** – Post-query PHP sorting, no SQL modifications

### Post Type Ordering (NEW in 1.2)
- **Drag-and-drop on post lists** – Reorder posts directly on admin list screens
- **Autosave** – Order saves automatically after each drag
- **Uses menu_order** – Standard WordPress field, no custom tables
- **FacetWP compatible** – Templates can use your custom order

### General
- **FacetWP integration** – Works with both term facets and post templates
- **Accessible** – Full keyboard support and screen reader announcements

## Installation

1. Activate the plugin
2. Go to **Settings → Yak Term Order**
3. **For terms:** Enable taxonomies and autosort options
4. **For posts:** Enable post types in the "Post Type Ordering" section
5. Visit your taxonomy or post type screen and drag to reorder

## Requirements

- WordPress 6.3+
- PHP 8.0+

## Term Ordering

### How It Works

1. **Storage** – Order stored as integers (10, 20, 30...) in term meta (`_yak_term_order`)
2. **Sorting** – Hooks into `get_terms` filter and sorts results in PHP
3. **Normalization** – Each save rewrites siblings to clean increments
4. **FacetWP** – Hooks into `facetwp_facet_render_args` to sort choices

Terms with explicit order appear first (ascending), followed by unordered terms (alphabetical).

### FacetWP Integration (Terms)

- Enable **Front-end autosort** in plugin settings
- Set your FacetWP facet to **Sort by: Term Order**
- Custom ordering applies automatically

## Post Type Ordering

### How It Works

1. **Storage** – Uses WordPress's built-in `menu_order` field
2. **Admin UI** – Drag handles appear on enabled post type list screens
3. **Autosave** – Order saves immediately after each drag operation
4. **Sorting** – Admin lists automatically sort by menu_order

### FacetWP Integration (Posts)

- Enable the post type in plugin settings
- Optionally enable **Front-end post autosort** for automatic ordering
- FacetWP templates will respect the custom order

### Opt-out of post ordering

```php
$posts = new WP_Query([
    'post_type' => 'resource',
    'ignore_menu_order' => true, // Skip Yak ordering
]);
```

## Developer Hooks

### Filters

```php
// Modify allowed taxonomies
add_filter('yak_term_order/allowed_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});

// Modify allowed post types
add_filter('yak_term_order/allowed_post_types', function($post_types) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
});

// Control autosort by context
add_filter('yak_term_order/autosort_enabled', function($enabled, $taxonomy, $context) {
    return ($context === 'frontend') ? true : $enabled;
}, 10, 3);
```

### Actions

```php
// Hook after term order is saved
add_action('yak_term_order/updated', function($taxonomy, $parent_id, $ordered_ids, $user_id) {
    // Your code here
}, 10, 4);

// Hook after post order is saved
add_action('yak_term_order/post_order_updated', function($post_type, $order, $user_id) {
    // Your code here
}, 10, 3);
```

### Opt-out of term ordering

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

## Credits

**Author:** Chris Liu-Beers, Tomatillo Design  
**License:** GPL-2.0-or-later  
**Version:** 1.2.0

## Support

For issues, feature requests, or contributions, please use the GitHub repository.
