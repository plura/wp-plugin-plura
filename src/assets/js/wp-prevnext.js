function plura_wp_prevnext({target}) {

	const handler = event => {

		const nav_item_link = event.currentTarget.querySelector('.plura-wp-prevnext-nav-item-link');

		location.assign( nav_item_link.href );

	};


	target.querySelectorAll('.plura-wp-prevnext-nav-item').forEach( element => element.addEventListener('click', event => handler( event ) ) );

}