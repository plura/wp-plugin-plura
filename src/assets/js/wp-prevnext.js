/**
 * Prev/next navigation (wp-nav.php): makes each [plura-wp-prevnext-nav] item clickable as a whole,
 * not only its link. Start it with plura_wp_prevnext({ target }).
 */

/**
 * Makes each item of a prev/next navigation follow its link when clicked anywhere.
 *
 * @param {Object}      options
 * @param {HTMLElement} options.target The .plura-wp-prevnext-nav element.
 * @returns {void}
 */
function plura_wp_prevnext({ target }) {
	/**
	 * Follows the clicked item's link.
	 *
	 * @param {MouseEvent} event Click on an item.
	 * @returns {void}
	 */
	const handler = (event) => {
		const nav_item_link = event.currentTarget.querySelector('.plura-wp-prevnext-nav-item-link');

		location.assign(nav_item_link.href);
	};

	target
		.querySelectorAll('.plura-wp-prevnext-nav-item')
		.forEach((element) => element.addEventListener('click', (event) => handler(event)));
}
