/**
 * Sticky effect: keeps an element fixed on screen until a boundary element scrolls into its way.
 * Start it with plura_fx_sticky({ target, bottom }); the styles are in wp-globals.css.
 */

/**
 * Fixes the target to the viewport and tracks the boundaries on scroll and resize.
 *
 * With a bottom boundary, the target gets .p-sticky-bottom and --p-sticky-bottom-pos once that
 * element reaches the viewport, so it rides up above it. A top boundary sets .p-sticky-pinned-top
 * and --p-sticky-top-pos, which the plugin's CSS leaves for the theme to style.
 *
 * @param {Object}      options
 * @param {HTMLElement} options.target   Element to keep fixed.
 * @param {HTMLElement} [options.bottom] Element the target must stay above, e.g. the footer.
 * @param {HTMLElement} [options.top]    Element the target must stay below, e.g. the header.
 * @returns {void}
 */
function plura_fx_sticky({ bottom, target, top }) {
	if (!target) {
		console.warn('plura_fx_sticky: Missing target element');
		return;
	}

	let ticking = false;

	/**
	 * Updates the target's class and position variable for one boundary.
	 *
	 * @param {HTMLElement} boundaryElement The top or bottom boundary.
	 * @param {string}      type            'top' or 'bottom'.
	 * @returns {void}
	 */
	const handleBoundary = (boundaryElement, type) => {
		const boundaryRect = boundaryElement.getBoundingClientRect();
		const viewportHeight = window.innerHeight;
		const targetRect = target.getBoundingClientRect();

		if (type === 'top') {
			const boundary = Math.max(0, boundaryRect.bottom);
			const shouldPin = targetRect.top < boundary;

			target.classList.toggle('p-sticky-pinned-top', shouldPin);
			target.style.setProperty('--p-sticky-top-pos', `${boundary}px`);
		} else if (type === 'bottom') {
			const shouldPin = boundaryRect.top <= viewportHeight;
			const offset = viewportHeight - boundaryRect.top;

			target.classList.toggle('p-sticky-bottom', shouldPin);
			target.style.setProperty('--p-sticky-bottom-pos', `${offset}px`);
		}
	};

	/**
	 * Updates both boundaries.
	 *
	 * @returns {void}
	 */
	const updateStickyPosition = () => {
		if (top) handleBoundary(top, 'top');
		if (bottom) handleBoundary(bottom, 'bottom');
	};

	/**
	 * Schedules one update per animation frame, however often scroll fires.
	 *
	 * @returns {void}
	 */
	const refresh = () => {
		if (ticking) return;

		requestAnimationFrame(() => {
			updateStickyPosition();
			ticking = false;
		});

		ticking = true;
	};

	// Initialize
	target.classList.add('p-sticky');
	refresh();

	// Event listeners
	window.addEventListener('scroll', refresh, { passive: true });
	window.addEventListener('resize', refresh, { passive: true });
}
