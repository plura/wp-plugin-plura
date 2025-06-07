function plura_fx_sticky({bottom, target, top}) {


	const

		refresh = () => {

			targetRect = target.getBoundingClientRect();

			if( top ) {

				topRect = top.getBoundingClientRect();

			}

			if( bottom ) {

				//console.log(target, 'sticky');

				bottomRect = bottom.getBoundingClientRect();

				if (bottomRect.top <= window.innerHeight) {

					target.classList.add('p-sticky-bottom');

					target.style.setProperty('--p-sticky-bottom-pos', `${window.innerHeight - bottomRect.top}px`);

				} else {

					target.classList.remove('p-sticky-bottom');

				}

			}

			//const footerRect = footer.getBoundingClientRect();
			//const stickyRect = stickyElement.getBoundingClientRect();

			/*if (footerRect.top <= window.innerHeight) {
			  stickyElement.style.position = 'absolute';
			  stickyElement.style.bottom = `${window.innerHeight - footerRect.top}px`;
			} else {
			  stickyElement.style.position = 'fixed';
			  stickyElement.style.bottom = '0';
			}*/

			//console.log(targetRect);

		};



	if( target && ( bottom || top ) ) {

		target.classList.add('p-sticky');

		refresh();

		window.addEventListener('scroll', refresh);

	}

}


/*
window.addEventListener('scroll', function() {
  
  const stickyElement = document.querySelector('.sticky-element');
  const footer = document.querySelector('.footer');


  
  

  const footerRect = footer.getBoundingClientRect();
  const stickyRect = stickyElement.getBoundingClientRect();

  if (footerRect.top <= window.innerHeight) {
    stickyElement.style.position = 'absolute';
    stickyElement.style.bottom = `${window.innerHeight - footerRect.top}px`;
  } else {
    stickyElement.style.position = 'fixed';
    stickyElement.style.bottom = '0';
  }
}); */