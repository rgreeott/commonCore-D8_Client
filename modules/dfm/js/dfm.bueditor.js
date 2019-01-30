/*global BUE:true*/
(function ($, Drupal, BUE) {
  'use strict';

  /**
   * @file
   * Defines Dfm plugin for BUEditor.
   */

  /**
   * Extend window.dfmEditor.
   */
  var DE = window.dfmEditor || {};

  /**
   * Opens dfm for inserting images into BUEditor.
   */
  DE.bueImage = function (E) {
    return DE.buePopup(E, 'image');
  },

  /**
   * Opens dfm for inserting files into BUEditor.
   */
  DE.bueLink = function (E) {
    return DE.buePopup(E, 'link');
  };

  /**
   * Opens dfm for inserting files/images into BUEditor.
   */
  DE.buePopup = function (E, type) {
    return DE.open('dfmEditor.sendtoBUEditor', type, 'bue_id=' + encodeURIComponent(E.id));
  };

  /**
   * Dfm sendto handler for inserting files/images into BUEditor.
   */
  DE.sendtoBUEditor = function (File, win) {
    var dfm = win.dfm;
    var E = BUE.getEditor(dfm.urlParam('bue_id'));
    if (E) {
      var img = dfm.urlParam('type') === 'image';
      var selected = dfm.getSelectedItems();
      var html = DE.filesHtml(selected, img, E.getSelection());
      E.setSelection(html);
    }
    win.close();
  };

  /**
   * Active form fields currently using the file browser.
   */
  DE.bueFields = [];

  /**
   * Dfm sendto handler for inserting a file url into a form field.
   */
  DE.sendtoBUEField = function (File, win) {
    var field;
    var id = win.dfm.urlParam('field_id');
    if (field = DE.bueFields[id]) {
      // Set field value
      field.value = File.getUrl();
      // Check other fields
      var input;
      var value;
      var values = {width: File.width, height: File.height, alt: File.formatName()};
      for (var name in values) {
        if (value = values[name]) {
          if (input = field.form.elements[name]) {
            input.value = value;
          }
        }
      }
      field.focus();
      DE.bueFields[id] = null;
    }
    win.close();
  };

  /**
   * Register buttons.
   */
  BUE.registerButtons('dfm', function() {
    return {
      dfm_image: {
        id: 'dfm_image',
        label: Drupal.t('Insert images'),
        cname: 'ficon-image',
        code: DE.bueImage
      },
      dfm_link: {
        id: 'dfm_link',
        label: Drupal.t('Insert files'),
        cname: 'ficon-link',
        code: DE.bueLink
      }
    };
  });

  /**
   * File browser handler for image/link dialogs.
   */
  BUE.fileBrowsers.dfm = function (field, type, E) {
    var field_id = DE.bueFields.length;
    DE.bueFields[field_id] = field;
    DE.open('dfmEditor.sendtoBUEField', type, 'field_id=' + field_id);
  };

})(jQuery, Drupal, BUE);
