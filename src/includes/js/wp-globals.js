document.addEventListener('DOMContentLoaded', () => {

	//Globals: Layout width
	const 

		globals_class = 'plura-has-globals',

		globals_prefix = 'plura-globals',

		globals_observer = new ResizeObserver( entries => {

			let globals = {'w': `calc(100vw - ${ p.scrollWidth() }px)`};/*,

				main = document.getElementById('sns-main-content');

			if( main ) {

				globals['w-main'] = `${ main.offsetWidth }px`;

				console.log('w-main', Date.now());

			}

			console.log( globals, Date.now() );*/

			Object.entries( globals ).forEach( ([key, value] ) => document.documentElement.style.setProperty(`--${ globals_prefix }-${ key }`, value) );

			if( !document.body.classList.contains( globals_class ) ) {

				document.body.classList.add( globals_class );

			}			

		});

	globals_observer.observe( document.body );



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