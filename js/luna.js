/* LunarSystem front-end behaviour — dependency-free (no jQuery), no animation.
 * Show/hide is instant; add a CSS transition on .box-content or a row if you
 * want movement. */

function confirmSubmit(string) { return confirm(string); }

document.addEventListener('DOMContentLoaded', function () {

	// Zebra striping + row hover. The stylesheet styles .even / .odd / .hover.
	var rows = document.querySelectorAll('.zebra tr');
	for (var i = 0; i < rows.length; i++) {
		rows[i].classList.add(i % 2 ? 'odd' : 'even');
		rows[i].addEventListener('mouseover', function () { this.classList.add('hover'); });
		rows[i].addEventListener('mouseout', function () { this.classList.remove('hover'); });
	}

	// Collapsible box handles: the bottom-bar hamburger sitemap, the search box, …
	// Instant open/close — flip the +/- class and the adjacent .box-content display.
	var handles = document.querySelectorAll('.box-handle');
	for (var j = 0; j < handles.length; j++) {
		handles[j].addEventListener('click', function () {
			var opening = this.classList.contains('collapsed');
			this.classList.toggle('collapsed', !opening);
			this.classList.toggle('expanded', opening);
			var content = this.nextElementSibling;
			if (content) { content.style.display = opening ? 'block' : 'none'; }
		});
	}
});
