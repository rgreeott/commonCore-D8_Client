(function ($, Drupal) {
  "use strict";

  /**
   * @file
   * Integrates Dfm into textareas.
   */

  /**
   * Extend window.dfmEditor.
   */
  var DE = window.dfmEditor;

  /**
   * Creates a file insertion widget for a textarea.
   */
  DE.createTextareaWidget = function(textarea) {
    var $widget = $('<div class="dfm-textarea-widget" data-textarea-id="' + textarea.id + '"><a class="insert-image" data-type="image" href="#">' + Drupal.t('Insert image') + '</a><a class="insert-link" data-type="link" href="#">' + Drupal.t('Insert file link') + '</a></div>');
    $widget.find('a').click(DE.textareaWidgetClick);
    return $widget[0];
  };

  /**
   * Click handler for textarea widget links.
   */
  DE.textareaWidgetClick = function(e) {
    var q = 'textarea_id=' + encodeURIComponent(this.parentNode.getAttribute('data-textarea-id'));
    DE.open('dfmEditor.sendtoTextarea', this.getAttribute('data-type'), q);
    e.preventDefault();
  };

  /**
   * DFM file handler for textarea widget.
   */
  DE.sendtoTextarea = function(File, win) {
    var dfm = win.dfm;
    var T = $('#' + dfm.urlParam('textarea_id'))[0];
    if (T) {
      var img = dfm.urlParam('type') === 'image';
      var selected = dfm.getSelectedItems();
      var html = DE.filesHtml(selected, img, DE.getTextareaSelection(T));
      DE.setTextareaSelection(T, html);
    }
    win.close();
  };

  /**
   * Gets textarea selection
   */
  DE.getTextareaSelection = function(T) {
    return T.selectionStart !== undefined ? T.value.substring(T.selectionStart, T.selectionEnd) : (document.selection ? document.selection.createRange().text : '');
  };
  
  /**
   * Sets textarea selection
   */
  DE.setTextareaSelection = function(T, str) {
    try {
      T.focus();
      T.selectionStart !== undefined ? (T.value = T.value.substring(0, T.selectionStart) + str + T.value.substring(T.selectionEnd, T.value.length)) : (document.selection ? (document.selection.createRange().text = str) : (T.value += str));
    }
    catch(e){};
  };
  
  /**
   * Drupal behavior to process dfm textareas.
   */
  Drupal.behaviors.dfmTextarea = {attach: function(context) {
    var $textareas = $('.dfm-textarea', context).not('.dfm-textarea-processed').addClass('dfm-textarea-processed');
    var textarea;
    for (var i = 0; textarea = $textareas[i]; i++) {
      // Skip manually hidden textareas
      if (textarea.style.display !== 'none') {
        textarea.parentNode.appendChild(DE.createTextareaWidget(textarea));
      }
    }
  }};

})(jQuery, Drupal);
