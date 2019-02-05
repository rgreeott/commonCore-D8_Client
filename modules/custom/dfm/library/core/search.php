<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_search.
 */
function _dfm_search_ajax_search(Dfm $dfm) {
  // Check if search is enabled
  if (!$dfm->getConf('searchOn')) return;
  // Prepare options
  if (!$options = _dfm_search_get_validated_options($dfm)) return;
  $dirnames = &$options['dirnames'];
  $scanned = array();
  while ($dirnames) {
    $dirname = array_pop($dirnames);
    // Check if already scanned
    if (isset($scanned[$dirname])) continue;
    $scanned[$dirname] = 1;
    // Check perms. Ignore 'skipFolders' as we need add subdirs to the scan list
    $options['listFiles'] = !$options['skipFiles'] && $dfm->dirPerm($dirname, 'listFiles');
    $options['listFolders'] = $dfm->dirPerm($dirname, 'listFolders');
    // Scan directory and check limit
    if ($options['listFiles'] || $options['listFolders']) {
      $options['dirname'] = $dirname;
      $dfm->dirPathContent($dfm->dirPath($dirname), $options);
      // Check limit and terminate file scan
      if ($options['limit'] && $options['count'] >= $options['limit']) break;
    }
  }
}

/**
 * Validates the request and returns search options.
 */
function _dfm_search_get_validated_options(Dfm $dfm) {
  if (!_dfm_search_validate_request()) return;
  $options = _dfm_search_prepare_options($dfm);
  if (in_array(FALSE, $dfm->invoke('search_options_alter', $options), TRUE)) return;
  return $options;
}

/**
 * Validates search request.
 */
function _dfm_search_validate_request() {
  // Check posted dirnames
  if (!isset($_POST['dirnames'][0])) return;
  //Check keys
  if (!isset($_POST['keys']) || !is_string($_POST['keys']) || preg_match('@^\**$|[/\x00]@', $_POST['keys'])) return;
  return TRUE;
}

/**
 * Prepare search options
 */
function _dfm_search_prepare_options(Dfm $dfm) {
  $options = $dfm->getFilterOptions();
  // Prepare dirnames
  $options['dirnames'] = array();
  foreach (array_unique($_POST['dirnames']) as $dirname) {
    if ($dirconf = $dfm->dirConf($dirname)) {
      // Make sure predefined directories exist.
      if (!empty($dirconf['derived']) || is_dir($dfm->dirPath($dirname))) {
        $options['dirnames'][] = $dirname;
      }
    }
  }
  // Prepare options
  $options['skipFiles'] = !empty($_POST['skipFiles']);
  $options['skipFolders'] = !empty($_POST['skipFolders']);
  $options['shallow'] = !empty($_POST['shallow']);
  // Keys. Add/convert wildcards
  $re = $_POST['keys'];
  if (strpos($re, '*') === FALSE) {
    $re = '.*' . preg_quote($re) .  '.*';
  }
  else {
    $re = implode('.*', array_map('preg_quote', preg_split('/\*+/', $re)));
  }
  $re = '/^' . str_replace('/', '\/', $re) . '$/';
  if (empty($_POST['caseSensitive'])) $re .= 'i';
  $options['searchRE'] = $re;
  // Limit
  $options['limit'] = (int) $dfm->getConf('searchLimit', 0);
  $options['count'] = 0;
  // Callback
  $options['callback'] = '_dfm_search_iterator';
  return $options;
}

/**
 * Iterator callback that is executed during directory search.
 */
function _dfm_search_iterator(Dfm $dfm, &$options, $filename, $filepath, $is_dir) {
  // Add subdirectory to the scan list
  if ($is_dir && !$options['shallow']) {
    $dirname = $options['dirname'] === '.' ? $filename : $options['dirname'] . '/' . $filename;
    // Check dirconf. Skip is_dir and name check as we already know this is a valid directory.
    if ($dfm->dirConf($dirname, TRUE, TRUE)) {
      $options['dirnames'][] = $dirname;
    }
  }
  // Chek match. Check skipFolders as we omitted it initially to process all the subdirs.
  if ((!$is_dir || !$options['skipFolders']) && preg_match($options['searchRE'], $filename)) {
    // Add the file to the conf
    $type = $is_dir ? 'subdirs' : 'files';
    $props = $is_dir ? $dfm->subdirProps($filepath) : $dfm->fileProps($filepath);
    $dfm->setAddedConf($options['dirname'], $type, $filename, $props);
    // Check limit and terminate file scan
    $options['count']++;
    if ($options['limit'] && $options['count'] >= $options['limit']) {
      return -1;
    }
  }
  // Skip the current file and continue iterating
  return FALSE;
}