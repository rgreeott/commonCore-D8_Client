<?php

namespace Drupal\dfm;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Site\Settings;
use Drupal\Core\Database\Database;
use Drupal\Component\Render\MarkupInterface;

/**
 * Dfm Drupal Plugin.
 */
class DfmDrupal {

  /**
   * Plugin callback: register.
   */
  public static function register($dfm) {
    $class = get_called_class();
    // Set a pre load callback
    $dfm->registerHook('ajax_load', "$class::ajaxLoad");
    // Response alter
    $dfm->registerHook('response_alter', "$class::responseAlter");
    // Upload
    $dfm->registerHook('upload_file_validate', "$class::uploadFileValidate");
    $dfm->registerHook('upload_file', "$class::uploadFile");
    // Delete
    $dfm->registerHook('delete_filepath_validate', "$class::deleteFilepathValidate");
    $dfm->registerHook('delete_filepath', "$class::deleteFilepath");
    // Rename
    $dfm->registerHook('rename_filepath', "$class::renameFilepath");
    $dfm->registerHook('rename_dirpath', "$class::renameDirpath");
    // Move
    $dfm->registerHook('move_filepath_validate', "$class::moveFilepathValidate");
    $dfm->registerHook('move_filepath', "$class::moveFilepath");
    $dfm->registerHook('move_dirpath', "$class::moveDirpath");
    // Copy
    $dfm->registerHook('copy_filepath_validate', "$class::copyFilepathValidate");
    $dfm->registerHook('copy_filepath', "$class::copyFilepath");
    // Resize
    $dfm->registerHook('resize_filepath_validate', "$class::resizeFilepathValidate");
    $dfm->registerHook('resize_filepath', "$class::resizeFilepath");
    // Crop. Resize handlers will also work for crop.
    $dfm->registerHook('crop_filepath_validate', "$class::resizeFilepathValidate");
    $dfm->registerHook('crop_filepath', "$class::resizeFilepath");
    // Allow registration altering.
    \Drupal::moduleHandler()->alter('dfm_register', $dfm);
    // Set a post load callback to save translations.
    $dfm->registerHook('ajax_load', "$class::postAjaxLoad");
  }

  /**
   * Plugin callback: ajax_load.
   */
  public static function ajaxLoad($dfm) {
    // Translate strings if locale or custom strings is enabled.
    $lang = $dfm->getConf('lang');
    if (!$lang || ($lang === 'en' || !\Drupal::moduleHandler()->moduleExists('locale')) && !Settings::get('locale_custom_strings_' . $lang, FALSE)) {
      return;
    }
    // Add a flag indicating Drupal translation is on.
    $dfm->setConf('drupalI18n', TRUE);
    $jsdir = 'public://js';
    $jspath = $jsdir . '/dfm.' . $lang . '.js';
    // Translation file was created before. Just add it to page.
    if (file_exists($jspath)) {
      if ($jsurl = file_create_url($jspath)) {
        $dfm->addJs($jsurl);
      }
      return;
    }
    // Generate the list of tranlation strings.
    // We'll eventually create a translation file even if there is no translation strings.
    // Set empty strings to make sure $dfm->getConf('drupalStrings') evaluates to TRUE in if statements
    $strs = ['' => ''];
    // Add strings from the template if there is no default translation file for the lang
    if (!file_exists($dfm->scriptPath('i18n/dfm.' . $lang . '.js'))) {
      if ($content = file_get_contents($dfm->scriptPath('i18n/dfm.template.js'))) {
        preg_match_all("@\n'((?:\\\\.|[^'])+)':@", $content, $matches);
        if (!empty($matches[1])) {
          foreach ($matches[1] as $str) {
            $strs[$str] = t($str);
          }
        }
      }
    }
    // Set strings as a variable so modules can add their own
    $dfm->setConf('drupalStrings', $strs);
  }

  /**
   * Plugin callback: post ajax_load.
   */
  public static function postAjaxLoad($dfm) {
    // Save drupal strings to a file
    if ($strs = $dfm->getConf('drupalStrings')) {
      $lines = [];
      foreach ($strs as $str => $t) {
        if ($str != $t) {
          $lines[$str] = "'$str':'$t'";
        }
      }
      $jsdir = 'public://js';
      if (file_prepare_directory($jsdir, FILE_CREATE_DIRECTORY)) {
        $jspath = $jsdir . '/dfm.' . $dfm->getConf('lang') . '.js';
        if (@file_put_contents($jspath, 'dfm.extend(dfm.i18n, {' . implode(',', $lines) . '});')) {
          \Drupal::service('file_system')->chmod($jspath);
          if ($jsurl = file_create_url($jspath)) {
            $dfm->addJs($jsurl);
          }
        }
      }
    }
  }

  /**
   * Plugin callback: response_alter.
   */
  public static function responseAlter($dfm) {
    // Convert drupal messages to DFM messages
    if ($messages = drupal_get_messages()) {
      foreach ($messages as $type => $arr) {
        foreach ($arr as $message) {
          $dfm->setMessage($message instanceof MarkupInterface ? $message . '' : Html::escape($message), [], $type);
        }
      }
    }
  }


  /**
   * Plugin callback: upload_file_validate.
   */
  public static function uploadFileValidate($dfm, $file, $existing) {
    $file->uri = $file->filepath;
    $file->uid = $dfm->getConf('drupalUid');
    // Validate overwrite. Track diff in filesize for quota validation.
    $diff = 0;
    if ($existing && !static::validateOverwrite($dfm, $file->destination, $diff)) {
      return FALSE;
    }
    // Validate quota
    if (!static::validateQuota($dfm, $file, $diff)) {
      return FALSE;
    }
    // Invoke hook_file_validate
    $file->status = 0;
    $file->filemime = \Drupal::service('file.mime_type.guesser')->guess($file->filename);
    if ($errors = file_validate(Dfm::createFileEntity((array) $file))) {
      foreach ($errors as $error) {
        $dfm->setMessage($error);
      }
      return FALSE;
    }
    return TRUE;
  }
  
  /**
   * Plugin callback: upload_file.
   */
  public static function uploadFile($dfm, $file, $existing) {
    $file->uri = $file->filepath;
    $file->status = 1;
    $file->jsprops['date'] = $file->created = time();
    // Include file id in response variables as it may be helpful to some apps.
    $file->jsprops['fid'] = static::saveFileRecord($dfm, $file)->id();
  }


  /**
   * Plugin callback: delete_filepath_validate.
   */
  public static function deleteFilepathValidate($dfm, $filepath) {
    if ($file = Dfm::getFileEntity($filepath)) {
      if (!$dfm->getConf('ignoreFileUsage') && Dfm::getFileUsage($file)) {
        $dfm->setMessage(t('%filename is in use by another application.', ['%filename' => $file->getFilename()]));
        return FALSE;
      }
      // Skip physical file deletion.
      return 'skip';
    }
    return TRUE;
  }
  
  /**
   * Plugin callback: delete_filepath.
   */
  public static function deleteFilepath($dfm, $filepath) {
    if ($file = Dfm::getFileEntity($filepath)) {
      $file->delete();
    }
  }


  /**
   * Plugin callback: rename_filepath.
   */
  public static function renameFilepath($dfm, $filepath, $newpath) {
    static::renameUriRecord($filepath, $newpath);
    static::checkFixFileMove($dfm, $filepath, $newpath);
  }

  /**
   * Plugin callback: rename_dirpath.
   */
  public static function renameDirpath($dfm, $dirpath, $newpath) {
    // @todo: other storage types? Load all files and update one by one?
    $db = Database::getConnection();
    $db->update('file_managed')
      ->expression('uri', 'CONCAT(:newpath, SUBSTR(uri, ' . Unicode::strlen($dirpath . '/') . '))', [':newpath' => $newpath])
      ->condition('uri', $db->escapeLike($dirpath) . '/%', 'LIKE')
      ->execute();
    static::checkFixFileMove($dfm, $dirpath, $newpath, TRUE);
  }


  /**
   * Plugin callback: move_filepath_validate.
   */
  public static function moveFilepathValidate($dfm, $filepath, $newpath, $existing) {
    // It is simply a rename op if not overwritten
    return !$existing || static::validateOverwrite($dfm, $newpath);
  }

  /**
   * Plugin callback: move_filepath.
   */
  public static function moveFilepath($dfm, $filepath, $newpath, $existing) {
    // Delete the overwritten file's record.
    if ($existing) {
      static::deleteUriRecord($newpath);
    }
    // Update the moved file's record.
    static::renameUriRecord($filepath, $newpath);
    static::checkFixFileMove($dfm, $filepath, $newpath);
  }

  /**
   * Plugin callback: move_dirpath.
   */
  public static function moveDirpath($dfm, $dirpath, $newpath, $existing) {
    // It is simply a rename operation if not overwritten
    if (!$existing) {
      static::renameDirpath($dfm, $dirpath, $newpath);
    }
    static::checkFixFileMove($dfm, $dirpath, $newpath, TRUE);
  }


  /**
   * Plugin callback: copy_filepath_validate.
   */
  public static function copyFilepathValidate($dfm, $filepath, $newpath, $existing) {
    // Validate overwrite. Track diff in filesize for quota validation.
    $diff = 0;
    if ($existing && !static::validateOverwrite($dfm, $newpath, $diff)) {
      return FALSE;
    }
    // Skip all validations except quota as this is a copy operation.
    $file = (object) [
      'uid' => $dfm->getConf('drupalUid'),
      'filename' => $dfm->basename($newpath),
      'filesize' => filesize($filepath),
    ];
    return static::validateQuota($dfm, $file, $diff);
  }

  /**
   * Plugin callback: copy_filepath.
   */
  public static function copyFilepath($dfm, $filepath, $newpath, $existing) {
    static::saveFileRecord($dfm, $newpath);
  }


  /**
   * Plugin callback: resize_filepath_validate.
   */
  public static function resizeFilepathValidate($dfm, $filepath, $newpath, $params) {
    return !$params['existing'] || static::validateOverwrite($dfm, $newpath);
  }

  /**
   * Plugin callback: resize_filepath.
   */
  public static function resizeFilepath($dfm, $filepath, $newpath, $params) {
    // Existing
    if ($params['existing']) {
      if ($file = Dfm::getFileEntity($newpath)) {
        // File::preSave() updates the size.
        $file->save();
      }
    }
    // New
    else {
      static::saveFileRecord($dfm, $newpath, TRUE);
    }
  }



  /**
   * Saves a dfm file.
   * May use an existing record by checking the file uri.
   */
  public static function saveFileRecord($dfm, $file, $skipcheck = FALSE) {
    // Check uri
    if (is_string($file)) {
      $file = [
        'uri' => $file,
        'uid' => $dfm->getConf('drupalUid'),
        'created' => time(),
        'status' => 1,
        'dfm' => 1,
      ];
    }
    $file = Dfm::createFileEntity((array) $file);
    // Check existing record
    if (!$skipcheck && $original = Dfm::getFileEntity($file->getFileUri())) {
      $file->setOriginalId($file->fid = $original->id());
    }
    $file->save();
    return $file;
  }

  /**
   * Deletes file records of an uri.
   */
  public static function deleteUriRecord($uri) {
    if ($file = Dfm::getFileEntity($uri)) {
      // Prevent deletion from the disk. @todo suppress 'file not exist' log message.
      if ($exists = file_exists($uri)) {
        $newuri = $uri . microtime(TRUE);
        rename($uri, $newuri);
        try {
          $file->delete();
        }catch (Exception $e) {}
        rename($newuri, $uri);
      }
      else {
        $file->delete();
      }
    }
    return $file;
  }

  /**
   * Renames file records of an uri.
   */
  public static function renameUriRecord($uri, $newuri) {
    if ($file = Dfm::getFileEntity($uri)) {
      $file->setFileUri($newuri);
      $file->setFilename(\Drupal::service('file_system')->basename($newuri));
      $file->save();
    }
    return $file;
  }

  /**
   * Validates disk quota.
   */
  public static function validateQuota($dfm, $file, $extra = 0) {
    if ($quota = $dfm->getConf('uploadQuota')) {
      $used = \Drupal::entityTypeManager()->getStorage('file')->spaceUsed($file->uid);
      if (($used + $file->filesize + $extra) > $quota) {
        $dfm->setMessage(t("There isn't sufficient disk quota (%used/%quota) to save %filename (%filesize).", ['%filename' => $file->filename, '%filesize' => format_size($file->filesize), '%used' => format_size($used), '%quota' => format_size($quota)]));
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates file overwrite by checking file usage.
   */
  public static function validateOverwrite($dfm, $destination, &$diff = NULL) {
    if ($destfile = Dfm::getFileEntity($destination)) {
      if (!$dfm->getConf('ignoreFileUsage') && Dfm::getFileUsage($destfile)) {
        $dfm->setMessage(t('The destination %filename is in use by another application and can not be overwritten.', ['%filename' => $destfile->getFilename()]));
        return FALSE;
      }
      // Take the overwritten size into account only if it belongs to the same user.
      if (isset($diff) && $destfile->getOwnerId() == $dfm->getConf('drupalUid')) {
        $diff -= $destfile->getSize();
      }
    }
    return TRUE;
  }

  /**
   * Update body of nodes/blocks on file move/rename
   */
  public static function checkFixFileMove($dfm, $filepath, $newpath, $isdir = FALSE) {
    try {
      // Update body of nodes/blocks
      if ($dfm->getConf('fixBodyOnMove')) {
        if ($url = file_create_url($filepath)) {
          if ($newurl = file_create_url($newpath)) {
            // Fix Absolute urls
            static::fixFileMove($url, $newurl, $isdir);
            // Fix relative urls.
            $relurl = strpos($url, $GLOBALS['base_url'] . '/') === 0 ? substr($url, strlen($GLOBALS['base_url'])) : FALSE;
            $newrelurl = strpos($newurl, $GLOBALS['base_url'] . '/') === 0 ? substr($newurl, strlen($GLOBALS['base_url'])) : FALSE;
            if ($relurl && $newrelurl) {
              static::fixFileMove($relurl, $newrelurl, $isdir);
            }
          }
        }
      }
    }
    catch(Exception $e) {
      watchdog_exception('dfm', $e);
    }
  }

  /**
   * Update moved/renamed file urls in body of nodes/blocks.
   */
  public static function fixFileMove($url, $newurl, $isdir = FALSE) {
    $url = '"' . $url;
    $newurl = '"' . $newurl;
    if ($isdir) {
      if (substr($url, -1) !== '/') {
        $url .= '/';
      }
      if (substr($newurl, -1) !== '/') {
        $newurl .= '/';
      }
    }
    else {
      $url .= '"';
      $newurl .= '"';
    }
    $tokens = [':url' => $url, ':newurl' => $newurl];
    $types = ['node', 'block_content'];
    $db = Database::getConnection();
    foreach ($types as $type) {
      $table = $type . '__body';
      $ids = $db->select($table, 'b')
        ->fields('b', ['entity_id'])
        ->condition('body_value', '%' . $db->escapeLike($url) . '%', 'LIKE')
        ->execute()
        ->fetchCol();
      if ($ids) {
        $db->update($table)
          ->expression('body_value', 'REPLACE(body_value, :url, :newurl)', $tokens)
          ->expression('body_summary', 'REPLACE(body_summary, :url, :newurl)', $tokens)
          ->condition('entity_id', $ids, 'IN')
          ->execute();
        // Invalidate field value cache and render cache
        $cids = [];
        $tags = [];
        foreach ($ids as $i => $id) {
          $cids[$i] = "values:$type:$id";
          $tags[$i] = "$type:$id";
        }
        \Drupal::cache('entity')->invalidateMultiple($cids);
        \Drupal\Core\Cache\Cache::invalidateTags($tags);
      }
    }
  }

}