/**
 * Auto-scroll: scrolls the page, or an element, down at a steady speed, paused and resumed with a
 * key. Start it with pluraAutoScroller(); it has no styles.
 */

/**
 * Starts scrolling after a delay and keeps going until the end; the toggle key pauses and resumes it.
 *
 * @param {Object}                    [options]
 * @param {number}                    [options.speed=100]     Top speed in pixels per second.
 * @param {number}                    [options.delay=1000]    Milliseconds before scrolling starts.
 * @param {Window|HTMLElement|string} [options.target=window] What to scroll: the window, an element, or a selector.
 * @param {string}                    [options.toggleKey=' '] Key that pauses and resumes; ignored while typing in a field.
 * @param {boolean}                   [options.easing=true]   Whether to speed up and slow down gradually instead of at once.
 * @returns {{destroy: function(): void}|undefined} Handle whose destroy() stops it for good,
 *                                                   or undefined when the selector matches nothing.
 */
function pluraAutoScroller({ speed = 100, delay = 1000, target = window, toggleKey = ' ', easing = true } = {}) {
	let isScrolling = true;
	let isRunning = false;
	let isDestroyed = false;
	let lastFrame = null;
	let velocity = 0;

	if (typeof target === 'string') {
		const resolved = document.querySelector(target);
		if (!resolved) {
			console.error(`pluraAutoScroller: No element found for selector "${target}"`);
			return;
		}
		target = resolved;
	}

	const isWindow = target === window;

	/** @returns {number} How far the target is scrolled, in pixels. */
	const getScrollTop = () => (isWindow ? window.scrollY : target.scrollTop);

	/** @returns {number} The target's full scrollable height, in pixels. */
	const getScrollHeight = () => (isWindow ? document.documentElement.scrollHeight : target.scrollHeight);

	/** @returns {number} The target's visible height, in pixels. */
	const getClientHeight = () => (isWindow ? window.innerHeight : target.clientHeight);

	/**
	 * Scrolls the target down.
	 *
	 * @param {number} pixels Distance to scroll.
	 * @returns {void}
	 */
	const doScrollBy = (pixels) => {
		// behavior: 'instant' avoids the page's own `scroll-behavior: smooth` CSS
		// hijacking these per-frame calls into competing browser-native animations
		if (isWindow) {
			window.scrollBy({ top: pixels, behavior: 'instant' });
		} else {
			target.scrollBy({ top: pixels, behavior: 'instant' });
		}
	};

	const maxSpeed = speed;
	const accelTime = 1 / 3; // seconds to reach maxSpeed, scales accel so easing feels consistent across speeds
	const accel = maxSpeed / accelTime;

	/**
	 * Animation frame: updates the speed, scrolls, and schedules the next frame until the end is reached.
	 *
	 * @param {number} timestamp Frame time from requestAnimationFrame().
	 * @returns {void}
	 */
	const scrollStep = (timestamp) => {
		if (isDestroyed) {
			isRunning = false;
			return;
		}

		isRunning = true;

		if (!lastFrame) lastFrame = timestamp;

		const rawDelta = timestamp - lastFrame;
		lastFrame = timestamp;
		// cap the per-frame delta so a stalled main thread (heavy scroll-linked
		// JS on some sites) can't produce one huge catch-up jump on resume
		const delta = Math.min(rawDelta, 50);

		if (isScrolling) {
			if (easing) {
				velocity += accel * (delta / 1000);
				if (velocity > maxSpeed) velocity = maxSpeed;
			} else {
				velocity = maxSpeed;
			}
		} else {
			if (easing) {
				velocity -= accel * (delta / 1000);
				if (velocity < 0) velocity = 0;
			} else {
				velocity = 0;
			}
		}

		if (velocity > 0) {
			doScrollBy(velocity * (delta / 1000));
		}

		if (getScrollTop() + getClientHeight() < getScrollHeight() || velocity > 0) {
			requestAnimationFrame(scrollStep);
		} else {
			lastFrame = null;
			isRunning = false;
		}
	};

	/**
	 * Checks whether a key press happened in a field, where the toggle key must type normally.
	 *
	 * @param {Element|null} el Key event target.
	 * @returns {boolean}
	 */
	const isEditableTarget = (el) => {
		if (!el) return false;
		const tag = el.tagName;
		return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
	};

	/**
	 * Pauses or resumes scrolling on the toggle key.
	 *
	 * @param {KeyboardEvent} e Key press.
	 * @returns {void}
	 */
	const toggleScroll = (e) => {
		if (isEditableTarget(e.target)) return;

		if (e.key === toggleKey || (toggleKey === ' ' && e.code === 'Space')) {
			e.preventDefault(); // Space would otherwise also scroll the page by a screen
			isScrolling = !isScrolling;
			if (!isRunning) requestAnimationFrame(scrollStep);
		}
	};

	const timerId = setTimeout(() => {
		requestAnimationFrame(scrollStep);
	}, delay);

	window.addEventListener('keydown', toggleScroll);

	return {
		destroy() {
			isDestroyed = true;
			clearTimeout(timerId);
			window.removeEventListener('keydown', toggleScroll);
		},
	};
}
