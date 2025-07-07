function pluraAutoScroller({
	speed = 100,
	delay = 1000,
	target = window,
	toggleKey = ' ',
	easing = true
} = {}) {

	let isScrolling = true;
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
		if (isWindow) {
			window.scrollBy({ top: pixels });
		} else {
			target.scrollBy({ top: pixels });
		}
	};

	const maxSpeed = speed;
	const accel = 300;

	const scrollStep = (timestamp) => {
		if (!lastFrame) lastFrame = timestamp;

		const delta = timestamp - lastFrame;
		lastFrame = timestamp;

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
		}
	};

	const toggleScroll = (e) => {
		if (e.key === toggleKey || (toggleKey === ' ' && e.code === 'Space')) {
			e.preventDefault(); // 👈 fix the browser’s default Space scroll
			isScrolling = !isScrolling;
			requestAnimationFrame(scrollStep);
		}
	};

	setTimeout(() => {
		requestAnimationFrame(scrollStep);
	}, delay);

	window.addEventListener('keydown', toggleScroll);
}
