<?php

namespace Drupal\dfm\Plugin\BUEditorPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\bueditor\BUEditorPluginBase;
use Drupal\bueditor\Entity\BUEditorEditor;
use Drupal\bueditor\BUEditorToolbarWrapper;
use Drupal\dfm\Dfm;

/**
 * Defines Dfm as a BUEditor plugin.
 *
 * @BUEditorPlugin(
 *   id = "dfm",
 *   label = "Drupella File Manager"
 * )
 */
class DfmBUEditor extends BUEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'dfm_image' => $this->t('Insert images'),
      'dfm_link' => $this->t('Insert files'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterEditorJS(array &$js, BUEditorEditor $bueditor_editor, Editor $editor = NULL) {
    $toolbar = BUEditorToolbarWrapper::set($js['settings']['toolbar']);
    $buttons = ['dfm_image', 'dfm_link'];
    $has_button = $toolbar->hasAnyOf($buttons);
    $is_fb = isset($js['settings']['fileBrowser']) && $js['settings']['fileBrowser'] === 'dfm';
    $access = ($has_button || $is_fb) && Dfm::access();
    // Add library
    if ($access) {
      $js['libraries'][] = 'dfm/drupal.dfm.bueditor';
    }
    // Remove settings and buttons.
    else {
      if ($has_button) {
        $toolbar->remove($buttons);
      }
      if ($is_fb) {
        unset($js['settings']['fileBrowser']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterToolbarWidget(array &$widget) {
    // Include button definitions.
    $widget['libraries'][] = 'dfm/drupal.dfm.bueditor';
    // Add tooltips
    $widget['items']['dfm_image']['tooltip'] = $this->t('Insert images using Drupella File Manager');
    $widget['items']['dfm_link']['tooltip'] = $this->t('Insert file links using Drupella File Manager');
  }

  /**
   * {@inheritdoc}
   */
  public function alterEditorForm(array &$form, FormStateInterface $form_state, BUEditorEditor $bueditor_editor) {
    // Add dfm option to file browser field.
    $fb = &$form['settings']['fileBrowser'];
    $fb['#options']['dfm'] = $this->t('Drupella File Manager');
    // Add configuration link
    $form['settings']['dfm'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [':input[name="settings[fileBrowser]"]' => ['value' => 'dfm']],
      ],
      '#attributes' => [
        'class' => ['description'],
      ],
      'content' => [
        '#markup' => $this->t('Configure <a href=":url">Drupella File Manager</a>.', [':url' => Url::fromRoute('dfm.admin')->toString()])
      ],
    ];
    // Set weight
    if (isset($fb['#weight'])) {
      $form['settings']['dfm']['#weight'] = $fb['#weight'] + 0.1;
    }
  }

}
