<?php

namespace Drupal\dfm\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines Dfm classic image plugin for CKEditor.
 *
 * @CKEditorPlugin(
 *   id = "dfm_classic_image",
 *   label = "Classic image dialog with Drupella File Manager"
 * )
 */
class DfmCKEditorClassicImage extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'dfm') . '/js/dfm.ckeditor.classic_image.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'dfm_classic_image' => [
        'label' => t('Classic Image dialog with Drupella File Manager. For full functionality you may want to enable "style, id, and class, etc." attributes of "img" tag in Allowed HTML tags settings or disable html filtering completely. Do not enable for untrusted users!'),
        'image' => dfm_ckeditor_cdn('plugins/image/icons/image.png'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
