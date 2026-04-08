# Plura WP Plugin

A utility-first WordPress plugin that provides a structured rendering layer for themes. Instead of hardcoding templates, themes call reusable PHP functions that generate consistent, filterable HTML output.

> The source code is always the source of truth. This document covers intent and usage — refer to the implementation for exact signatures and defaults.

---

## Architecture

### Entry-based rendering

Content is assembled as a structured array before being rendered into HTML. This makes it easy to reorder, filter, or replace individual pieces without touching the rendering code.

```php
// The plura_wp_post filter receives this array and can modify it before output
[
    'featured-image' => '<img ...>',
    'title'          => '<h3 ...>',
    'datetime'       => '<time ...>',
    'excerpt'        => '<div ...>',
    'read-more'      => '<a ...>',
]
```

### Filter-first

Everything is designed to be extended via WordPress filters. The core functions apply filters at key points; themes hook into those filters rather than forking core logic.

### Context-aware output

Most rendering functions accept a `$context` string that is passed through to filters, allowing context-specific overrides without conditional logic in core.

### Pure functions

Functions return HTML strings. They never echo.

---

## Post Rendering

### `plura_wp_post()`

Renders a single post as a structured HTML block.

```php
echo plura_wp_post(
    post: $post,          // WP_Post or ID
    link: 0,              // 0 = link inner elements, 1 = wrap block in link, -1 = no links
    read_more: true,      // bool or custom label string
    meta: ['category'],   // optional meta keys (passed to plura_wp_post_meta)
    context: 'archive',
    index: $i             // position in list (available to filters)
);
```

**Filters:**
- `plura_wp_post` — Receives the entry array, `WP_Post`, `$context`, `$index`, and original entry. Return a modified array to change output.
- `plura_wp_post_atts` — Filters the wrapper attributes (`class`, `data-*`).
- `plura_wp_post_title` — Filters the title text before rendering.

### `plura_wp_posts()`

Renders a collection of posts. Runs its own query unless `$posts` is provided.

```php
echo plura_wp_posts(
    type: 'post',
    limit: 6,
    orderby: 'date',
    terms: [12, 34],
    taxonomy: 'category',
    context: 'homepage',
    exclude: [get_the_ID()],
    read_more: 'Read article',
    link: 1
);
```

Pass `output: 'objects'` to return raw `WP_Post[]` instead of HTML.

**Filters:**
- `plura_wp_posts_atts` — Filters the wrapper element attributes.

### `plura_wp_posts_query()`

Builds and returns a `WP_Query` with support for taxonomy filtering, timeline filtering, exclusion, and ordering.

```php
$query = plura_wp_posts_query(
    type: 'event',
    timeline: 1,              // 1 = in progress, 0 = past, -1 = future
    timeline_start_key: 'start_date',
    timeline_end_key: 'end_date',
    limit: 10
);
```

**Filters:**
- `plura_wp_posts_query` — Filters the raw `WP_Query` args array before the query runs.

---

## Titles

### `plura_wp_title()`

Renders a title for a post or term. Works on both `WP_Post` and `WP_Term`.

```php
echo plura_wp_title(object: $post, tag: 'h2', link: true, context: 'banner');
```

**Filters:**
- `plura_wp_title` — Filters the title text.

### `plura_wp_post_title()`

Post-specific variant. Accepts `WP_Post` or ID.

---

## Media

### `plura_wp_image()`

Renders a responsive `<img>` tag for an attachment.

```php
echo plura_wp_image(
    attachment: 42,
    size: 'large',
    atts: ['class' => 'hero-image'],
    loading: 'lazy'   // or false to disable
);
```

### `plura_wp_image_data()`

Returns image metadata as an array (`src`, `width`, `height`, `alt`, `srcset`, `sizes`) without rendering HTML.

### `plura_wp_gallery()`

Renders a gallery from explicit attachment IDs and/or a post's ACF/meta field.

```php
echo plura_wp_gallery(
    ids: [10, 11, 12],
    source: get_the_ID(),
    source_key: 'gallery_field',
    source_featured_image: true,
    class: 'project-gallery',
    context: 'single'
);
```

**Filters:**
- `plura_wp_gallery` — Filters the resolved array of image IDs before rendering.

### `plura_wp_thumbnail()`

Returns raw thumbnail data (`[url, width, height, is_intermediate]`) for a post. Returns `false` if no thumbnail.

### `plura_wp_post_featured_image()`

Renders the featured image of a post as an `<img>` tag.

---

## Links

### `plura_wp_link()`

Wraps HTML in an `<a>` tag pointing to a post, term, or external URL. Automatically adds `target="_blank"` for external links (including subdirectory installs).

```php
echo plura_wp_link(
    html: '<img src="...">',
    target: $post,       // WP_Post, WP_Term, or URL string
    atts: ['class' => 'card-link'],
    rel: true            // adds rel="noopener noreferrer" when blank
);
```

---

## Datetime

### `plura_wp_datetime()`

Renders a `<time>` element with a formatted date and ISO 8601 `datetime` attribute. Supports relative time output.

```php
echo plura_wp_datetime(
    date: $post->post_date,
    format: 'F j, Y',
    relative: false,
    class: 'post-date'
);
```

**Filters:**
- `plura_datetime_suffix_past` — Suffix for past relative times (default: "ago").
- `plura_datetime_suffix_future` — Suffix for future relative times (default: "from now").

---

## Breadcrumbs

### `plura_wp_breadcrumbs()`

Renders breadcrumbs for a post or term. Falls back to the current queried object if none is given.

```php
echo plura_wp_breadcrumbs(
    object: $post,  // WP_Post, WP_Term, int, or null
    self: false,    // whether to include the object itself
    context: 'single'
);
```

**Filters:**
- `plura_wp_breadcrumbs` — Filters the array of breadcrumb groups before rendering.

### `plura_wp_breadcrumbs_nav()` / `plura_wp_prevnext_nav()`

Navigation-menu-based breadcrumbs and prev/next navigation. These traverse a WordPress block navigation by title and render adjacent items.

```php
echo plura_wp_prevnext_nav(menu: 'Main Navigation', breadcrumbs: true);
echo plura_wp_breadcrumbs_nav(menu: 'Main Navigation');
```

---

## Dynamic Grid

### `plura_wp_dynamic_grid()`

Renders a filterable grid of posts with a taxonomy-based filter UI. Connected to a REST endpoint for client-side re-filtering without page reload.

```php
echo plura_wp_dynamic_grid(
    post_type: 'project',
    taxonomy: 'project_category',
    filter: true,
    filter_type: 'tag',   // or 'select'
    filter_group: true,
    filter_group_acf_field_key: 'field_abc123',
    term_meta_key: 'group_key'
);
```

**Filters:**
- `plura_wp_dynamic_grid_items_params` — Filters the args passed to `plura_wp_posts()` when rendering grid items.

---

## Components

### `plura_wp_component()`

Renders a manifest-based component from a JSON file. The manifest declares an HTML file and optional scripts to enqueue.

```php
echo plura_wp_component(
    manifest: get_stylesheet_directory() . '/components/hero/manifest.json',
    id: 'hero',
    img2svg: true,
    context: 'homepage'
);
```

**Manifest format:**
```json
{
    "html": "index.html",
    "scripts": {
        "hero.js": { "deps": ["jquery"] }
    }
}
```

Shortcodes in the HTML file are processed. Local `<img src="*.svg">` tags are inlined if `img2svg` is enabled. Relative asset paths are resolved to absolute URLs.

**Filters:**
- `plura_wp_component_manifest` — Override the manifest path.
- `plura_wp_component_manifest_data` — Override the manifest data array.

---

## Asset Management

### `plura_wp_enqueue()`

Enqueues multiple CSS and JS files in a single call. Supports absolute paths, URLs, and `%s` patterns that resolve to both `css` and `js` variants.

```php
plura_wp_enqueue(
    scripts: [
        __DIR__ . '/assets/main.css',
        __DIR__ . '/assets/main.js',
        __DIR__ . '/assets/%s/theme.%s',  // resolves to theme.css + theme.js
        'https://cdn.example.com/lib.js' => ['deps' => ['jquery']],
    ],
    prefix: 'mytheme-',
    cache: true
);
```

---

## REST API

| Endpoint | Method | Description |
|---|---|---|
| `/pwp/v1/ids?ids=1,2,3` | GET | Returns `{ [id]: { id, title, url } }` for a comma-separated list of post IDs |
| `/plura/v1/dynamic-grid/?terms=12,34&taxonomy=category&post_type=post&filter_cond=AND` | GET | Returns post IDs matching the given term filters (used by the dynamic grid) |

Both endpoints are public (`permission_callback: '__return_true'`).

---

## Shortcodes

| Shortcode | PHP Function |
|---|---|
| `[plura-wp-post]` | `plura_wp_post()` |
| `[plura-wp-posts]` | `plura_wp_posts()` |
| `[plura-wp-posts-related]` | `plura_wp_posts()` scoped to related content |
| `[plura-wp-post-title]` | `plura_wp_post_title()` |
| `[plura-wp-post-featured-image]` | `plura_wp_post_featured_image()` |
| `[plura-wp-post-timeline-datetime]` | `plura_wp_post_timeline_datetime()` |
| `[plura-wp-title]` | `plura_wp_title()` — works on posts and terms |
| `[plura-wp-image]` | `plura_wp_image()` |
| `[plura-wp-gallery]` | `plura_wp_gallery()` |
| `[plura-wp-datetime]` | `plura_wp_datetime()` |
| `[plura-wp-breadcrumbs]` | `plura_wp_breadcrumbs()` |
| `[plura-wp-breadcrumbs-nav]` | `plura_wp_breadcrumbs_nav()` |
| `[plura-wp-prevnext-nav]` | `plura_wp_prevnext_nav()` |
| `[plura-wp-nav-list]` | Renders a nav menu as both `<ul>` list and `<select>` dropdown |
| `[plura-wp-dynamic-grid]` | `plura_wp_dynamic_grid()` |
| `[plura-wp-component]` | `plura_wp_component()` |
| `[plura-wp-component-banner]` | Renders the built-in banner component |
| `[plura-p-tags]` | Post tags |
| `[plura-p-date-archive]` | Date-based archive links |
| `[p-restricted-area]` | Login-gated content area |

---

## Integrations

### CF7 → Google Sheets

Forward Contact Form 7 submissions to a Google Apps Script endpoint.

```php
plura_cf7_to_sheets(
    endpoint: 'AKfycbw...',   // script ID or full exec URL
    forms: [
        [
            'id'        => '101',
            'whitelist' => ['your-name', 'your-email', 'your-message'],
        ],
        [
            'title'     => 'Contact Form',
            'blacklist' => ['_wpcf7', '_wpnonce'],
            'endpoint'  => 'AKfycbx...'   // per-form override
        ],
    ]
);
```

Form matching accepts numeric ID, 7-char shortcode hash, or form title. In whitelist mode, only the listed fields are forwarded (in that order). Without a whitelist, all user fields except CF7 internals and blacklisted keys are forwarded.

### WPML

`plura_wpml()` — returns true if WPML is active.
`plura_wpml_lang()` — current language code.
`plura_wpml_id($id)` — returns the WPML object ID for the current language.
`plura_wpml_query($args)` — wraps `WP_Query` args with WPML language filtering.

### Lottie

Adds support for inline Lottie animations via a shortcode.

### Revolution Slider / Essential Grid

`[plura-wp-revslider]` shortcode for rendering an Essential Grid filtered by WPML-aware post IDs.

---

## Utilities

| Function | Description |
|---|---|
| `plura_attributes(array $atts)` | Converts an attribute array to an HTML attribute string. Handles boolean attributes and class arrays. |
| `plura_wp_enqueue_asset()` | Enqueues a single CSS or JS file with optional deps, handle, and version. |
| `plura_img2svg(string $html)` | Replaces local `<img src="*.svg">` tags with inline SVG markup. |
| `plura_rel2url(string $html, string $base_url)` | Converts relative paths to absolute URLs in `<img>`, `<script>`, `<link>`, and `<source>` tags. |
| `plura_curl(string $url, array $args)` | HTTP POST via cURL with optional JSON body encoding. |
| `plura_explode(string $sep, string $str)` | `explode()` with automatic trimming of each element. |
| `plura_bool($value)` | Checks whether a value is a boolean-like string (`'true'`, `'1'`, `'false'`, `'0'`, etc.). |
