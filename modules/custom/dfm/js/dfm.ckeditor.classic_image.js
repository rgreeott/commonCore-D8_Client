(function ($, Drupal, CKEDITOR) {
  'use strict';

  /**
   * @file
   * Defines Dfm classic image plugin for CKEditor.
   */

  CKEDITOR.plugins.add('dfm_classic_image', {
    requires: 'image',
    beforeInit: function (editor) {
      // Remove image2 reference to make image plugin work properly.
      var i2 = editor.plugins.image2;
      if (i2 && editor.plugins.image) {
        // Disable init functions for other references.
        i2.init = function(){};
        i2.afterInit = function(){};
        delete editor.plugins.image2;
      }
    },
    init: function (editor) {
      // Define the button.
      editor.ui.addButton('dfm_classic_image', {
        label: Drupal.t('Image'),
        command: 'image',
        icon: editor.plugins.image.path + 'icons/image.png'
      });
      // Set file browser url.
      var url = Drupal.url('dfm');
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'fileHandler=CKEDITOR.dfmClassicImageHandler&type=image';
      editor.config.filebrowserImageBrowseUrl = url;
    }
  });

  /**
   * Dfm file handler for classic image dialog.
   */
  CKEDITOR.dfmClassicImageHandler = function (File, win) {
    CKEDITOR.tools.callFunction(win.dfm.urlParam('CKEditorFuncNum'), File.getUrl());
    win.close();
  };

})(jQuery, Drupal, CKEDITOR);
