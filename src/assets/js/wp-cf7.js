document.addEventListener('DOMContentLoaded', () => {

	const form_field_id_prefix = `plura-wp-cf7-${ Date.now() }-`;

	document.querySelectorAll('.wpcf7 form.plura-wp-cf7').forEach( (form, formIndex) => {



		//checkboxes span 2 label
		form.querySelectorAll('.wpcf7-radio.plura-wp-cf7-btn .wpcf7-list-item').forEach( (element, index) => {

			let info, txt;

			const

				input = element.querySelector('[type="radio"]'),
				span = element.querySelector('span.wpcf7-list-item-label'),
				label = document.createElement('label'),
				id = input.hasAttribute('id') ? input.id : 'wpcf7-' + Date.now() + '-' + index;


			label.setAttribute('for', id);

			label.textContent = span.textContent;

			if( input.hasAttribute('data-info') ) {


				( txt = document.createElement('span') ).classList.add( ...['wpcf7-list-item-label-txt'] );

				txt.append( ...label.childNodes );

				label.append( txt );


				( info = label.appendChild( document.createElement('span') ) ).classList.add( ...['wpcf7-list-item-label-info'] );

				setTimeout( () => {
				
					info.textContent = input.getAttribute('data-info');
				
					label.classList.add( ...['has-info'] );

				}, 0);

			}


			input.id = id;


			[ ...span.attributes ].forEach( a => label.setAttribute( a.nodeName, a.nodeValue ) );

			span.replaceWith( label );
	
		});



		//add "for" attributes to labels of email, text and textarea elements
		form.querySelectorAll(`
			input:is([type="email"], [type="tel"], [type="text"]),
			select,
			textarea
		`)
		.forEach( element => {

			const

				id = `${ form_field_id_prefix + formIndex }-${ element.getAttribute('name') }`,

				label = element.closest('label'),

				wrapper = element.closest('.wpcf7-form-control-wrap'),

				data = 	{'tag': element.tagName.toLowerCase() };

			
			if( label || wrapper ) {


				if( label && !label.hasAttribute('for') ) {

					element.setAttribute('id', id);
				
					label.setAttribute('for', id);

				}

				if( element.hasAttribute('type') ) {

					data['input-type'] = element.getAttribute('type');

				}


				Object.entries( data ).forEach( ([key,value]) => {

					( form.classList.contains('plura-wp-cf7-no-labels') || !label ? wrapper : label ).setAttribute(`data-${key}`, value)

				} );

				/*( form.classList.contains('plura-wp-cf7-no-labels') || !label ? wrapper : label )

				.setAttribute('data-input-type', element.hasAttribute('type') ? element.getAttribute('type') : element.tagName.toLowerCase() );*/
			
			}

		
		});


	} );


});