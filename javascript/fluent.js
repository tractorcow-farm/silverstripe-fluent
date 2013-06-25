
		
				
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
						<span class='icon icon-16 icon-fluent-translate'>&nbsp;</span>\
						<span class='text'></span>\
						<a class='cms-fluent-selector-flydown' type='button' title='Change Locale'><span class='icon icon-fluent-select'>Change Locale</span></a>\
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
				$(".text", selector).text(fluentLocales[fluentLocale]);
				
				this.prepend(selector);
				
				// Setup click events
				$(".cms-fluent-selector").each(function() {
					var self = $(this);
					self.click(function(event){
						self.toggleClass('active');
					});
				});
			}
		});
	});
})(jQuery);
