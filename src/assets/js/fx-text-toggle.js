/**
 * Text toggle: collapses text to its first paragraph, or first sentence, behind a "Read More" link
 * that expands the rest. Start it with PluraFXTextToggle({ target }); the styles are in fx.css.
 */

/**
 * Splits each target's text into a visible part and a collapsible hidden part, followed by the link.
 *
 * Targets already set up are skipped. The hidden part's height is kept in --max-height, which the
 * CSS transitions to when the link adds .on.
 *
 * @param {Object}                 options
 * @param {HTMLElement[]|NodeList} options.target                      Elements to set up.
 * @param {Object}                 [options.labels]                    Link texts keyed 'Read More' and 'Read Less', e.g. translated.
 * @param {boolean}                [options.splitSingleParagraph=true] Whether a single paragraph splits after its first sentence.
 * @returns {void}
 */
function PluraFXTextToggle({ labels, target, splitSingleParagraph = true }) {
	const PRFX = 'plura-fx-text-toggle';

	/**
	 * Stores each hidden part's natural height in --max-height, for the expand transition.
	 *
	 * @returns {void}
	 */
	const updateHeights = () => {
		target.forEach((element) => {
			const wrapper = element.querySelector(`.${PRFX}-wrapper`);
			const inner = wrapper?.querySelector(`.${PRFX}-part-hidden .${PRFX}-part-inner`);
			if (wrapper && inner) {
				const height = inner.offsetHeight;
				wrapper.style.setProperty('--max-height', `${height}px`);
			}
		});
	};

	/**
	 * Rebuilds each target not yet set up into visible part, hidden part and link.
	 *
	 * @returns {void}
	 */
	const refresh = () => {
		target.forEach((element) => {
			// Skip if already initialized
			if (element.querySelector(`.${PRFX}-wrapper`)) return;

			// Wrap text node in <p> if needed
			if (element.childNodes.length === 1 && element.firstChild.nodeType === Node.TEXT_NODE) {
				const p = document.createElement('p');
				p.textContent = element.textContent.trim();
				element.innerHTML = '';
				element.appendChild(p);
			}

			const paragraphs = Array.from(element.querySelectorAll('p'));
			if (paragraphs.length === 0) return;

			const wrapper = document.createElement('div');
			wrapper.classList.add(`${PRFX}-wrapper`);

			const partVisible = document.createElement('div');
			partVisible.classList.add(`${PRFX}-part`);

			const partHidden = document.createElement('div');
			partHidden.classList.add(`${PRFX}-part`, `${PRFX}-part-hidden`);

			const partInner = document.createElement('div');
			partInner.classList.add(`${PRFX}-part-inner`);
			partHidden.appendChild(partInner);

			if (paragraphs.length === 1 && splitSingleParagraph) {
				const fullText = paragraphs[0].textContent.trim();
				const match = fullText.match(/.*?[.!?](\s|$)/);

				if (match) {
					const first = match[0].trim();
					const rest = fullText.slice(first.length).trim();

					if (first) {
						const p1 = document.createElement('p');
						p1.textContent = first;
						partVisible.appendChild(p1);
					}
					if (rest) {
						const p2 = document.createElement('p');
						p2.textContent = rest;
						partInner.appendChild(p2);
					}
				} else {
					partVisible.appendChild(paragraphs[0].cloneNode(true));
				}
			} else {
				partVisible.appendChild(paragraphs[0].cloneNode(true));
				paragraphs.slice(1).forEach((p) => partInner.appendChild(p.cloneNode(true)));
			}

			const trigger = document.createElement('a');
			trigger.href = '#';
			trigger.classList.add(`${PRFX}-trigger`);
			trigger.textContent = labels?.['Read More'] || 'Read More';

			trigger.addEventListener('click', (e) => {
				e.preventDefault();
				const isOn = wrapper.classList.toggle('on');

				trigger.textContent = isOn ? labels?.['Read Less'] || 'Read Less' : labels?.['Read More'] || 'Read More';
			});

			// Assemble structure
			wrapper.appendChild(partVisible);
			if (partInner.children.length > 0) wrapper.appendChild(partHidden);
			wrapper.appendChild(trigger);

			element.innerHTML = '';
			element.appendChild(wrapper);
		});

		updateHeights();
	};

	// Observe DOM for resizes
	const observer = new ResizeObserver(() => {
		updateHeights();
	});

	// Run once for all targets
	refresh();

	target.forEach((element) => {
		const wrapper = element.querySelector(`.${PRFX}-wrapper`);
		if (wrapper) observer.observe(wrapper);
	});
}
