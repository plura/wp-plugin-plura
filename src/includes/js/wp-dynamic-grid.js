/**
 * PluraWPDynamicGrid
 * A dynamic responsive grid system with tag/select filtering.
 *
 * @param {Object} options
 * @param {Array<Object>} [options.breakpoints] - Optional breakpoints: [{min, max, cols}]
 * @param {string} [options.cond='AND'] - Tag condition: 'AND' | 'OR'
 * @param {HTMLElement} options.target - The container element
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

	/**
	 * Default grid breakpoints — fallback logic like:
	 * 
	 * if (w >= 1600) n = 6;
	 * else if (w >= 1366) n = 5;
	 * ...
	 */
	const COLS_BREAKPOINTS = [
		{ min: 1600, cols: 6 },
		{ min: 1366, max: 1600, cols: 5 },
		{ min: 991, max: 1366, cols: 4 },
		{ min: 768, max: 991, cols: 3 },
		{ max: 768, cols: 2 } // fallback
	];

	const ui_filter_groups = target.querySelectorAll('.plura-wp-dynamic-grid-filter-group');
	const ui_grid = target.querySelector('.plura-wp-dynamic-grid-items');

	/**
	 * Collects filter values and makes request to REST API endpoint.
	 * Toggles grid visibility based on tag matching and condition.
	 */
	const activate = () => {
		const clss = 'filtered';
		const terms = [];
		const url = new URL(`${plura_wp_data.restURL}plura/v1/dynamic-grid/`);

		url.searchParams.set('post_type', post_type);

		ui_filter_groups.forEach(group => {
			if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT) {
				group.value.match(/^[0-9]+$/) && terms.push(group.value);
			} else if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG) {
				group.querySelectorAll('.plura-wp-dynamic-grid-filter-item').forEach(el =>
					el.classList.contains('on') &&
					el.dataset.id.match(/^[0-9]+$/) &&
					terms.push(el.dataset.id)
				);
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

		console.log('[PluraWPDynamicGrid] Fetching:', url.toString());

		fetch(url)
			.then(res => res.ok ? res.json() : Promise.reject(res))
			.then(data => refresh(data))
			.catch(err => console.error('[PluraWPDynamicGrid] Fetch error:', err));
	};


	/**
	 * Toggles 'on' class for clicked tag and triggers filtering.
	 * 
	 * @param {HTMLElement} element - The tag element clicked
	 */
	const activateTag = element => {
		element.classList.toggle('on');
		activate();
	};

	/**
	 * Sets the number of columns in the grid based on current window width.
	 * Also applies `--grid-w` and `--grid-cols` as CSS variables to the container.
	 */
	const set_grid_cols = () => {
		let w = window.innerWidth;
		let b = breakpoints || COLS_BREAKPOINTS;

		let n = b.find(bp => w >= (bp.min || 0) && w < (bp.max || Infinity))?.cols;
		grid_cols = n || 2;

		console.log('[PluraWPDynamicGrid] Window:', w, 'Cols:', grid_cols);

		// Set width and column count as CSS variables
		Object.entries({
			w: `${ui_grid.offsetWidth}px`,
			cols: grid_cols
		}).forEach(([key, value]) =>
			ui_grid.style.setProperty(`--grid-${key}`, value)
		);

		refresh();
	};

	/**
	 * Refreshes layout:
	 * - Sets `--x` and `--y` for each item based on its new position
	 * - Applies `.on` class only to active (visible) items
	 * - Calculates number of rows and sets `--grid-rows` to adjust container height
	 * 
	 * @param {Array<number>} [data] - Optional array of IDs for filtered items
	 */
	const refresh = (data) => {
		if (data) active = data;

		const ui_grid_items = ui_grid.querySelectorAll('.plura-wp-post');

		// Get number of rows needed (ensures holder gets proper height since items are absolutely positioned)
		const rows = (!active || active.length > 0)
			? Math.floor((!active ? ui_grid_items.length : active.length) / grid_cols) + 1
			: 0;

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
		ui_filter_groups.forEach(group => {
			if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT) {
				group.addEventListener('change', () => activate());
			} else if (group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG) {
				group.querySelectorAll('.plura-wp-dynamic-grid-filter-item').forEach(el =>
					el.addEventListener('click', () => activateTag(el))
				);
			}
		});
	}

	resizeObserver.observe(target);
}
