/**
 * Expandable text toggle component
 *
 * - Splits content into visible and hidden parts.
 * - If only a single paragraph or text node, can split by first sentence.
 * - Measures hidden part's height for smooth CSS transitions.
 *
 * @param {Object} config
 * @param {Object} config.labels - Optional text labels like { 'Read More': '...', 'Read Less': '...' }
 * @param {HTMLElement[]} config.target - Array of target elements
 * @param {boolean} [config.splitSingleParagraph=true] - Whether to split a single paragraph into two
 */
function PluraFXTextToggle({ labels, target, splitSingleParagraph = true }) {
	const PRFX = 'plura-fx-text-toggle';

	// Update CSS variable --max-height based on inner hidden content
	const updateHeights = () => {
		target.forEach(element => {
			const wrapper = element.querySelector(`.${PRFX}-wrapper`);
			const inner = wrapper?.querySelector(`.${PRFX}-part-hidden .${PRFX}-part-inner`);
			if (wrapper && inner) {
				const height = inner.offsetHeight;
				wrapper.style.setProperty('--max-height', `${height}px`);
			}
		});
	};

	// Refresh DOM structure
	const refresh = () => {
		target.forEach(element => {
			// Skip if already initialized
			if (element.querySelector(`.${PRFX}-wrapper`)) return;

			// Wrap text node in <p> if needed
			if (
				element.childNodes.length === 1 &&
				element.firstChild.nodeType === Node.TEXT_NODE
			) {
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
				paragraphs.slice(1).forEach(p => partInner.appendChild(p.cloneNode(true)));
			}

			const trigger = document.createElement('a');
			trigger.href = '#';
			trigger.classList.add(`${PRFX}-trigger`);
			trigger.textContent = labels?.['Read More'] || 'Read More';

			trigger.addEventListener('click', e => {
				e.preventDefault();
				const isOn = wrapper.classList.toggle('on');

				trigger.textContent = isOn
					? (labels?.['Read Less'] || 'Read Less')
					: (labels?.['Read More'] || 'Read More');
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

	target.forEach(element => {
		const wrapper = element.querySelector(`.${PRFX}-wrapper`);
		if (wrapper) observer.observe(wrapper);
	});
}
