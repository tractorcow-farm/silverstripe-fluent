/* eslint-env browser */
import liburl from 'url';
import queryString from 'query-string';

window.jQuery.entwine('ss', ($) => {
  /**
   * Get fluent config
   *
   * @return {Object}
   */
  const fluentConfig = () => {
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
  };

  /**
   * Determine the url to navigate to given the specified locale
   *
   * @param {String} url
   * @param {String} locale
   * @return {String}
   */
  const urlForLocale = (url, locale) => {
    // Get param from config
    const config = fluentConfig();
    if (!config.param) {
      return url;
    }

    // Manipulate using url / query-string libraries
    const urlObj = liburl.parse(url);
    const args = queryString.parse(urlObj.search);
    args[config.param] = locale;
    urlObj.search = queryString.stringify(args);
    return liburl.format(urlObj);
  };

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
     * Generate the locale selector when activated
     */
    onmatch() {
      this._super();
      const config = fluentConfig();
      // Skip if no locales defined
      if (typeof config.locales === 'undefined' || config.locales.length === 0) {
        return;
      }
      // Note: Remove c-select once admin upgraded to bootstrap v4.0.0-alpha.6
      const selector = $(
        `<div class='cms-fluent-selector font-icon font-icon-caret-up-down'>
          <select class='cms-fluent-selector-locales custom-select c-select'></select>
        </div>`
      );

      // Create options
      config.locales.forEach((locale) => {
        const item = $('<option />')
          .text(locale.title)
          .prop('value', locale.code);

        // Display selected locale
        if (locale.code === config.locale) {
          item.prop('selected', true);
        }

        $('select', selector).append(item);
      });
      this.prepend(selector);
    },
  });

  /**
   * Selector container
   */
  $('.cms-fluent-selector').entwine({
    /**
     * Show or hide the selector when clicked
     */
    onclick() {
      this.toggleClass('active');
    },
  });

  /**
   * Locale links
   */
  $('.cms-fluent-selector .cms-fluent-selector-locales').entwine({
    /**
     * Takes the user to the selected locale
     */
    onchange(event) {
      event.preventDefault();
      const locale = this.val();
      const url = urlForLocale(document.location.href, locale);

      // Load new URL
      window.location.href = url;
    },
  });
});
