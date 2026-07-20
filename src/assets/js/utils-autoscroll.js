function pluraAutoScroller({
	speed = 100,
	delay = 1000,
	target = window,
	toggleKey = ' ',
	easing = true
} = {}) {

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

	const getScrollTop = () => isWindow ? window.scrollY : target.scrollTop;
	const getScrollHeight = () => isWindow
		? document.documentElement.scrollHeight
		: target.scrollHeight;
	const getClientHeight = () => isWindow
		? window.innerHeight
		: target.clientHeight;
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

	const isEditableTarget = (el) => {
		if (!el) return false;
		const tag = el.tagName;
		return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
	};

	const toggleScroll = (e) => {
		if (isEditableTarget(e.target)) return;

		if (e.key === toggleKey || (toggleKey === ' ' && e.code === 'Space')) {
			e.preventDefault(); // 👈 fix the browser’s default Space scroll
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
		}
	};
}
