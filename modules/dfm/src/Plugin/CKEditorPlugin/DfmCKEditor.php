<?php

namespace Drupal\dfm\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines Dfm plugin for CKEditor.
 *
 * @CKEditorPlugin(
 *   id = "dfm",
 *   label = "Drupella File Manager"
 * )
 */
class DfmCKEditor extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'dfm') . '/js/dfm.ckeditor.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return ['dfm/drupal.dfm.ckeditor'];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = drupal_get_path('module', 'dfm') . '/css/images';
    return [
      'dfm_image' => [
        'label' => t('Insert images using Drupella File Manager'),
        'image' => $path . '/image.png',
      ],
      'dfm_link' => [
        'label' => t('Insert file links using Drupella File Manager'),
        'image' => $path . '/file.png',
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
