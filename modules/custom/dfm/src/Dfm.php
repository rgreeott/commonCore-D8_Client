<?php

namespace Drupal\dfm;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Url;

/**
 * Dfm container class for helper methods.
 */
class Dfm {

  /**
   * Checks if a user has a dfm profile assigned for a file scheme.
   */
  public static function access(AccountProxyInterface $user = NULL, $scheme = NULL) {
    return (bool) static::userProfile($user, $scheme);
  }

  /**
   * Returns a response for a dfm request.
   */
  public static function response(Request $request, AccountProxyInterface $user = NULL, $scheme = NULL) {
    // Ajax request
    if ($request->request->has('ajaxOp')) {
      if ($fm = static::userFM($user, $scheme, $request)) {
        return $fm->run();
      }
    }
    // Return dfm page.
    $page = ['#theme' => 'dfm_page'];
    return new Response(\Drupal::service('renderer')->render($page));
  }

  /**
   * Returns a file manager instance for a user.
   */
  public static function userFM(AccountProxyInterface $user = NULL, $scheme = NULL, Request $request = NULL) {
    if ($conf = static::userConf($user, $scheme)) {
      if (!isset($conf['ajaxOp']) && $request && $op = $request->request->get('ajaxOp')) {
        $conf['ajaxOp'] = $op;
      }
      require_once $conf['scriptDirPath'] . '/core/Dfm.php';
      return new \Dfm($conf);
    }
  }

  /**
   * Returns dfm configuration profile for a user.
   */
  public static function userProfile(AccountProxyInterface $user = NULL, $scheme = NULL) {
    $profiles = &drupal_static(__METHOD__, []);
    $user = $user ?: \Drupal::currentUser();
    $scheme = isset($scheme) ? $scheme : file_default_scheme();
    $profile = &$profiles[$user->id()][$scheme];
    if (!isset($profile)) {
      // Check stream wrapper
      if ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaScheme($scheme)) {
        // Give user #1 admin profile
        $storage = \Drupal::entityTypeManager()->getStorage('dfm_profile');
        if ($user->id() == 1 && $profile = $storage->load('admin')) {
          return $profile;
        }
        $roles_profiles = \Drupal::config('dfm.settings')->get('roles_profiles', []);
        $user_roles = array_flip($user->getRoles());
        // Order roles from more permissive to less permissive.
        $roles = array_reverse(user_roles());
        foreach ($roles as $rid => $role) {
          if (isset($user_roles[$rid]) && !empty($roles_profiles[$rid][$scheme])) {
            if ($profile = $storage->load($roles_profiles[$rid][$scheme])) {
              return $profile;
            }
          }
        }
      }
      $profile = FALSE;
    }
    return $profile;
  }

  /**
   * Returns processed profile configuration for a user.
   */
  public static function userConf(AccountProxyInterface $user = NULL, $scheme = NULL) {
    $user = $user ?: \Drupal::currentUser();
    $scheme = isset($scheme) ? $scheme : file_default_scheme();
    if ($profile = static::userProfile($user, $scheme)) {
      $conf = $profile->getConf();
      $conf['pid'] = $profile->id();
      $conf['scheme'] = $scheme;
      return static::processUserConf($conf, $user);
    }
  }

  /**
   * Processes raw profile configuration of a user.
   */
  public static function processUserConf(array $conf, AccountProxyInterface $user) {
    // Convert MB to bytes
    $conf['uploadMaxSize'] *= 1048576;
    $conf['uploadQuota'] *= 1048576;
    // Set extensions
    $conf['uploadExtensions'] = array_values(array_filter(explode(' ', $conf['uploadExtensions'])));
    if (isset($conf['imgExtensions'])) {
      $conf['imgExtensions'] = $conf['imgExtensions'] ? array_values(array_filter(explode(' ', $conf['imgExtensions']))) : FALSE;
    }
    // Set variables
    if ($val = \Drupal::config('system.file')->get('allow_insecure_uploads')) {
      $conf['uploadInsecure'] = $val;
    }
    if ($val = \Drupal::config('dfm.settings')->get('abs_urls')) {
      $conf['absUrls'] = $val;
    }
    // Set paths/urls
    $conf['rootDirPath'] = $conf['scheme'] . '://';
    $conf['rootDirUrl'] = preg_replace('@/(?:%2E|\.)$@i', '', file_create_url($conf['rootDirPath'] . '.'));
    if (empty($conf['absUrls'])) {
      $conf['rootDirUrl'] = file_url_transform_relative($conf['rootDirUrl']);
    }
    $conf['scriptDirPath'] = static::scriptPath();
    $conf['baseUrl'] = base_path();
    $conf['securityKey'] = $user->isAnonymous() ? 'anonymous' : \Drupal::csrfToken()->get('dfm');
    $conf['jsCssSuffix'] = \Drupal::state()->get('system.css_js_query_string', '0');
    $conf['drupalUid'] = $user->id();
    $conf['fileMode'] = Settings::get('file_chmod_file', FileSystem::CHMOD_FILE);
    $conf['directoryMode'] = Settings::get('file_chmod_directory', FileSystem::CHMOD_DIRECTORY);
    $conf['imgJpegQuality'] = \Drupal::config('system.image.gd')->get('jpeg_quality', 85);
    $conf['lang'] = \Drupal::service('language_manager')->getCurrentLanguage()->getId();
    // Set thumbnail URL
    if (!empty($conf['thumbStyle']) && function_exists('image_style_options')) {
      $conf['thumbUrl'] = Url::fromRoute('image.style_public', ['image_style' => $conf['thumbStyle'], 'scheme' => $conf['scheme']])->toString();
      if (!\Drupal::config('image.settings')->get('allow_insecure_derivatives')) {
        $conf['thumbUrlQuery'] = 'dfm_itok=' . static::itok($conf['thumbStyle']);
      }
    }
    // Add drupal plugin to call dfm_drupal_plugin_register().
    $conf['plugins'][] = 'drupal';
    // Set custom realpath function
    $conf['realpathFunc'] = 'Drupal\dfm\Dfm::realpath';
    // Process folder configurations. Make raw data available to alterers.
    $conf['dirConfRaw'] = $conf['dirConf'];
    $conf['dirConf'] = static::processConfFolders($conf, $user, \Drupal::config('dfm.settings')->get('merge_folders'));
    // Run alterers.
    \Drupal::moduleHandler()->alter('dfm_conf', $conf, $user);
    unset($conf['dirConfRaw']);
    // Apply chroot jail.
    if (!empty($conf['chrootJail'])) {
      static::chrootJail($conf);
    }
    return $conf;
  }

  /**
   * Processes folders in a user configuration.
   */
  public static function processConfFolders(array $conf, AccountProxyInterface $user, $merge = FALSE) {
    $ret = static::processUserFolders($conf['dirConf'], $user);
    // Merge folders from multiple profiles
    if ($merge) {
      $scheme = $conf['scheme'];
      $user_roles = array_flip($user->getRoles());
      $storage = \Drupal::entityTypeManager()->getStorage('dfm_profile');
      foreach (\Drupal::config('dfm.settings')->get('roles_profiles', []) as $rid => $profiles) {
        if (isset($user_roles[$rid]) && !empty($profiles[$scheme]) && $profiles[$scheme] != $conf['pid']) {
          if ($profile = $storage->load($profiles[$scheme])) {
            $pconf = $profile->getConf();
            foreach (static::processUserFolders($pconf['dirConf'], $user) as $dirname => $dirconf) {
              // Existing folder
              if (isset($ret[$dirname])) {
                continue;
              }
              // Inherited folder
              foreach ($ret as $parent => $parent_conf) {
                if (!empty($parent_conf['subdirConf']['inherit']) && ($parent === '.' || strpos($dirname . '/', $parent . '/') === 0)) {
                  continue 2;
                }
              }
              $ret[$dirname] = $dirconf;
            }
          }
        }
      }
    }
    return $ret;
  }

  /**
   * Processes user folders.
   */
  public static function processUserFolders(array $folders, AccountProxyInterface $user) {
    $ret = [];
    $token_service = \Drupal::token();
    $token_data = ['user' => $user];
    foreach ($folders as $folder) {
      $dirname = $token_service->replace($folder['dirname'], $token_data);
      if (static::regularPath($dirname)) {
        $ret[$dirname] = $folder;
        unset($ret[$dirname]['dirname']);
      }
    }
    return $ret;
  }

  /**
   * Applies chroot jail to the topmost directory in a profile configuration.
   */
  public static function chrootJail(array &$conf) {
    if (isset($conf['dirConf']['.'])) {
      return;
    }
    // Set the first one as topdir.
    list($topdir) = each($conf['dirConf']);
    // Check the rest
    while (list($dirname) = each($conf['dirConf'])) {
      // This is a subdirectory of the topdir. No change.
      if (strpos($dirname . '/', $topdir . '/') === 0) {
        continue;
      }
      // This is a parent directory of the topdir. Make it the topdir.
      if (strpos($topdir . '/', $dirname . '/') === 0) {
        $topdir = $dirname;
        continue;
      }
      // Not a part of the same branch with topdir which means there is no top-most directory
      return;
    }
    // Create the new dir conf starting from the top
    $newdirconf['.'] = $conf['dirConf'][$topdir];
    unset($conf['dirConf'][$topdir]);
    // Add the rest
    $pos = strlen($topdir) + 1;
    foreach ($conf['dirConf'] as $dirname => $set) {
      $newdirconf[substr($dirname, $pos)] = $set;
    }
    $conf['dirConf'] = $newdirconf;
    $conf['rootDirPath'] .= (substr($conf['rootDirPath'], -1) == '/' ? '' : '/') . $topdir;
    $topdirurl = str_replace('%2F', '/', rawurlencode($topdir));
    $conf['rootDirUrl'] .= '/' . $topdirurl;
    // Also alter thumnail prefix
    if (isset($conf['thumbUrl'])) {
      $conf['thumbUrl'] .= '/' . $topdirurl;
    }
    return $topdir;
  }

  /**
   * Checks the structure of a folder path.
   * Forbids current/parent directory notations.
   */
  public static function regularPath($path) {
    return is_string($path) && ($path === '.' || !preg_match('@\\\\|(^|/)\.*(/|$)@', $path));
  }

  /**
   * Returns a managed file entity by uri.
   * Optionally creates it.
   */
  public static function getFileEntity($uri, $create = FALSE, $save = FALSE) {
    $file = FALSE;
    if ($files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri])) {
      $file = reset($files);
    }
    elseif ($create) {
      $file = static::createFileEntity($uri, $save);
    }
    return $file;
  }

  /**
   * Creates a file entity with an uri.
   */
  public static function createFileEntity($uri, $save = FALSE) {
    // Set defaults. Mime and name are set by File::preCreate()
    $values = is_array($uri) ? $uri : ['uri' => $uri];
    $values += ['uid' => \Drupal::currentUser()->id(), 'status' => 1];
    if (!isset($values['filesize'])) {
      $values['filesize'] = filesize($values['uri']);
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->create($values);
    if ($save) {
      $file->save();
    }
    return $file;
  }

  /**
   * Returns all references to a file except own references.
   */
  public static function getFileUsage($file, $include_own = FALSE) {
    $usage = \Drupal::service('file.usage')->listUsage($file);
    // Remove own usage and also imce usage.
    if (!$include_own) {
      unset($usage['dfm'], $usage['imce']);
    }
    return $usage;
  }

  /**
   * Checks if the selected file paths(relative) are accessible by a user with Dfm.
   * Returns the accessible file uris.
   */
  public static function checkFilePaths(array $paths, AccountProxyInterface $user = NULL, $scheme = NULL) {
    $ret = [];
    if ($fm = static::userFM($user, $scheme)) {
      foreach ($paths as $path) {
        if ($uri = $fm->checkFile($path)) {
          $ret[$path] = $uri;
        }
      }
    }
    return $ret;
  }

  /**
   * Checks if a file uri is accessible by a user with Dfm.
   */
  public static function checkFileUri($uri, AccountProxyInterface $user = NULL) {
    list($scheme, $path) = explode('://', $uri, 2);
    if ($scheme && $path) {
      if ($fm = static::userFM($user, $scheme)) {
        return $fm->checkFileUri($uri);
      }
    }
  }

  /**
   * Returns Dfm script path.
   */
  public static function scriptPath($subpath = NULL) {
    static $libpath;
    if (!isset($libpath)) {
      $libpath = drupal_get_path('module', 'dfm') . '/library';
      if (!file_exists($libpath)) {
        $libpath = 'sites/all/libraries/dfm_lite';
      }
    }
    return isset($subpath) ? $libpath . '/' . $subpath : $libpath;
  }

  /**
   * Preprocessor for Dfm page.
   */
  public static function preprocessDfmPage(&$vars) {
    $vars += ['title' => t('File Browser'), 'head' => ''];
    $vars['lang'] = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $vars['libUrl'] = base_path() . static::scriptPath();
    $vars['qs'] = '?' . \Drupal::state()->get('system.css_js_query_string', '0');
    $vars['cssUrls']['core'] = $vars['libUrl'] . '/core/misc/dfm.css' . $vars['qs'];
    $vars['jsUrls']['jquery'] = $vars['libUrl'] . '/core/misc/jquery.js';
    $vars['jsUrls']['core'] = $vars['libUrl'] . '/core/misc/dfm.js' . $vars['qs'];
    $vars['scriptConf']['url'] = \Drupal\Core\Url::fromRoute('<current>')->toString();
  }

  /**
   * Resolves a file uri.
   */
  public static function realpath($uri) {
    return \Drupal::service('file_system')->realpath($uri);
  }

  /**
   * Returns image token for thumbnail styles.
   */
  public static function itok($style) {
    return substr(md5(md5($style . ':' . \Drupal::currentUser()->id() . ':' . \Drupal::service('private_key')->get())), 8, 8);
  }

  /**
   * Pre renders a textarea element for Dfm integration.
   */
  public static function preRenderTextarea($element) {
    $static = &drupal_static(__FUNCTION__, []);
    $regexp = &$static['regexp'];
    if (!isset($regexp)) {
      if ($regexp = str_replace(' ', '', \Drupal::config('dfm.settings')->get('textareas', ''))) {
        $regexp = '@^(' . str_replace(',', '|', implode('.*', array_map('preg_quote', explode('*', $regexp)))) . ')$@';
      }
    }
    if ($regexp && preg_match($regexp, $element['#id'])) {
      if (!isset($static['access'])) {
        $static['access'] = static::access();
      }
      if ($static['access']) {
        $element['#attached']['library'][] = 'dfm/drupal.dfm.textarea';
        $element['#attributes']['class'][] = 'dfm-textarea';
      }
    }
    return $element;
  }

}