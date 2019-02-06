<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_newdir.
 */
function _dfm_newdir_ajax_newdir(Dfm $dfm) {
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    if (isset($content['subdirs']) && $dfm->dirPerm($dirname, 'createFolders')) {
      if ($new_fail = _dfm_create_dir_subdirs($dfm, $dirname, $content['subdirs'])) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to create: %names.', $fail);
  }
}

/**
 * Creates a list of subdirectories.
 */
function _dfm_create_dir_subdirs(Dfm $dfm, $dirname, array $filenames) {
  $success = array();
  $mode = $dfm->getConf('directoryMode', 0775);
  foreach ($filenames as $filename) {
    // Validate filename
    if (!$dfm->validateFileName($filename)) continue;
    // Check existence
    $filepath = $dfm->dirFilePath($dirname, $filename);
    if (file_exists($filepath)) {
      $dfm->setMessage('%filename already exists.', array('%filename' => $filename));
      continue;
    }
    if (in_array(FALSE, $dfm->invoke('newdir_validate', $filepath), TRUE)) continue;
    if (!mkdir($filepath, $mode)) continue;
    $dfm->invoke('newdir', $filepath);
    $success[] = $filename;
    $dfm->addDirSubdir($dirname, $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}