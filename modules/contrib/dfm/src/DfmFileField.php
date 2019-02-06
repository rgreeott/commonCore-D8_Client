<?php

namespace Drupal\dfm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Url;

/**
 * Defines methods for integrating Dfm into file field widgets.
 */
class DfmFileField {

  /**
   * Returns a list of supported widgets.
   */
  public static function supportedWidgets() {
    $widgets = &drupal_static(__FUNCTION__);
    if (!isset($widgets)) {
      $widgets = ['file_generic', 'image_image'];
      \Drupal::moduleHandler()->alter('dfm_supported_widgets', $widgets);
      $widgets = array_unique($widgets);
    }
    return $widgets;
  }

  /**
   * Checks if a widget is supported.
   */
  public static function isWidgetSupported(WidgetInterface $widget) {
    return in_array($widget->getPluginId(), static::supportedWidgets());
  }

  /**
   * Returns widget settings form.
   */
  public static function widgetSettingsForm(WidgetInterface $widget) {
    $form = [];
    if (static::isWidgetSupported($widget)) {
      $form['enabled'] = [
        '#type' => 'checkbox',
        '#title' => t('Allow users to select files from <a href=":url">Drupella File Manager</a> for this field.', [':url' => Url::fromRoute('dfm.admin')->toString()]),
        '#default_value' => $widget->getThirdPartySetting('dfm', 'enabled'),
      ];
    }
    return $form;
  }

  /**
   * Alters the summary of widget settings form.
   */
  public static function alterWidgetSettingsSummary(&$summary, $context) {
    $widget = $context['widget'];
    if (static::isWidgetSupported($widget)) {
      $status = $widget->getThirdPartySetting('dfm', 'enabled') ? t('Yes') : t('No');
      $summary[] = t('Dfm enabled: @status', ['@status' => $status]);
    }
  }

  /**
   * Processes widget form.
   */
  public static function processWidget($element, FormStateInterface $form_state, $form) {
    // Create url with initial location
    $options = [];
    if (!empty($element['#upload_location']) && $target = file_uri_target($element['#upload_location'])) {
      $options['query']['fallbackDir'] = $target;
    }
    $url = Url::fromRoute('dfm.page', ['scheme' => $element['#scheme']], $options)->toString();
    // Path input
    $element['dfm_paths'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'class' => ['dfm-filefield-paths'],
        'data-dfm-url' => $url,
      ],
      // Reset value to prevent consistent errors
      '#value' => '',
    ];
    // Library
    $element['#attached']['library'][] = 'dfm/drupal.dfm.filefield';
    // Set the pre-renderer to conditionally disable the elements.
    $element['#pre_render'][] = [get_called_class(), 'preRenderWidget'];
    return $element;
  }

  /**
   * Pre-renders widget form.
   */
  public static function preRenderWidget($element) {
    // Hide elements if there is already an uploaded file.
    if (!empty($element['#value']['fids'])) {
      $element['dfm_paths']['#access'] = FALSE;
    }
    return $element;
  }

  /**
   * Sets widget file id values by validating and processing the submitted data.
   * Runs before processor callbacks.
   */
  public static function setWidgetValue($element, &$input, FormStateInterface $form_state) {
    if (empty($input['dfm_paths'])) {
      return;
    }
    $paths = $input['dfm_paths'];
    $input['dfm_paths'] = '';
    // Remove excess data.
    $paths = array_unique(array_filter(explode(':', $paths)));
    if (isset($element['#cardinality']) && $element['#cardinality'] > -1) {
      $paths = array_slice($paths, 0, $element['#cardinality']);
    }
    // Check if paths are accessible by the current user with Dfm.
    if (!$uris = Dfm::checkFilePaths($paths, \Drupal::currentUser(), $element['#scheme'])) {
      return;
    }
    // Validate paths as file entities.
    $file_usage = \Drupal::service('file.usage');
    $errors = [];
    foreach ($uris as $uri) {
      // Get entity by uri
      $file = Dfm::getFileEntity($uri, TRUE);
      if ($new_errors = file_validate($file, $element['#upload_validators'])) {
        $errors = array_merge($errors, $new_errors);
      }
      else {
        // Save the file record.
        if ($file->isNew()) {
          $file->save();
        }
        if ($fid = $file->id()) {
          // Make sure the file has usage otherwise it will be denied.
          if (!$file_usage->listUsage($file)) {
            $file_usage->add($file, 'dfm', 'file', $fid);
          }
          $input['fids'][] = $fid;
        }
      }
    }
    // Set error messages.
    if ($errors) {
      $errors = array_unique($errors);
      if (count($errors) > 1) {
        $errors = ['#theme' => 'item_list', '#items' => $errors];
        $message = \Drupal::service('renderer')->render($errors);
      }
      else {
        $message = array_pop($errors);
      }
      // May break the widget flow if set as a form error.
      drupal_set_message($message, 'error');
    }
  }

}