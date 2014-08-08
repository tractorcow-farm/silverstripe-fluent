
		
				
(function($) {
	
	/**
	 * File: fluent.js
	 * 
	 * Injects locale navigation menu into the website
	 */
	$.entwine('ss', function($) {

		/**
		 * Activation for cms menu
		 */
		$('.cms > .cms-container > .cms-menu > .cms-panel-content.center').entwine({
			
			/**
			 * Generate the locale selector when activated
			 */
			onmatch: function() {
				this._super();
				var selector = 
					$("<div class='cms-fluent-selector'>\
						<span class='icon icon-16 icon-fluent-translate'>&nbsp;</span>\
						<span class='text'></span>\
						<a class='cms-fluent-selector-flydown' type='button' title='"+fluentButtonTitle+"'><span class='icon icon-fluent-select'>"+fluentButtonTitle+"</span></a>\
						<ul class='cms-fluent-selector-locales'></ul>\
					</div>");
				
				// Create options
				$.each(fluentLocales, function(locale, name){
					var item = $("<li><a></a></li>");
					$("a", item)
						.text(name)
						.attr('data-locale', locale);
					$(".cms-fluent-selector-locales", selector).append(item);
				});
				
				// Display selected locale
				$(".text", selector).text(fluentLocales[fluentLocale]);
				
				this.prepend(selector);
			}
		});
				
		/**
		 * Selector container
		 */
		$(".cms-fluent-selector").entwine({
			/**
			 * Show or hide the selector when clicked
			 */
			onclick: function() {
				this.toggleClass('active');
			}
		});
		
		/**
		 * Locale links
		 */
		$(".cms-fluent-selector .cms-fluent-selector-locales a").entwine({
			/**
			 * Determine the url to navigate to given the specified locale
			 */
			urlForLocale: function(locale) {
				
				// Get new locale code
				param = {}
				param[fluentParam] = locale;

				// Check existing url
				search = new RegExp('\\b'+fluentParam+'=[^&#]*');
				url = document.location.href;
				if(url.match(search)) {
					// Replace locale code
					url = url.replace(search, $.param(param));
				} else {
					// Add locale code
					url = $.path.addSearchParams(url, param);
				}
				
				// Remove hash. See https://github.com/tractorcow/silverstripe-fluent/issues/90
				return url.split('#')[0];
			},
			/**
			 * Takes the user to the selected locale
			 */
			onclick: function(event) {
				event.preventDefault();
				locale = this.attr('data-locale');
				url = this.urlForLocale(locale);
				
				// Load panel
				$('.cms-container').loadPanel(url);
				
				// Update selector
				$(".cms-fluent-selector")
					.removeClass("active")
					.find(".text")
						.text(fluentLocales[locale]);
				
				return false;
			}
		});
	});
})(jQuery);
