<?php

namespace Drupal\dfm\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dfm\Dfm;

/**
 * Base form for Dfm Profile entities.
 */
class DfmProfileForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $dfm_profile = $this->getEntity();
    // Import configuration from the member profile for add operation
    if ($this->getOperation() === 'add') {
      if (!$dfm_profile->getConf() && $member_profile = $dfm_profile->load('member')) {
        $dfm_profile->set('conf', $member_profile->getConf());
      }
    }
    // Check duplication
    elseif ($this->getOperation() === 'duplicate') {
      $dfm_profile = $dfm_profile->createDuplicate();
      $dfm_profile->set('label', $this->t('Duplicate of @label', ['@label' => $dfm_profile->label()]));
      $this->setEntity($dfm_profile);
    }
    // Label
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $dfm_profile->label(),
      '#maxlength' => 64,
      '#required' => TRUE,
      '#weight' => -20,
    ];
    // Id
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [get_class($dfm_profile), 'load'],
        'source' => ['label'],
      ],
      '#default_value' => $dfm_profile->id(),
      '#maxlength' => 32,
      '#required' => TRUE,
      '#weight' => -20,
    ];
    // Description
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $dfm_profile->get('description'),
      '#weight' => -10,
    ];
    // Conf
    $conf = [
      '#tree' => TRUE,
    ];
    // Extensions
    $conf['uploadExtensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $dfm_profile->getConf('uploadExtensions'),
      '#maxlength' => 255,
      '#description' => $this->t('Separate extensions with a space or comma and do not include the leading dot.') . ' ' . $this->t('Set to * to allow all extensions.'),
      '#weight' => -9,
    ];
    // File size
    $maxsize = file_upload_max_size();
    $conf['uploadMaxSize'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => ceil($maxsize/1024/1024),
      '#step' => 'any',
      '#size' => 8,
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $dfm_profile->getConf('uploadMaxSize'),
      '#description' => $this->t('Maximum allowed file size per upload.') . ' ' . t('Your PHP settings limit the upload size to %size.', ['%size' => format_size($maxsize)]) . ' ' . $this->t('Set to 0 for no limit.'),
      '#field_suffix' => $this->t('MB'),
      '#weight' => -8,
    ];
    // Quota
    $conf['uploadQuota'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 'any',
      '#size' => 8,
      '#title' => $this->t('Disk quota'),
      '#default_value' => $dfm_profile->getConf('uploadQuota'),
      '#description' => $this->t('Maximum disk space that can be allocated by a user.') . ' ' . $this->t('Set to 0 for no limit.'),
      '#field_suffix' => $this->t('MB'),
      '#weight' => -7,
    ];
    // Image dimensions
    $conf['imgMaxDim'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum image dimensions'),
      '#default_value' => $dfm_profile->getConf('imgMaxDim'),
      '#description' => $this->t('Images exceeding this value will be scaled down to fit.') . ' ' . $this->t('Leave empty for no limit.'),
      '#field_suffix' => '<kbd>' . $this->t('WIDTHxHEIGHT') . '</kbd>',
      '#weight' => -6,
    ];
    // Search settings
    $conf['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search settings'),
      '#open' => $dfm_profile->getConf('searchOn'),
      // Descendents will inherit this.
      '#parents' => ['conf'],
      '#weight' => -5,
    ];
    $conf['search']['searchOn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable file search'),
      '#default_value' => $dfm_profile->getConf('searchOn'),
    ];
    $conf['search']['searchLimit'] = [
      '#type' => 'number',
      '#title' => $this->t('Search result limit'),
      '#default_value' => $dfm_profile->getConf('searchLimit'),
      '#min' => 0,
      '#description' => $this->t('For performance reasons you may want to limit the number of results returned from the server to the client. Searching files on server side will stop when the limit is reached. This will not affect client side searching that is performed on cached items.') . ' ' . $this->t('Set to 0 for no limit.'),
      '#states' => [
        'visible' => [
          '#edit-conf-searchon' => ['checked' => TRUE],
        ],
      ],
    ];
    // Advanced settings
    $conf['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
      '#parents' => ['conf'],
      '#weight' => 9,
    ];
    // Image thumbnails
    if (function_exists('image_style_options')) {
      $conf['advanced']['thumbStyle'] = [
        '#type' => 'select',
        '#title' => $this->t('Show image icons as thumbnails'),
        '#options' => image_style_options(),
        '#description' => $this->t('Select a thumbnail style from the list to make the file browser display icons of image files as thumbnails(32x32).'),
        '#default_value' => $dfm_profile->getConf('thumbStyle'),
      ];
    }
    // Chroot jail
    $conf['advanced']['chrootJail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable chroot jail'),
      '#description' => $this->t("This option sets the user's top-most folder as the root of folder tree. So, instead of the tree structure home>users>user42 there will be only home folder pointing to users/user42. Note that, this won't work for multiple top-most folders generating different branches."),
      '#default_value' => $dfm_profile->getConf('chrootJail'),
    ];
    // Ignore file usage
    $conf['advanced']['ignoreFileUsage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore file usage'),
      '#description' => $this->t('By default the file manager avoids deletion or overwriting of files that are in use by other Drupal modules. Enabling this option skips the file usage check. Not recommended!'),
      '#default_value' => $dfm_profile->getConf('ignoreFileUsage'),
    ];
    // Image copy
    $conf['advanced']['imgCopy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable image copy'),
      '#description' => $this->t('Allow image operations to be performed optionally on a copy instead of the original image regardless of the file copy permission. A new option will appear in image widgets allowing to work on a copy.'),
      '#default_value' => $dfm_profile->getConf('imgCopy'),
    ];
    // Image upscale
    $conf['advanced']['imgUpscale'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable image upscaling'),
      '#description' => $this->t('Allow image dimensions to be set to higher values than the original ones during resizing. Maximum allowed dimensions restriction is still applied.'),
      '#default_value' => $dfm_profile->getConf('imgUpscale'),
    ];
    // Disable auto-scale on upload
    $conf['advanced']['uploadNoScale'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable auto-scale'),
      '#description' => $this->t('Disable scaling images down to the maximum allowed dimensions during upload. The ones bigger than the allowed dimensions will be rejected.'),
      '#default_value' => $dfm_profile->getConf('uploadNoScale'),
    ];
    // Image extensions
    $conf['advanced']['imgExtensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image extensions'),
      '#maxlength' => 255,
      '#description' => $this->t('You can disable image handling by leaving this field empty. It can drastically increase browsing performance for remote(S3, etc.) directories with lots of images.'),
      '#default_value' => $dfm_profile->getConf('imgExtensions', 'jpg jpeg png gif'),
    ];
    // Fix body field on move
    $conf['advanced']['fixBodyOnMove'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fix urls in body fields on file move'),
      '#description' => $this->t('When files/folders are moved or renamed hard-coded URLs in body fields are not updated. Check this box if you want DFM try fixing them. Note that this requires some expensive database queries which may slow down move and rename operations.'),
      '#default_value' => $dfm_profile->getConf('fixBodyOnMove'),
    ];
    // Folders
    $conf['dirConf'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Folders'),
      'description' => ['#markup' => '<div class="description">' . $this->t('You can use user tokens and global tokens in folder paths, e.g. @tokens.', ['@tokens' => '[user:uid], [user:name], [date:custom:Y-m], [site:name]']) . '</div>'],
      '#weight' => 10,
    ];
    $folders = $dfm_profile->getConf('dirConf', []);
    $parents = ['conf', 'dirConf'];
    $index = 0;
    foreach ($folders as $folder) {
      $conf['dirConf'][$index] = $this->folderForm(array_merge($parents, [$index]), $folder);
      $index++;
    }
    $conf['dirConf'][$index] = $this->folderForm(array_merge($parents, [$index]));
    $conf['dirConf'][$index+1] = $this->folderForm(array_merge($parents, [$index+1]));
    $form['conf'] = $conf;
    // Add library
    $form['#attached']['library'][] = 'dfm/drupal.dfm.admin';
    return parent::form($form, $form_state);
  }

  /**
   * Returns folder form elements.
   */
  public function folderForm(array $parents, array $values = []) {
    $values += ['dirname' => '', 'perms' => [], 'subdirConf' => []];
    $form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['folder-container']],
      '#parents' => $parents,
    ];
    $form['dirname'] = [
      '#type' => 'textfield',
      '#default_value' => $values['dirname'],
      '#field_prefix' => '&lt;' . $this->t('root') . '&gt;' . '/',
    ];
    $form += $this->folderFormElements($parents, $values);
    $form['subdirConf'] = $this->folderFormElements(array_merge($parents, ['subdirConf']), $values['subdirConf'], TRUE);
    return $form;
  }

  /**
   * Returns configuration form elements for a (sub)directory.
   */
  public function folderFormElements(array $parents, array $values = [], $is_sub = FALSE) {
    $values += ['perms' => [], 'subdirConf' => []];
    // Permissions
    $form['perms'] = [
      '#type' => 'details',
      '#title' => $this->t('Permissions'),
      '#open' => !!array_filter($values['perms']),
      '#attributes' => ['class' => ['folder-permissions']],
    ];
    $form['perms'] += $this->permForm(array_merge($parents, ['perms']), $values['perms']);
    // Subdir inheritance. Place under perms and set the correct parents.
    $form['perms']['inherit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply the same file/folder permissions to all subfolders recursively'),
      '#default_value' => !empty($values['subdirConf']['inherit']),
      '#parents' => array_merge($parents, ['subdirConf', 'inherit']),
    ];
    // Subfolder
    if ($is_sub) {
      $form['perms']['#attributes']['class'][] = 'subfolder-permissions';
      $form['perms']['#title'] = t('Subfolder permissions');
      $form['perms']['#states']['invisible']['input[name="' . $this->parentsFormName($parents) . '[inherit]"]']['checked'] = TRUE;
    }
    return $form;
  }

  /**
   * Returns permissions form elements.
   */
  public function permForm(array $parents, array $values = []) {
    $perm_info = $this->permInfo();
    $labels = ['file' => $this->t('File permissions'), 'folder' => $this->t('Folder permissions')];
    $name_prefix = $this->parentsFormName($parents);
    $form['#parents'] = $parents;
    $form['all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('All permissions'),
      '#default_value' => !empty($values['all']),
    ];
    foreach ($perm_info as $type => $perms) {
      $form[$type] = [
        '#type' => 'fieldset',
        '#title' => $labels[$type],
        '#attributes' => ['class' => ['permission-group pg-' . $type]],
        '#parents' => $parents,
      ];
      $form[$type]['#states']['invisible']['input[name="' . $name_prefix . '[all]"]']['checked'] = TRUE;
      foreach ($perms as $perm => $title) {
        $form[$type][$perm] = [
          '#type' => 'checkbox',
          '#title' => $title,
          '#default_value' => !empty($values[$perm]),
        ];
        $form[$type][$perm]['#states']['disabled']['input[name="' . $name_prefix . '[all]"]']['checked'] = TRUE;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $conf = &$form_state->getValue('conf');
    // Check image max dimensions
    if (!empty($conf['imgMaxDim']) && !preg_match('/^\d+x\d+$/', $conf['imgMaxDim'])) {
      return $form_state->setError($form['conf']['imgMaxDim'],  $this->t('Dimensions must be specified as <kbd>WIDTHxHEIGHT</kbd>.'));
    }
    // Check folders
    $dirs = [];
    foreach ($conf['dirConf'] as $i => $dirconf) {
      $dirname = trim($dirconf['dirname']);
      // Empty path
      if ($dirname === '') {
        continue;
      }
      // Validate path
      if (!Dfm::regularPath($dirname)) {
        return $form_state->setError($form['conf']['dirConf'][$i]['dirname'], $this->t('Invalid folder path.'));
      }
      // Remove empty conf
      if (empty($dirconf['subdirConf']['subdirConf']['inherit'])) {
        unset($dirconf['subdirConf']['subdirConf']);
      }
      // Sub permissions
      if (!$dirconf['subdirConf']['perms'] = array_filter($dirconf['subdirConf']['perms'])) {
        unset($dirconf['subdirConf']['perms']);
      }
      // Sub conf
      if (!$dirconf['subdirConf'] = array_filter($dirconf['subdirConf'])) {
        unset($dirconf['subdirConf']);
      }
      // Sub conf inheritance. No need for perms if inheritance is checked.
      else if (!empty($dirconf['subdirConf']['inherit'])) {
        unset($dirconf['subdirConf']['perms'], $dirconf['subdirConf']['subdirConf']);
      }
      // Permissions
      if (!$dirconf['perms'] = array_filter($dirconf['perms'])) {
        unset($dirconf['perms']);
        // No need for inherited subconf if there is no perm to inherit
        if (!empty($dirconf['subdirConf']['inherit'])) {
          unset($dirconf['subdirConf']);
        }
      }
      $dirs[$dirname] = $dirconf;
    }
    // No valid folders
    if (!$dirs) {
      return $form_state->setError($form['conf']['dirConf'][0]['dirname'], $this->t('You must define a folder.'));
    }
    $conf['dirConf'] = array_values($dirs);
    // Unset empty variables that normally default to false.
    foreach (['thumbStyle', 'chrootJail', 'ignoreFileUsage', 'imgCopy', 'imgUpscale', 'uploadNoScale', 'fixBodyOnMove'] as $key) {
      if (isset($conf[$key]) && !$conf[$key]) {
        unset($conf[$key]);
      }
    }
    // Unset default image extensions
    if (isset($conf['imgExtensions']) && $conf['imgExtensions'] === 'jpg jpeg png gif') {
      unset($conf['imgExtensions']);
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $dfm_profile = $this->getEntity();
    $status = $dfm_profile->save();
    if ($status == SAVED_NEW) {
      drupal_set_message($this->t('Profile %name has been added.', ['%name' => $dfm_profile->label()]));
    }
    elseif ($status == SAVED_UPDATED) {
      drupal_set_message($this->t('The changes have been saved.'));
    }
    $form_state->setRedirect('entity.dfm_profile.edit_form', ['dfm_profile' => $dfm_profile->id()]);
  }

  /**
   * Returns folder permission definitions.
   */
  public static function permInfo() {
    $info = &drupal_static(__METHOD__);
    if (!isset($info)) {
      $info = [
        'file' => [
          'listFiles' => t('List files'),
          'uploadFiles' => t('Upload files'),
          'deleteFiles' => t('Delete files'),
          'renameFiles' => t('Rename files'),
          'moveFiles' => t('Move files'),
          'copyFiles' => t('Copy files'),
          'downloadFiles' => t('Download files'),
          'resize' => t('Resize images'),
          'crop' => t('Crop images'),
        ],
        'folder' => [
          'listFolders' => t('List folders'),
          'createFolders' => t('Create folders'),
          'deleteFolders' => t('Delete folders'),
          'renameFolders' => t('Rename folders'),
          'moveFolders' => t('Move folders'),
          'copyFolders' => t('Copy folders'),
          'downloadFolders' => t('Download folders'),
        ],
      ];
      \Drupal::moduleHandler()->alter('dfm_perm_info', $info);
    }
    return $info;
  }

  /**
   * Returns form name derived from form parents.
   */
  public static function parentsFormName(array $parents) {
    $name = array_shift($parents);
    if ($parents) {
      $name .= '[' . implode('][', $parents) . ']';
    }
    return $name;
  }

}
