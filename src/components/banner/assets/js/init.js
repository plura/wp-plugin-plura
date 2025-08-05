document.addEventListener('DOMContentLoaded', () => {
	gsap.registerPlugin(SplitText);

	//https://www.youtube.com/watch?v=L1afzNAhI40&t=54s
	document.fonts.ready.then(() => {

		let split = SplitText.create(".plura-wp-component-banner h1", {
			type: "chars, words, lines",
			onSplit: (self) => {

				gsap.from(self.chars, {
					y: 100,
					autoAlpha: 0,
					stagger: 0.05
				});

			}
		});

	});


});
