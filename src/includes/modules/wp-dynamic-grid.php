<?php

/**
 * Summary of Filtering Grid Logic
 * 
 * This module powers a filterable grid of posts/items.
 * 
 * Core Flow:
 * - A filter UI is rendered above the grid.
 * - Each filter term (e.g., taxonomy term) acts as a toggle.
 * - When filters are interacted with (click, change), a fetch/AJAX request is triggered.
 * - The request sends the IDs of all selected filters to the backend.
 * - The backend returns posts matching all selected filters (AND logic).
 * 
 * Grouped Filters:
 * - Filter terms can be visually grouped into logical categories.
 * - Groups can be rendered as:
 *   - Clickable tag groups, or
 *   - <select> dropdowns.
 * 
 * Grouping Logic:
 * - Terms can include group information via custom term meta.
 * - This meta can be set manually, or managed through ACF (optional).
 * - If ACF is used, the field key (not meta key) may be passed to fetch group labels and order.
 * 
 * Event Bindings:
 * - All interactive filters are tied to event listeners (click, change).
 * - On interaction, the currently active filters are collected and used in the request.
 */


add_action('rest_api_init', function() {

	/**
	 * Register REST endpoint for dynamic grid post IDs
	 * 
	 * Example usage:
	 * /wp-json/plura/v1/dynamic-grid/?terms=12,34,56&filter_cond=OR&taxonomy=cp_news_tag&post_type=cp_news
	 * 
	 * - `filter_cond` can be "AND" or "OR"
     * - `terms` must be a comma-separated list of term IDs (integers)
	 * - `taxonomy` is the taxonomy to filter by (e.g., 'category', 'cp_news_tag')
	 * - `post_type` is the post type (e.g., 'post', 'cp_news')
	 */
	register_rest_route('plura/v1', '/dynamic-grid/', [
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'plura_wp_dynamic_grid_ids',
		'args'     => [
			'terms' => [
				'required' => false,
				'validate_callback' => function($param, $request, $key) {
					return is_string($param) && preg_match('/^(\d+,?)+$/', $param);
				},
				'sanitize_callback' => 'sanitize_text_field',
				'description' => __('Comma-separated list of term IDs', 'plura')
			],
			'filter_cond' => [
				'required' => false,
				'validate_callback' => function($param, $request, $key) {
					return in_array(strtoupper($param), ['AND', 'OR']);
				},
				'sanitize_callback' => 'sanitize_key',
				'default' => 'AND',
				'description' => __('Term query condition (AND/OR)', 'plura')
			],
			'taxonomy' => [
				'required' => false,
				'validate_callback' => function($param, $request, $key) {
					return is_string($param);
				},
				'sanitize_callback' => 'sanitize_key',
				'default' => 'category',
				'description' => __('Taxonomy used for term filtering', 'plura')
			],
			'post_type' => [
				'required' => false,
				'validate_callback' => function($param, $request, $key) {
					return is_string($param);
				},
				'sanitize_callback' => 'sanitize_key',
				'default' => 'post',
				'description' => __('Post type to query', 'plura')
			]
		],
		'permission_callback' => '__return_true'
	]);
});



/**
 * Retrieves post IDs for dynamic grid based on term IDs, taxonomy, post type, and condition (AND/OR).
 *
 * @param WP_REST_Request|null $request Optional REST request object
 * @return array<int> Array of post IDs
 */
function plura_wp_dynamic_grid_ids(?WP_REST_Request $request = null): array {
    // Set defaults
    $terms = [];
    $filter_cond = 'AND';
    $taxonomy = 'category';
    $post_type = 'post';

    if ($request) {
        // Extract and sanitize terms (term IDs)
        $terms_param = $request->get_param('terms');
        if ($terms_param) {
            $terms = array_filter(
                array_map('intval', explode(',', sanitize_text_field($terms_param)))
            );
        }

        // Extract filter_cond
        $filter_cond_param = strtoupper($request->get_param('filter_cond'));
        if (in_array($filter_cond_param, ['AND', 'OR'])) {
            $filter_cond = $filter_cond_param;
        }

        // Extract taxonomy
        $taxonomy_param = $request->get_param('taxonomy');
        if (!empty($taxonomy_param)) {
            $taxonomy = sanitize_key($taxonomy_param);
        }

        // Extract post type
        $post_type_param = $request->get_param('post_type');
        if (!empty($post_type_param)) {
            $post_type = sanitize_key($post_type_param);
        }
    }

    // Run the actual WP_Query
    $query = plura_wp_dynamic_grid_query(terms: $terms, taxonomy: $taxonomy, post_type: $post_type, filter_cond: $filter_cond);

    if (!$query->have_posts()) {
        return [];
    }

    // Return list of post IDs
    return array_map(
        fn($post) => (int) $post->ID,
        $query->posts
    );
}



/**
 * Builds and returns a WP_Query for posts matching the given term filters.
 *
 * @param array $terms Array of term IDs to filter by.
 * @param string $taxonomy Taxonomy to apply filtering on.
 * @param string $post_type Post type to query.
 * @param string $filter_cond Filtering condition: 'AND' or 'OR'.
 * @return WP_Query Query object ready for use.
 */

function plura_wp_dynamic_grid_query( 
    array $terms = [],
    string $taxonomy = 'category',
    string $post_type = 'post',
    string $filter_cond = 'AND'
): WP_Query {
    $query_atts = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'no_found_rows' => true, // Always safe for performance
        'update_post_term_cache' => empty($terms) // Only cache terms if not filtering by them
    ];

    if (!empty($terms)) {
        $valid_terms = array_filter($terms, 'is_numeric');
        
        if (!empty($valid_terms)) {
            $query_atts['tax_query'] = [
                array_map(
                    fn($term) => [
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => (int)$term
                    ],
                    $valid_terms
                )
            ];

            if (count($valid_terms) > 1) {
                $query_atts['tax_query']['relation'] = 
                    in_array(strtoupper($filter_cond), ['AND', 'OR']) 
                        ? strtoupper($filter_cond) 
                        : 'AND';
            }
        }
    }

    return plura_wpml_query($query_atts);
}



/**
 * Renders the full dynamic grid component, including filters and post items.
 *
 * @param array|string $class                     Additional CSS class(es) for the wrapper div.
 * @param string|null  $context                   Optional rendering context (e.g., for use in filters).
 * @param bool         $filter                    Whether to display the filter UI.
 * @param string       $filter_cond               Logical condition used for filtering: 'AND' or 'OR'.
 * @param bool         $filter_group              Whether to group filters by ACF-defined groupings.
 * @param string|null  $filter_group_acf_field_key ACF field key used to define filter groups.
 * @param string       $filter_type               Filter display type: 'tag' or 'select'.
 * @param string       $post_type                 Post type to query.
 * @param string       $taxonomy                  Taxonomy used for filtering terms.
 * @param string|null  $term_meta_key             Term meta key used to group terms.
 * @return string                                 HTML output of the grid and filters.
 */
function plura_wp_dynamic_grid(
    array|string $class = '',
    ?string $context = null,
    bool $filter = true,
    string $filter_cond = 'AND',
    bool $filter_group = false,
    ?string $filter_group_acf_field_key = null,
    string $filter_type = 'tag',
    string $post_type = 'post',
    string $taxonomy = 'category',
    ?string $term_meta_key = null
): string {

    $html = [];

    if ($filter) {
        $html[] = plura_wp_dynamic_grid_filter(
            group: $filter_group,
            group_acf_field_key: $filter_group_acf_field_key,
            taxonomy: $taxonomy,
            term_meta_key: $term_meta_key,
            type: $filter_type
        );
    }

    $html[] = plura_wp_dynamic_grid_items(context: $context, post_type: $post_type);

    $attrs = [
        'class' => ['plura-wp-dynamic-grid'],
        'data-filter-cond' => $filter_cond,
        'data-filter-type' => $filter_type,
        'data-post-type' => $post_type,        
        'data-taxonomy' => $taxonomy
    ];

    if (!empty($class)) {
        $attrs['class'] = array_merge($attrs['class'], (array) $class);
    }

    return sprintf(
        '<div %s>%s</div>',
        plura_attributes($attrs),
        implode('', $html) // This ensures the HTML elements are properly combined.
    );
}

/**
 * Registers the [plura-wp-dynamic-grid] shortcode to render a dynamic grid with optional filters.
 *
 * Attributes:
 * - class (string): Additional CSS classes for the grid container.
 * - filter (bool): Whether to display filters. Default: true.
 * - filter_group (bool): Whether to group filters by an ACF field. Default: false.
 * - filter_group_acf_field_key (?string): The ACF field key used for grouping filters.
 * - filter_type (string): The filter type, e.g., 'tag' or 'select'. Default: 'tag'.
 * - post_type (string): The post type to query. Default: 'post'.
 * - taxonomy (string): The taxonomy used to build the filters. Default: 'category'.
 *
 * Example:
 * [plura-wp-dynamic-grid 
 *     filter_group="1" 
 *     filter_group_acf_field_key="field_63dd39b0dfb66" 
 *     post_type="toyno_work" 
 *     taxonomy="toyno_works_tag" 
 *     term_meta_key="toyno_work_tags_group"
 * ]
 */

add_shortcode('plura-wp-dynamic-grid', function($atts) {

    $args = shortcode_atts([
        'class' => '',
        'context' => null,
        'filter' => true,
        'filter_cond' => 'AND',
        'filter_group' => false,
        'filter_group_acf_field_key' => null,
        'filter_type' => 'tag',
        'post_type' => 'post',
        'taxonomy' => 'category'
    ], $atts);

    // Type casting
    $args['filter'] = (bool) $args['filter']; // Correcting the variable here
    $args['filter_group'] = (bool) $args['filter_group'];

    return plura_wp_dynamic_grid(...$args);

});


/**
 * Generates HTML filter controls for dynamic grid
 *
 * @param bool $group The filter group identifier
 * @param string $type The filter type/style to render
 * @return string HTML markup for the filter controls
 */
function plura_wp_dynamic_grid_filter(
    bool $group = false,
    ?string $group_acf_field_key = null,
    ?string $taxonomy = null,
    ?string $term_meta_key = null,
    string $type = 'select'
): string {
    $data = plura_wp_dynamic_grid_filter_data(
        taxonomy: $taxonomy,
        group: $group,
        group_acf_field_key: $group_acf_field_key,
        term_meta_key: $term_meta_key
    );

    if (empty($data)) {
        return '';
    }

    $html = array_map(
        fn($group_item) => plura_wp_dynamic_grid_filter_obj( data: $group_item, type: $type),
        $data
    );

    return sprintf(
        '<div %s>%s</div>',
        plura_attributes(['class' => ['plura-wp-dynamic-grid-filter']]),
        implode('', $html)
    );
}



/**
 * Generates HTML for a filter group object (select options or div elements)
 *
 * @param array<array{id:int|string,name:string}> $data Array of term data
 * @param string $type Filter type ('select' or other)
 * @return string HTML markup for the filter group
 */
function plura_wp_dynamic_grid_filter_obj(array $data, string $type = 'select'): string {

    if (!in_array($type, ['select', 'tag'], true)) {
        throw new InvalidArgumentException("Invalid type: $type");
    }

    $html = [];

    foreach ($data as $term) {
        $item_attrs = [
            'class' => 'plura-wp-dynamic-grid-filter-item',
            $type === 'select' ? 'value' : 'data-id' => $term['id']
        ];

        $html[] = sprintf(
            $type === 'select' ? '<option %s>%s</option>' : '<div %s>%s</div>',
            plura_attributes($item_attrs),
            htmlspecialchars($term['name'], ENT_QUOTES)
        );
    }

    $group_attrs = [
        'class' => 'plura-wp-dynamic-grid-filter-group',
        'data-filter-type' => $type
    ];

    $tag = $type === 'select' ? 'select' : 'div';

    return sprintf(
        '<%s %s>%s</%s>',
        $tag,
        plura_attributes($group_attrs),
        implode('', $html),
        $tag
    );
}




/**
 * Get filtered taxonomy terms, optionally grouped by an ACF radio field.
 *
 * @param string $taxonomy Taxonomy to fetch terms from.
 * @param bool $group Whether to group terms by a custom field.
 * @param string|null $group_acf_field_key ACF field key for the radio group.
 * @param string|null $term_meta_key Term meta key used to group terms.
 * @return array<int|string, mixed> Array of terms or grouped terms
 */
function plura_wp_dynamic_grid_filter_data(
    string $taxonomy,
    bool $group = false,
    ?string $group_acf_field_key = null,
    ?string $term_meta_key = null
): array {
    // Early return if missing required parameters
    if (!$taxonomy || ($group && (!$group_acf_field_key || !$term_meta_key))) {
        return [];
    }

    $base_query = [
        'taxonomy' => $taxonomy,
        'hide_empty' => true
    ];

    // Simple case: non-grouped terms
    if (!$group) {
        $terms = plura_wp_dynamic_grid_filter_data_items($base_query);
        return [ $terms ];
    }

    // Grouped terms case
    $field_object = acf_get_field($group_acf_field_key);
    if (empty($field_object['choices'])) {
        return [];
    }

    $grouped_data = [];
    foreach ($field_object['choices'] as $group_key => $group_label) {
        $group_query = $base_query + [
            'meta_query' => [[
                'key' => $term_meta_key,
                'value' => $group_key,
                'compare' => '='
            ]]
        ];

        $terms = plura_wp_dynamic_grid_filter_data_items($group_query);
        if (!empty($terms)) {
          /*   $grouped_data[$group_key] = [
                'group' => sanitize_text_field($group_label),
                'terms' => $terms
            ]; */
            $grouped_data[] = $terms;

        }
    }

    return $grouped_data;
}



/**
 * Retrieves unique term data for dynamic grid filters
 * 
 * @param array $query_args Arguments for WP_Term_Query
 * @return array<int, array{id: int, name: string}> Array of term data with term IDs as keys
 */
function plura_wp_dynamic_grid_filter_data_items(array $query_args): array {
    $terms = plura_wpml_query($query_args, 'terms')->get_terms();
    
    if (empty($terms)) {
        return [];
    }

    $items = [];
    
    foreach ($terms as $term) {
        $term_id = (int)$term->term_id;
        $items[$term_id] = [
            'id' => $term_id,
            'name' => sanitize_text_field($term->name)
        ];
    }

    return $items;
}



/**
 * Generates the HTML markup for dynamic grid items using plura_wp_posts().
 *
 * @param string|null $context   Optional context passed to `plura_wp_posts`.
 * @param string       $post_type The post type to fetch and render.
 * @return string                 HTML markup for the rendered grid items.
 *
 * @see plura_wp_posts()
 * @filter plura_wp_dynamic_grid_items_params Allows overriding the args passed to plura_wp_posts.
 */
function plura_wp_dynamic_grid_items(?string $context = null, string $post_type = 'post'): string {

	$query = plura_wp_dynamic_grid_query(post_type: $post_type);

	if ( ! $query->have_posts() ) {
		return '';
	}

	// Define only the relevant args for plura_wp_posts in this context
	$args_defaults = [
		'class' => 'plura-wp-dynamic-grid-items',
		'context' => 'plura-wp-dynamic-grid',
		'data' => [],
		'datetime_format' => 'l, F jS, Y g:i A',
		'link' => 0,
		'read_more' => 1,
		'posts' => $query->posts,
		'type' => $post_type,
	];

	// Allow customization via filter
	$args = apply_filters('plura_wp_dynamic_grid_items_params', $args_defaults, $context);

	return plura_wp_posts(...$args);
}

