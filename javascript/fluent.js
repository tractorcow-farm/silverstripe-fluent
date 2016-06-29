(function($) {
	/**
	 * File: fluent.js
	 * 
	 * Injects locale navigation menu into the website
	 */
	$.entwine('ss', function($) {
		/**
		 * Determine the url to navigate to given the specified locale
		 */
		var urlForLocale = function(url, locale) {
			var re, separator;
			// Remove hash. See https://github.com/tractorcow/silverstripe-fluent/issues/90
			url = url.split('#')[0];
				
			re = new RegExp("([?&])" + fluentParam + "=.*?(&|$)", "i");
			separator = url.indexOf('?') !== -1 ? "&" : "?";
			if (url.match(re)) {
				return url.replace(re, '$1' + fluentParam + "=" + locale + '$2');
			} else {
				return url + separator + fluentParam + "=" + locale;
			}
		};

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
					var item = $("<li><a><span class='full-title'></span><span class='short-title'></span></a></li>");
					$(".full-title", item).text(name);
					$(".short-title", item).text(locale.split("_")[0]);
					$("a", item)
						.attr('data-locale', locale)
						.attr('title', name);
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
			 * Takes the user to the selected locale
			 */
			onclick: function(event) {
				event.preventDefault();
				locale = this.attr('data-locale');
				url = urlForLocale(document.location.href, locale);

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

		$('.cms-panel-deferred').entwine({
			/**
			 * Ensure that any deferred panel URLs include the locale parameter in their URL
			 */
			onadd: function() {
				var url = this.attr('data-url'),
					newUrl = urlForLocale(url, $.cookie('FluentLocale_CMS'));

				this.attr('data-url', newUrl);
				this._super();
			}
		});

		/**
		 * Translated fields - visual indicator
		 */
		$('div.LocalisedField *').entwine({
			/**
			 * Switch the class and title/tooltip on translated fields
			 */
			toggleModified: function(sameAsDefault, selector) {
				selector = selector || this;

				var label = selector.closest('.field').find('.fluent-locale-label');
				label.toggleClass('fluent-modified-value');

				if (label.hasClass('fluent-modified-value')) {
					label.attr('title', 'Modified from default locale value - click to reset');
				} else {
					label.attr('title', 'Using default locale value');
				}
			}
		});

		$('div.LocalisedField .LocalisedField').entwine({
			/**
			 * Check for changes against the default value
			 */
			onchange: function() {
				if (this.is('input')) {
					var newValue = this.val();
				} else if (this.is('textarea')) {
					var newValue = this.text();
				}

				var defaultValue = this.data('default-locale-value');

				if (!defaultValue) {
					// We'll turn this off on the default locale
					return;
				}

				this.toggleModified(newValue === defaultValue);
			}			
		});

		/**
		 * If the user clicks on the locale label in its modified state, reset to the default field value
		 */
		$('.fluent-locale-label.fluent-modified-value').entwine({
			onclick: function() {
				var input = this.closest('.LocalisedField').find('.LocalisedField');

				var defaultValue = input.data('default-locale-value');
				if (!defaultValue) {
					return;
				}

				if (input.is('input')) {
					input.val(defaultValue);
				} else if (input.is('textarea')) {
					input.text(defaultValue);
					// If it's a WYSIWYG editor, trigger it to update
					if ('true' === input.attr('tinymce')) {
						tinyMCE.get(input.attr('id')).setContent(defaultValue);
					}
				}
				input.change();
			}
		});

		/**
		 * Maintain UI state of the CMS locale switcher when using standard browser navigation buttons.
		 */
		$('.cms-edit-form').entwine({
			redraw: function() {
				var localeMenuLocale = $.cookie('FluentLocale_CMS')
				$('.cms-fluent-selector .text').text(fluentLocales[localeMenuLocale]);
				this._super();
			}
		});
	});
})(jQuery);
