/**
 * Post carousels (wp-posts.php): on page load, turns each .plura-wp-posts.plura-wp-f-carousel list
 * into a Fancybox Carousel. Does nothing unless the site loads Fancybox's Carousel script.
 */

document.addEventListener('DOMContentLoaded', () => {
	if (window.Carousel) {
		document.querySelectorAll('.plura-wp-posts.plura-wp-f-carousel').forEach((element) => {
			[...element.children].forEach((element) => element.classList.add('f-carousel__slide'));

			new Carousel(element, {
				transition: 'slide',
				Dots: true,
			});
		});
	}
});
