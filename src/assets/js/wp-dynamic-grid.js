/**
 * Dynamic grid (wp-dynamic-grid.php): lays a grid's posts out in columns and filters them by term,
 * fetching the matching post IDs from /plura/v1/dynamic-grid. Start it with
 * PluraWPDynamicGrid({ target }) on each .plura-wp-dynamic-grid; the styles are in wp-dynamic-grid.css.
 */

/**
 * Lays out and filters one grid, reading its post type, taxonomy and AND/OR condition from the
 * wrapper's data attributes.
 *
 * Posts are absolutely positioned from --x and --y, so they animate into place when filtered. Needs
 * plura_wp_data, localized onto the plura-p script, for the REST URL.
 *
 * @param {Object}                                              options
 * @param {HTMLElement}                                         options.target        The .plura-wp-dynamic-grid element.
 * @param {Array<{min?: number, max?: number, cols: number}>} [options.breakpoints] Columns per window width, in pixels.
 *                                                                                    Default 6 from 1600 down to 2 below 768.
 * @returns {void}
 */
function PluraWPDynamicGrid({ breakpoints, target }) {
	const filter_cond = target.dataset.filterCond;
	const taxonomy = target.dataset.taxonomy;
	const post_type = target.dataset.postType;

	delete target.dataset.filterCond;
	delete target.dataset.taxonomy;
	delete target.dataset.postType;

	let active, grid_cols;

	const FILTER_DATA_FILTER_TYPE_SELECT = 'select';
	const FILTER_DATA_FILTER_TYPE_TAG = 'tag';

	// Columns per window width, unless options.breakpoints is given
	const COLS_BREAKPOINTS = [
		{ min: 1600, cols: 6 },
		{ min: 1366, max: 1600, cols: 5 },
		{ min: 991, max: 1366, cols: 4 },
		{ min: 768, max: 991, cols: 3 },
		{ max: 768, cols: 2 }, // fallback
	];

	const ui_filter_groups = target.querySelectorAll('.plura-wp-dynamic-grid-filter-group');
	const ui_grid = target.querySelector('.plura-wp-dynamic-grid-items');

	/**
	 * Collects the selected terms and fetches the matching post IDs; with none selected, all posts show.
	 *
	 * @returns {void}
	 */
	const activate = () => {
		const clss = 'filtered';
		const terms = [];
		const url = new URL(`${plura_wp_data.restURL}plura/v1/dynamic-grid/`);

		url.searchParams.set('post_type', post_type);

		ui_filter_groups.forEach((group) => {
			if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT) {
				group.value.match(/^[0-9]+$/) && terms.push(group.value);
			} else if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG) {
				group
					.querySelectorAll('.plura-wp-dynamic-grid-filter-item')
					.forEach((el) => el.classList.contains('on') && el.dataset.id.match(/^[0-9]+$/) && terms.push(el.dataset.id));
			}
		});

		if (terms.length) {
			url.searchParams.set('terms', terms.join(','));
			url.searchParams.set('filter_cond', filter_cond);
			url.searchParams.set('taxonomy', taxonomy);

			target.classList.add(clss);
		} else {
			target.classList.remove(clss);
		}

		fetch(url)
			.then((res) => (res.ok ? res.json() : Promise.reject(res)))
			.then((data) => refresh(data))
			.catch((err) => console.error('[PluraWPDynamicGrid] Fetch error:', err));
	};

	/**
	 * Toggles a clicked tag filter and refilters.
	 *
	 * @param {HTMLElement} element The tag clicked.
	 * @returns {void}
	 */
	const activateTag = (element) => {
		element.classList.toggle('on');
		activate();
	};

	/**
	 * Picks the column count for the window width, stores it and the grid width in --grid-cols and
	 * --grid-w, and re-lays out the posts.
	 *
	 * @returns {void}
	 */
	const set_grid_cols = () => {
		let w = window.innerWidth;
		let b = breakpoints || COLS_BREAKPOINTS;

		let n = b.find((bp) => w >= (bp.min || 0) && w < (bp.max || Infinity))?.cols;
		grid_cols = n || 2;

		// Set width and column count as CSS variables
		Object.entries({
			w: `${ui_grid.offsetWidth}px`,
			cols: grid_cols,
		}).forEach(([key, value]) => ui_grid.style.setProperty(`--grid-${key}`, value));

		refresh();
	};

	/**
	 * Positions the visible posts (--x, --y), marks them .on, and sets --grid-rows, which gives the
	 * absolutely positioned grid its height.
	 *
	 * @param {number[]} [data] IDs of the posts to show, from the REST response. Default the last set,
	 *                          or all posts before any filtering.
	 * @returns {void}
	 */
	const refresh = (data) => {
		if (data) active = data;

		const ui_grid_items = ui_grid.querySelectorAll('.plura-wp-post');

		// Get number of rows needed (ensures holder gets proper height since items are absolutely positioned)
		const count = !active ? ui_grid_items.length : active.length;
		const rows = count > 0 ? Math.ceil(count / grid_cols) : 0;

		ui_grid_items.forEach((item, index) => {
			const id = Number(item.dataset.id);

			if (!active || active.includes(id)) {
				// Calculate item's grid position
				const n = !active ? index : active.indexOf(id);
				const x = n % grid_cols;
				const y = Math.floor(n / grid_cols);

				// Set item position using CSS variables
				item.style.setProperty('--x', x);
				item.style.setProperty('--y', y);

				item.classList.add('on');
			} else {
				item.classList.remove('on');
			}
		});

		ui_grid.style.setProperty('--grid-rows', rows);
	};

	// Resize observer triggers layout refresh on container resize
	const resizeObserver = new ResizeObserver(() => set_grid_cols());

	// Activate base classes
	target.classList.add('active', 'p-dynamic-grid');

	// Bind filter listeners
	if (ui_filter_groups.length) {
		ui_filter_groups.forEach((group) => {
			if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT) {
				group.addEventListener('change', () => activate());
			} else if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG) {
				group
					.querySelectorAll('.plura-wp-dynamic-grid-filter-item')
					.forEach((el) => el.addEventListener('click', () => activateTag(el)));
			}
		});
	}

	resizeObserver.observe(target);
}
