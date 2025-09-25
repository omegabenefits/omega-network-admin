(function($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(function() {
		// hide the blog grid list submit button
		$('body.my-sites-php p.submit > #submit').hide();

		// live filtering as type in field
		$('#filterSites').on('keyup', function() {
			var value = $(this)
				.val()
				.toLowerCase();
			$('ul.my-sites li').filter(function() {
				$(this).toggle(
					$(this)
					.text()
					.toLowerCase()
					.indexOf(value) > -1
				);
			});
		});

		// prevent hitting enter key on filter field from submitting page form and refreshing
		$('#filterSites').on('keydown', function(event) {
			return event.key != 'Enter';
		});

		// make the filter field ready for text entry upon load
		$('#filterSites').select();

		// show total sites visible based on current filters
		if ($('body').hasClass('my-sites-php')) {
			var count = $('ul.my-sites li:visible').length;
			$('h1.wp-heading-inline').append(' <span class="site-count">[' + count + ']</span>');
		}
	});
})(jQuery);