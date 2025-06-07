const p = {


	//TEMPLATE_PATH: document.getElementById('divi-style-css').href.replace(/style\.css(.+)?/, ''),


	empty: element => {

		while (element.firstChild) {

			element.removeChild(element.firstChild);

		}

	},


	lang: data => {

		let obj = typeof DICTIONARY === 'undefined' ? {} : DICTIONARY;

		if( typeof data === 'string' ) {

			return obj[data] || data;

		} /*else if( typeof data === "object" ) {

			//obj	= plura.extend( obj, data );

		}*/

		DICTIONARY = obj;

		return obj;
	},




	page: (classes, all = false) => {

		if( !Array.isArray( classes ) ) {

			classes = [ classes ];

		}

		for( let  [index, value] of classes.entries() ) {

			if( ( !all || index === classes.length - 1) && document.body.classList.contains( value ) ) {

				return true;

			}

		}

		return false;

	},


	polyfill: ({object, handler, type = 'resize'}) => {

		const 

			handlers = Array.isArray( handler ) ? handler : [handler, handler],

			elements = Array.isArray( object ) || ( object instanceof NodeList ) ? object : [ object ];

		//console.log( elements );

		let observer;

		//Resize Observer
		if( type === 'resize' && typeof ResizeObserver !== "undefined" ) {

			observer = new ResizeObserver( handlers[0] );

		} else if( type === 'intersection' && typeof IntersectionObserver !== "undefined" ) {

			observer = new IntersectionObserver( handlers[0] );

		}

		if( observer ) {

			elements.forEach( element => observer.observe( element ) );

		} else {

			const

				noobservertype = type === 'intersection' ? 'scroll' : type,

				fn = () => handlers[1]( elements.map( element => ({target: element}) ) );

			window.addEventListener(noobservertype, fn );

			setTimeout(fn, 100); 

		}

	},	


	scrollWidth: () => {

		// Create the measurement node
		let el = document.body.appendChild( document.createElement("div") ),

			props = {width: '100px', height: '100px',  overflow: 'scroll', position: 'absolute', top: '-9999px'},

			scrollbarWidth;

		Object.entries( props ).forEach( ([key, value]) => el.style[key] = value );

		//Get the scrollbar width
		scrollbarWidth = el.offsetWidth - el.clientWidth;
		
		//console.warn(scrollbarWidth); // Mac:  15

		// Delete the DIV 
		el.parentNode.removeChild( el );

		return scrollbarWidth;

	},



	request: async (path, params) => {

		let data = await fetch(path, {
							body: JSON.stringify( params ),
							headers: {
								'Accept': 'application/json',
								'Content-Type': 'application/json'
							},
							method: 'POST'
						})

		    			.then( response => response.json() );

		 return data;
	
	},

	

	translate: (selectors, dictionary) => {

		//escape string
		const esc = string => string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');


		selectors.forEach(selector => {

			let has_attr = selector instanceof Array, elements = document.querySelectorAll( has_attr ? selector[0] :  selector);

			if( elements ) {

				elements.forEach( element => {

					//console.log(element);

					Object.entries( dictionary ).forEach( ([lang, trans]) => {

						let langreg = new RegExp( esc( lang ) );

						//console.log(escape_string( lang ))

						if( 

							//if it was not translated before
							!element.classList.contains('p-translated') 

							&& ( 	
									//node text/html value matches
									(!has_attr && element.innerHTML.match( langreg ) ) || 

									//or node attribute matches
									(element.hasAttribute( selector[1] ) && element.getAttribute( selector[1] ).match( langreg ) )

								) 

							) {
							
							let translation = ( has_attr ? element.getAttribute( selector[1] ) : element.innerHTML ).replace(langreg, trans);

							if( has_attr ) {

								element.setAttribute( selector[1], translation );

							} else {

								element.innerHTML = translation;

							}

							//adding a class avoids multiple translations on the same element
							element.classList.add('p-translated')

						}

					});

				});

			}

		});

	},


	translate_media: media => {

		Object.entries( media ).forEach( ([selector, file]) => {

			document.querySelectorAll( selector )

			.forEach( (element, index) => {

				['sizes', 'srcset'].forEach( attr => element.removeAttribute(attr) );

				element.src = element.src.replace(/(uploads\/)(.+)/, `$1${ file }`);

			});

		} );

	}


}