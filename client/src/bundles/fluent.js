window.jQuery.entwine('ss', ($) => {
  // CMS admin extensions
  $('input[data-hides]').entwine({
    onmatch() {
      this._super();
      const hideName = this.data('hides');
      const target = $(`[name='${hideName}']`).closest('.field');
      if (this.is(':checked')) {
        target.hide();
      } else {
        target.show();
      }
    },
    onunmatch() {
      this._super();
    },
    onchange() {
      const hideName = this.data('hides');
      const target = $(`[name='${hideName}']`).closest('.field');
      if (this.is(':checked')) {
        target.slideUp();
      } else {
        target.slideDown();
      }
    },
  });

  /**
   * Activation for cms menu
   */
  $('.cms > .cms-container > .cms-menu > .cms-panel-content').entwine({
    /**
     * Get section config for fluent
     *
     * @return {Object}
     */
    fluentConfig() {
      const section = 'TractorCow\\Fluent\\Control\\LocaleAdmin';
      if (window
        && window.ss
        && window.ss.config
        && window.ss.config.sections
      ) {
        const config = window.ss.config.sections.find((next) => next.name === section);
        if (config) {
          return config.fluent || {};
        }
      }
      return {};
    },

    /**
     * Generate the locale selector when activated
     */
    onmatch() {
      this._super();
      const config = this.fluentConfig();
      // Skip if no locales defined
      if (typeof config.locales === 'undefined' || typeof config.title === 'undefined') {
        return;
      }
      const buttonTitle = config.title;
      const selector = $(
        `<div class='cms-fluent-selector'>
          <span class='icon icon-16 icon-fluent-translate'>&nbsp;</span>
          <span class='text'></span>
          <a class='cms-fluent-selector-flydown' type='button'>
            <span class='icon icon-fluent-select'></span>
          </a>
          <ul class='cms-fluent-selector-locales'></ul>
        </div>`
      );
      $('.cms-fluent-selector-flydown', selector).prop('title', buttonTitle);
      $('.cms-fluent-selector-flydown span', selector).text(buttonTitle);

      // Create options
      config.locales.forEach((locale) => {
        const item = $(
          "<li><a><span class='full-title'></span><span class='short-title'></span></a></li>"
        );
        $('.full-title', item).text(locale.title);
        $('.short-title', item).text(locale.code.split('_')[0]);
        $('a', item)
          .attr('data-locale', locale.code)
          .attr('title', locale.title);
        $('.cms-fluent-selector-locales', selector).append(item);

        // Display selected locale
        if (locale.code === config.locale) {
          $('.text', selector).text(locale.title);
        }
      });

      this.prepend(selector);
    },
  });
});
