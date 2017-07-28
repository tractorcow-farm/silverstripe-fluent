window.jQuery.entwine('ss', ($) => {
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
});
