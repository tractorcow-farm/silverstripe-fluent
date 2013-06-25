
		
				
(function($) {
	
	/**
	 * File: fluent.js
	 * 
	 * Injects locale navigation menu into the website
	 */
	$.entwine('ss', function($) {

		/**
		 * Class: #Form_VersionsForm
		 *
		 * The left hand side version selection form is the main interface for
		 * users to select a version to view, or to compare two versions
		 */
		$('.cms > .cms-container > .cms-menu > .cms-panel-content.center').entwine({
			urlForLocale: function(locale) {
				
				// Get new locale code
				param = {FluentLocale: locale};

				// Check existing url
				search = /FluentLocale=[^&]*/;
				url = document.location.href;
				if(url.match(search)) {
					// Replace locale code
					url = url.replace(search, $.param(param));
				} else {
					// Add locale code
					url = $.path.addSearchParams(url, param);
				}
				return url;
			},
			// Inject the menu here
			onmatch: function() {
				this._super();
				var self = this;
				var selector = 
					$("<div class='cms-fluent-selector'>\
						<label class='cms-fluent-selector-label'></label>\
						<button class='cms-fluent-selector-flydown' type='button'>Change Locale</button>\
						<ul class='cms-fluent-selector-locales'></ul>\
					</div>");
				// Create options
				$.each(fluentLocales, function(locale, name){
					item = $("<li><a></a></li>")
					$("a", item)
						.text(name)
						.attr('href', self.urlForLocale(locale));
					$(".cms-fluent-selector-locales", selector).append(item);
				});
				// Display selected locale
				$(".cms-fluent-selector-label", selector).text(fluentLocales[fluentLocale]);
				
				this.prepend(selector);
			}
		});
	});
})(jQuery);
