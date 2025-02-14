function pluraFXInfiniteScroll({
  target,
  speed = 4,
  direction = "left",
  container,
} = {}) {
  const targetEl = typeof target === "string" ? document.querySelector(target) : target;
  if (!targetEl) return;

  container = container || targetEl.parentElement;
  container.style.setProperty('--plura-fx-infinitescroll-speed', speed);

  // Create the track and wrapper elements
  const track = document.createElement('div');
  let wrapper1 = document.createElement('div');
  let wrapper2 = document.createElement('div');
  track.classList.add('plura-fx-infinitescroll-track');
  container.appendChild(track);

  container.classList.add('plura-fx-infinitescroll-container');
  wrapper1.classList.add('plura-fx-infinitescroll-wrapper');
  wrapper2.classList.add('plura-fx-infinitescroll-wrapper');
  track.appendChild(wrapper1);
  track.appendChild(wrapper2);

  // Move the original target element into wrapper1
  wrapper1.appendChild(targetEl);

  // Wait for the width to be properly set after moving targetEl to wrapper1
  function waitForWidth() {
    const targetWidth = targetEl.offsetWidth;
    const containerWidth = container.offsetWidth;

    if (!targetWidth || !containerWidth) {
      // Re-run the wait function if width isn't set
      requestAnimationFrame(waitForWidth);
      return;
    }

    // Set dynamic height and start initialization
    const targetHeight = targetEl.offsetHeight;
    container.style.setProperty('--plura-fx-infinitescroll-height', `${targetHeight}px`);

    // Ensure the item class on the target element
    targetEl.classList.add('plura-fx-infinitescroll-item');

    // Clone items into wrapper1 until the combined width exceeds twice the container width
    let totalWidth = targetWidth;
    while (totalWidth < containerWidth * 2) {
      const clone = targetEl.cloneNode(true);
      wrapper1.appendChild(clone);
      totalWidth += clone.offsetWidth;
    }

    // Copy wrapper1's content into wrapper2 for seamless scrolling
    wrapper2.innerHTML = wrapper1.innerHTML;

    // Add the target class only to the original target element
    targetEl.classList.add('plura-fx-infinitescroll-target');

    function pos(element, value = 0) {
      element.style.left = `${value}px`;
    }

    pos(wrapper1);
    pos(wrapper2, wrapper1.offsetWidth);

    let offset = 0;

    function animate() {
      offset += direction === "left" ? -speed : speed;
      container.style.setProperty('--plura-fx-infinitescroll-pos', `${offset}px`);

      const containerRect = container.getBoundingClientRect();
      const wrapper1Rect = wrapper1.getBoundingClientRect();
      const wrapper2Rect = wrapper2.getBoundingClientRect();

      if (direction === "left" && wrapper1Rect.right <= containerRect.left) {
        pos(wrapper1, wrapper2.offsetLeft + wrapper2.offsetWidth);
        [wrapper1, wrapper2] = [wrapper2, wrapper1];
      } else if (direction === "right" && wrapper2Rect.left >= containerRect.right) {
        pos(wrapper2, wrapper1.offsetLeft - wrapper2.offsetWidth);
        [wrapper1, wrapper2] = [wrapper2, wrapper1];
      }

      requestAnimationFrame(animate);
    }

    requestAnimationFrame(animate);
  }

  // Start the width check after appending the target
  requestAnimationFrame(waitForWidth);
}
