/**
 *
 *
 * 
 *
 *
 * 
 */

function PluraWPDynamicGrid({breakpoints, cond = 'AND', target}) {

	let active, grid_cols;


	const 

		FILTER_DATA_FILTER_TYPE_SELECT = 'select',

		FILTER_DATA_FILTER_TYPE_TAG = 'tag',

		/* 
			LIMITS allows for an if/else if/else condition like this

			if( w >= 1600 ) {

				n = 6;

			} if( w >= 1366 && w < 1600 ) {

				n = 5;

			} else if( w >= 991 && w < 1366 ) {

				n = 4;

			} else if( w >= 768 && w < 991 ) {

				n = 3;

			} else {

				n = 2;

			}
		 */

		COLS_BREAKPOINTS = [
			{ min: 1600, cols: 6 },
			{ min: 1366, max: 1600, cols: 5 },
			{ min: 991, max: 1366, cols: 4 },
			{ min: 768, max: 991, cols: 3 },
			// Default value
			{ max: 991, cols: 2 }
		],

		ui_filter_groups = target.querySelectorAll('.plura-wp-dynamic-grid-filter-group'),

		ui_grid = target.querySelector('.plura-wp-dynamic-grid-items'),



		activate = () => {

			let clss = 'filtered', tags = [], url = plura_wp_data.restURL + 'plura/v1/posts/';

			ui_filter_groups.forEach( group => {

				if( group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT ) {

					group.value.match(/^[0-9]+$/) && tags.push( group.value );

				} else if( group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG ) {

					group.querySelectorAll('.plura-wp-dynamic-grid-filter-item').forEach( element => 

						element.classList.contains('on') && element.dataset.id.match(/^[0-9]+$/) && tags.push( element.dataset.id )

					);

				}

			} );

			if( tags.length ) {

				url += '?' + ( new URLSearchParams( {tags: tags.join(',') } ) ).toString() + '&tags_cond=' + cond;

				target.classList.add( clss );

			} else {

				target.classList.remove( clss );

			}

			console.log( url );

			fetch( url ).then( response => response.json() ).then( data => refresh( data ) );

		},


		activateTag = element => {

			element.classList.toggle('on');

			activate();

		},




		//refreshes layout according to window size
		set_grid_cols = () => {

			let w = window.innerWidth, b = breakpoints || COLS_BREAKPOINTS,

				n = b.find( breakpoint => w >= (breakpoint.min || 0) && w < (breakpoint.max || Infinity) )?.cols;

			//set grid_cols var
			grid_cols = n || 2;

			console.log(w, grid_cols);
			
			//set grid properties
			Object.entries({w: `${ ui_grid.offsetWidth }px`, cols: grid_cols})

			.forEach(([key, value]) => ui_grid.style.setProperty(`--grid-${key}`, value) );

			//refresh grid
			refresh();

		},



		refresh = data => {

			if( data ) {

				active = data;

			}

			let ui_grid_items = ui_grid.querySelectorAll('.plura-wp-dynamic-grid-item'),

				//get number of rows
				rows = !active || active.length > 0 ? Math.floor( ( !active ? ui_grid_items.length : active.length ) / grid_cols ) + 1 : 0;

			ui_grid_items.forEach( (item, index) => {

				const id = Number( item.dataset.id );

				if( !active || active.includes( id ) ) {

						//get item index (if no active is set use index)
					let n = !active ? index : active.indexOf( id ),

						//get item x pos
						x = n % grid_cols,

						//get item y pos
						y = Math.floor( n / grid_cols );

					//set item x/y css vars
					Object.entries({x: x, y: y}).forEach( ([key, value]) => item.style.setProperty(`--${key}`, value) );

					//set item 'on' status
					item.classList.add('on');
	
				} else {

					//remove item 'on' status
					item.classList.remove('on');

					//['x', 'y'].forEach( key => work.style.removeProperty(`--${key}`) );
				
				}

			});

			//set grid number of rows css var. b/c its children have their position set
			//to absolute, it's necessary to update their holder's height
			ui_grid.style.setProperty('--grid-rows', rows);

		},

		//observes browser window resize
		resizeObserver = new ResizeObserver( entries => set_grid_cols() );



	target.classList.add( ...['active', 'p-dynamic-grid'] );


	//filters
	if( ui_filter_groups.length ) {

		ui_filter_groups.forEach( group => {

			console.log( group.dataset.filterType );

			if( group.dataset.filterType === FILTER_DATA_FILTER_TYPE_SELECT ) {

				group.addEventListener('change', event => activate() );

			} else if( group.dataset.filterType === FILTER_DATA_FILTER_TYPE_TAG ) {

				group.querySelectorAll('.plura-wp-dynamic-grid-filter-item').forEach( element => 

					element.addEventListener('click', event => activateTag( element ) )

				);
			
			}

		} );

	}


	resizeObserver.observe( target );


}
