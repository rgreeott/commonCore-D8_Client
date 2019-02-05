(function ($, Drupal) {
  "use strict";

  /**
   * @file
   * Provides methods for integrating Dfm into text editors.
   */
  
  /**
   * Global container for dfm editor integration methods.
   */
  var DE = window.dfmEditor = window.dfmEditor || {

    /**
     * Opens Dfm with a custom file handler.
     */
    open: function(handler, type, params) {
      var url = DE.url('fileHandler=' + handler + '&type=' + type + (params ? '&' + params : ''));
      return DE.openWindow(url);
    },

    /**
     * Returns dfm url.
     */
    url: function (params) {
      var url = Drupal.url('dfm');
      if (params) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + params;
      }
      return url;
    },

    /**
     * Opens a new window with an url.
     */
    openWindow: function(url, win) {
      var width = Math.min(1000, parseInt(screen.availWidth * 0.8));
      var height = Math.min(800, parseInt(screen.availHeight * 0.8));
      return (win || window).open(url, '', 'width=' + width + ',height=' + height + ',resizable=1');
    },

    /**
     * Returns image or file html for a list of file objects.
     */
    filesHtml: function (files, img, inner, joiner) {
      var method = img ? DE.imageHtml : DE.linkHtml;
      if (files.length == 1) {
        return method(files[0], inner);
      }
      var File;
      var items = [];
      for (var i = 0; i < files.length; i++) {
        File = files[i];
        if (!img || File.isImageSource()) {
          items.push(method(files[i]));
        }
      }
      return items.join(joiner || '\n');
    },

    /**
     * Returns image html for a file object.
     */
    imageHtml: function(File) {
      return '<img src="' + File.getUrl() + '"' + (File.width ? ' width="' + File.width + '"' : '') + (File.height ? ' height="' + File.height + '"' : '') + ' alt="' + File.formatName() + '" />';
    },

    /**
     * Returns link html for a file object.
     */
    linkHtml: function(File, inner) {
      return '<a href="' + File.getUrl() + '">' + (inner || File.formatName() + ' (' + File.formatSize() + ')') + '</a>';
    },

    /**
     * Processes an url input.
     */
    processUrlInput: function(el) {
      if (!el.id) el.id = 'dfm-url-input-' + (Math.random() + '').substr(2);
      var button = DE.createUrlButton(el.id, el.getAttribute('data-dfm-type') || 'link');
      el.parentNode.insertBefore(button, el);
      $(el).change(DE.eUrlInputChange);
    },

    /**
     * Creates an url input button.
     */
    createUrlButton: function(inputId, inputType) {
      var button = document.createElement('a');
      button.href = '#';
      button.className = 'dfm-url-button';
      button.innerHTML = '<span>' + Drupal.t('Select file') + '</span>';
      button.onclick = DE.eUrlButtonClick;
      button.InputId = inputId;
      button.InputType = inputType;
      return button;
    },

    /**
     * Click event of an url button.
     */
    eUrlButtonClick: function(e) {
      var id = this.InputId;
      // Focus on input before opening the window(can't change the focus on Firefox)
      $('#' + id).focus();
      DE.openWindow(DE.url('urlFieldId=' + id + '&type=' + this.InputType));
      return false;
    },

    /**
     * Change event of an url input.
     */
    eUrlInputChange: function(e) {
      // Image src input
      var name = this.name;
      var value = this.value;
      var re = /(^|[^a-z])src([^a-z]|$)/i;
      if (value && name && re.test(name)) {
        // Update alt input with image name.
        var altEl = this.form && this.form.elements[name.replace(re, '$1alt$2')];
        if (altEl && !altEl.value) {
          altEl.value = value.split('/').pop();
        }
      }
    }
  
  };

  /**
   * Drupal behavior to handle editor integration.
   */
  Drupal.behaviors.dfmEditor = {
    attach: function (context, settings) {
      var i;
      var el;
      var $els = $('.dfm-url-input', context).not('.dfmui-processed').addClass('dfmui-processed');
      for (i = 0; el = $els[i]; i++) {
        DE.processUrlInput(el);
      }
    }
  };

})(jQuery, Drupal);
