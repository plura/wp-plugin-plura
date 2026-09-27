/**
 * Contact Form 7 (wp-cf7.php): on page load, adapts every CF7 form with the plura-wp-cf7 class.
 * Radio buttons in .plura-wp-cf7-btn groups get real <label>s, with an optional data-info line, and
 * fields get ids, label "for" attributes and the data-tag / data-input-type attributes wp-cf7.css uses.
 */

document.addEventListener('DOMContentLoaded', () => {
	const form_field_id_prefix = `plura-wp-cf7-${Date.now()}-`;

	document.querySelectorAll('.wpcf7 form.plura-wp-cf7').forEach((form, formIndex) => {
		// Radio buttons: replace each item's <span> label with a <label> for its input
		form.querySelectorAll('.wpcf7-radio.plura-wp-cf7-btn .wpcf7-list-item').forEach((element, index) => {
			let info, txt;

			const input = element.querySelector('[type="radio"]'),
				span = element.querySelector('span.wpcf7-list-item-label'),
				label = document.createElement('label'),
				id = input.hasAttribute('id') ? input.id : 'wpcf7-' + Date.now() + '-' + index;

			label.setAttribute('for', id);

			label.textContent = span.textContent;

			if (input.hasAttribute('data-info')) {
				(txt = document.createElement('span')).classList.add(...['wpcf7-list-item-label-txt']);

				txt.append(...label.childNodes);

				label.append(txt);

				(info = label.appendChild(document.createElement('span'))).classList.add(...['wpcf7-list-item-label-info']);

				setTimeout(() => {
					info.textContent = input.getAttribute('data-info');

					label.classList.add(...['has-info']);
				}, 0);
			}

			input.id = id;

			[...span.attributes].forEach((a) => label.setAttribute(a.nodeName, a.nodeValue));

			span.replaceWith(label);
		});

		// Fields: link each to its label, and tag the label (or wrapper) with the field's type
		form
			.querySelectorAll(
				`
			input:is([type="email"], [type="tel"], [type="text"]),
			select,
			textarea
		`,
			)
			.forEach((element) => {
				const id = `${form_field_id_prefix + formIndex}-${element.getAttribute('name')}`,
					label = element.closest('label'),
					wrapper = element.closest('.wpcf7-form-control-wrap'),
					data = { tag: element.tagName.toLowerCase() };

				if (label || wrapper) {
					if (label && !label.hasAttribute('for')) {
						element.setAttribute('id', id);

						label.setAttribute('for', id);
					}

					if (element.hasAttribute('type')) {
						data['input-type'] = element.getAttribute('type');
					}

					Object.entries(data).forEach(([key, value]) => {
						(form.classList.contains('plura-wp-cf7-no-labels') || !label ? wrapper : label).setAttribute(
							`data-${key}`,
							value,
						);
					});
				}
			});
	});
});
