(function ($, Drupal) {
  "use strict";

  /**
   * @file
   * Integrates Dfm into file field widgets.
   */

  /**
   * Global container for helper methods.
   */
  var dfmFileField = window.dfmFileField = {};

  /**
   * Drupal behavior to handle dfm file field integration.
   */
  Drupal.behaviors.dfmFileField = {
    attach: function (context, settings) {
      var i;
      var el;
      var $els = $('.dfm-filefield-paths', context).not('.dff-processed').addClass('dff-processed');
      for (i = 0; el = $els[i]; i++) {
        dfmFileField.processInput(el);
      }
    }
  };

  /**
   * Processes a dfm file field input to create a widget.
   */
  dfmFileField.processInput = function (el) {
    var widget;
    var url = el.getAttribute('data-dfm-url');
    var fieldId = el.getAttribute('data-drupal-selector').split('-dfm-paths')[0];
    if (url && fieldId) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'fileHandler=dfmFileField.sendto&fieldId=' + fieldId;
      widget = $(dfmFileField.createWidget(url)).insertBefore(el.parentNode)[0];
      widget.parentNode.className += ' dfm-filefield-parent';
    }
    return widget;
  };

  /**
   * Creates a dfm file field widget with the given url.
   */
  dfmFileField.createWidget = function (url) {
    var $link = $('<a class="dfm-filefield-link"><span>' + Drupal.t('Select file') + '</span></a>');
    $link.attr('href', url).click(dfmFileField.eLinkClick);
    return $('<div class="dfm-filefield-widget"></div>').append($link)[0];
  };

  /**
   * Click event for the browser link.
   */
  dfmFileField.eLinkClick = function (e) {
    window.open(this.href, '', 'width=780,height=520,resizable=1');
    e.preventDefault();
  };

  /**
   * Handler for dfm sendto operation.
   */
  dfmFileField.sendto = function (File, win) {
    var dfm = win.dfm;
    var items = dfm.getSelectedItems();
    var fieldId = dfm.urlParam('fieldId');
    var exts = dfmFileField.getFieldExts(fieldId);
    var paths = [];
    // Prepare paths
    for (var i = 0; i < items.length; i++) {
      // Check extensions
      if (exts && !dfm.uploadValidateFileExt(items[i], exts)) {
        return;
      }
      paths.push(items[i].getPath());
    }
    // Submit form with selected item paths
    dfmFileField.submit(fieldId, paths);
    win.close();
  };


  /**
   * Returns allowed extensions for a field.
   */
  dfmFileField.getFieldExts = function (fieldId) {
    var settings = drupalSettings.file;
    var elements = settings && settings.elements;
    return elements ? elements['#' + fieldId] : false;
  };

  /**
   * Submits a field widget with selected file paths.
   */
  dfmFileField.submit = function (fieldId, paths) {
    $('[data-drupal-selector="' + fieldId + '-dfm-paths"]').val(paths.join(':'));
    $('[data-drupal-selector="' + fieldId + '-upload-button"]').mousedown();
  };

})(jQuery, Drupal);
