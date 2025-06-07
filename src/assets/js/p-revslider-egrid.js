/**
 * The following script fixes a bug in the current Essential Grid version:
 *
 * 		. the invisible trigger, ".eg-invisiblebutton", is wrapped by a paragraph tag, which prevents the trigger 
 * 		  from being targeted correctly
 * 		. this code checks "unwraps" the trigger, by appending it directly to the parent "esg-entry-cover".
 * 		. this allows for the trigger to be successfully reached by the ESS Grid methods. 
 */
document.addEventListener('DOMContentLoaded', () => {

	const 

		map = new Map(),

		check = grid => {

			grid.querySelectorAll('.esg-entry-cover').forEach( cover => {

				const trigger = cover.querySelector('.eg-invisiblebutton');

				if( trigger.parentNode !== cover ) {

					cover.append( trigger );

				}

			});

		},

		observer = new MutationObserver( entries => {

			document.querySelectorAll('.esg-grid').forEach( grid => {

				if( !map.has( grid ) ) {

					const obs = new MutationObserver( e => check( grid ) );

					obs.observe( grid, {subtree: true, childList: true} );

					map.set(grid, obs);

				}

			});
		
		});

	observer.observe(document.body, {subtree: true, childList: true});

});