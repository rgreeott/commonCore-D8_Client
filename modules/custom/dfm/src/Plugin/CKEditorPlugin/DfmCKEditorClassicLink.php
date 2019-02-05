<?php

namespace Drupal\dfm\Plugin\CKEditorPlugin;

use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;

/**
 * Defines Dfm classic link plugin for CKEditor.
 *
 * @CKEditorPlugin(
 *   id = "dfm_classic_link",
 *   label = "Classic link dialog with Drupella File Manager"
 * )
 */
class DfmCKEditorClassicLink extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'dfm') . '/js/dfm.ckeditor.classic_link.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'dfm_classic_link' => [
        'label' => t('Classic Link dialog with Drupella File Manager. For full functionality you may want to enable "target, style, id, class, etc." attributes of "a" tag in Allowed HTML tags settings or disable html filtering completely. Do not enable for untrusted users!'),
        'image' => dfm_ckeditor_cdn('plugins/link/icons/link.png'),
      ],
      'dfm_classic_unlink' => [
        'label' => t('Classic unlink button complementary to the link button with Drupella File Manager.'),
        'image' => dfm_ckeditor_cdn('plugins/link/icons/unlink.png'),
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
