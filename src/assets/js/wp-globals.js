document.addEventListener('DOMContentLoaded', () => {

	//carousel
	if( window.Carousel ) {

		document.querySelectorAll('.plura-wp-posts.plura-wp-f-carousel').forEach( element => {

			[ ...element.children ].forEach( element => element.classList.add('f-carousel__slide') );

			new Carousel( element, {

				transition: 'slide',

			  // Your custom options
			  Dots: true
			}/*, { Thumbs }*/);

		});

	}


});