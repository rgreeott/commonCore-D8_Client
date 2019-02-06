<?php

namespace Drupal\dfm\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\user\RoleInterface;

/**
 * Dfm settings form.
 */
class DfmSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dfm_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dfm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dfm.settings');
    $form['roles_profiles'] = $this->buildRolesProfilesTable($config->get('roles_profiles', []));
    // Common settings container
    $form['common'] = [
      '#type' => 'details',
      '#title' => $this->t('Common settings'),
    ];
    $form['common']['merge_folders'] = [
      '#type' => 'checkbox',
      '#title' => t('Merge folders from multiple profiles'),
      '#description' => t('A user with multiple roles gets only the bottom most profile in the list. This option adds folders to the assigned profile from other matching profiles as well.'),
      '#default_value' => $config->get('merge_folders'),
    ];
    $form['common']['abs_urls'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable absolute URLs'),
      '#description' => t('Make the file manager return absolute file URLs to other applications.'),
      '#default_value' => $config->get('abs_urls'),
    ];
  // Integrated textareas
    $form['common']['textareas'] = [
      '#type' => 'textfield',
      '#title' => t('Enable image/link insertion into textareas'),
      '#default_value' => $config->get('textareas'),
      '#maxlength' => NULL,
      '#description' => t('Enter comma separated textarea IDs under which you want to enable a widget that allows to select files from Drupella FM and inserts the HTML code into the textarea. This widget works for only plain textareas without a WYSIWYG editor. ID of Body fields in most node types starts with <strong>edit-body*</strong>. The * character can be used as a wildcard.'),
    ];
    $form['#attached']['library'][] = 'dfm/drupal.dfm.admin';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dfm.settings');
    // Merge folders
    $config->set('merge_folders', $form_state->getValue('merge_folders'));
    // Absolute URLs
    $config->set('abs_urls', $form_state->getValue('abs_urls'));
    // Textareas
    $config->set('textareas', $form_state->getValue('textareas'));
    // Role-profile assignments.
    $old_roles_profiles = $config->get('roles_profiles');
    $roles_profiles = $form_state->getValue('roles_profiles');
    // Filter empty values
    foreach ($roles_profiles as $rid => &$profiles) {
      if (!$profiles = array_filter($profiles)) {
        unset($roles_profiles[$rid]); 
      }
    }
    $config->set('roles_profiles', $roles_profiles);
    $config->save();
    // Warn about anonymous access
    if (!empty($roles_profiles[RoleInterface::ANONYMOUS_ID])) {
      drupal_set_message(t('You have enabled anonymous access to the file manager. Please make sure this is not a misconfiguration.'), 'warning');
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns roles-profiles table.
   */
  public function buildRolesProfilesTable(array $roles_profiles) {
    $rp_table = ['#type' => 'table'];
    // Prepare roles
    $roles = user_roles();
    $wrappers = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    // Prepare profile options
    $options = ['' => '-' . $this->t('None') . '-'];
    foreach (\Drupal::entityTypeManager()->getStorage('dfm_profile')->loadMultiple() as $pid => $profile) {
      $options[$pid] = $profile->label();
    }
    // Build header
    $dfm_url = Url::fromRoute('dfm.page')->toString();
    $rp_table['#header'] = [$this->t('Role')];
    $default = file_default_scheme();
    foreach ($wrappers as $scheme => $name) {
      $url = $scheme === $default ? $dfm_url : $dfm_url . '/' . $scheme;
      $rp_table['#header'][]['data'] = ['#markup' => '<a href="' . $url . '">' . Html::escape($name) . '</a>'];
    }
    // Build rows
    foreach ($roles as $rid => $role) {
      $rp_table[$rid]['role_name'] = [
        '#plain_text' => $role->label(),
      ];
      foreach ($wrappers as $scheme => $name) {
        $rp_table[$rid][$scheme] = [
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => isset($roles_profiles[$rid][$scheme]) ? $roles_profiles[$rid][$scheme] : '',
        ];
      }
    }
    // Add description
    $rp_table['#prefix'] = '<h3>' . $this->t('Role-profile assignments') . '</h3>';
    $rp_table['#suffix'] = '<div class="description">' . $this->t('Assign configuration profiles to user roles for available file systems. Users with multiple roles get the bottom most profile.') . ' ' . $this->t('The default file system %name is accessible at :url path.', ['%name' => $wrappers[file_default_scheme()], ':url' => $dfm_url]) . '</div>';
    return $rp_table;
  }

}
