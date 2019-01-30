(function ($, Drupal, CKEDITOR) {
  'use strict';

  /**
   * @file
   * Defines Dfm plugin for CKEditor.
   */

  /**
   * Extend window.dfmEditor.
   */
  var DE = window.dfmEditor || {};

  /**
   * Opens dfm for inserting images into CKEditor.
   */
  DE.ckeImage = function (E) {
    return DE.ckePopup(E, 'image');
  },

  /**
   * Opens dfm for inserting files into CKEditor.
   */
  DE.ckeLink = function (E) {
    return DE.ckePopup(E, 'link');
  };

  /**
   * Opens dfm for inserting files/images into CKEditor.
   */
  DE.ckePopup = function (E, type) {
    return DE.open('dfmEditor.sendtoCKEditor', type, 'cke_id=' + encodeURIComponent(E.name));
  };

  /**
   * Dfm sendto handler for inserting files/images into CKEditor.
   */
  DE.sendtoCKEditor = function (File, win) {
    var dfm = win.dfm;
    var E = CKEDITOR.instances[dfm.urlParam('cke_id')];
    if (E) {
      var img = dfm.urlParam('type') === 'image';
      var selected = dfm.getSelectedItems();
      var html = DE.filesHtml(selected, img, !img && DE.ckeSelectedHtml(E), '<br />');
      E.insertHtml(html);
    }
    win.close();
  };

  /**
   * Returns selected html from ckeditor.
   */
  DE.ckeSelectedHtml = function (editor) {
    var html = '';
    try {
      var range = editor.getSelection().getRanges()[0];
      var div = editor.document.createElement('div');
      div.append(range.cloneContents());
      html = div.getHtml();
    } catch(err) {}
    return html;
  };

  /**
   * Define the dfm buttons.
   */
  CKEDITOR.plugins.add('dfm', {
    init: function (E) {
      // Image
      E.addCommand('dfm_image', {exec: DE.ckeImage});
      E.ui.addButton('dfm_image', {
        label: Drupal.t('Insert images'),
        command: 'dfm_image'
      });
      // Link
      E.addCommand('dfm_link', {exec: DE.ckeLink});
      E.ui.addButton('dfm_link', {
        label: Drupal.t('Insert files'),
        command: 'dfm_link',
      });
    }
  });

})(jQuery, Drupal, CKEDITOR);
