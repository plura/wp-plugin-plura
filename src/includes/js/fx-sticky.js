function plura_fx_sticky({ bottom, target, top }) {
	if (!target) {
	  console.warn('plura_fx_sticky: Missing target element');
	  return;
	}
  
	let ticking = false;
  
	// Unified boundary handler pattern
	const handleBoundary = (boundaryElement, type) => {
	  const boundaryRect = boundaryElement.getBoundingClientRect();
	  const viewportHeight = window.innerHeight;
	  const targetRect = target.getBoundingClientRect();
  
	  if (type === 'top') {
		const boundary = Math.max(0, boundaryRect.bottom);
		const shouldPin = targetRect.top < boundary;
		
		target.classList.toggle('p-sticky-pinned-top', shouldPin);
		target.style.setProperty('--p-sticky-top-pos', `${boundary}px`);
	  } 
	  else if (type === 'bottom') {
		const shouldPin = boundaryRect.top <= viewportHeight;
		const offset = viewportHeight - boundaryRect.top;
		
		target.classList.toggle('p-sticky-bottom', shouldPin);
		target.style.setProperty('--p-sticky-bottom-pos', `${offset}px`);
	  }
	};
  
	const updateStickyPosition = () => {
	  const targetRect = target.getBoundingClientRect();
	  
	  if (top) handleBoundary(top, 'top');
	  if (bottom) handleBoundary(bottom, 'bottom');
	};
  
	const refresh = () => {
	  if (ticking) return;
	  
	  requestAnimationFrame(() => {
		updateStickyPosition();
		ticking = false;
	  });
  
	  ticking = true;
	};
  
	// Initialize
	target.classList.add('p-sticky');
	refresh();
  
	// Event listeners
	window.addEventListener('scroll', refresh, { passive: true });
	window.addEventListener('resize', refresh, { passive: true });
  }