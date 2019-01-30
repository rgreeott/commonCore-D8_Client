(function ($, Drupal, CKEDITOR) {
  'use strict';

  /**
   * @file
   * Defines Dfm link plugin for CKEditor.
   */

  CKEDITOR.plugins.add('dfm_classic_link', {
    requires: 'fakeobjects,link',
    init: function (editor) {
      // Define the buttons
      editor.ui.addButton('dfm_classic_link', {
        label: Drupal.t('Link'),
        command: 'link',
        icon: editor.plugins.link.path + 'icons/link.png'
      });
      editor.ui.addButton('dfm_classic_unlink', {
        label: Drupal.t('Unlink'),
        command: 'unlink',
        icon: editor.plugins.link.path + 'icons/unlink.png'
      });
      // Set file browser url.
      var url = Drupal.url('dfm');
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'fileHandler=CKEDITOR.dfmClassicLinkHandler&type=link';
      editor.config.filebrowserBrowseUrl = url;
    }
  });

  /**
   * Dfm file handler for classic link dialog.
   */
  CKEDITOR.dfmClassicLinkHandler = function (File, win) {
    CKEDITOR.tools.callFunction(win.dfm.urlParam('CKEditorFuncNum'), File.getUrl());
    win.close();
  };

})(jQuery, Drupal, CKEDITOR);
