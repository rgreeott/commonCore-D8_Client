<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_rename.
 */
function _dfm_rename_ajax_rename(Dfm $dfm) {
  $fail = array();
  foreach ($dfm->getSelectedItems(array('newfiles', 'newsubdirs')) as $dirname => $content) {
    if (isset($content['files']) && isset($content['newfiles']) && $dfm->dirPerm($dirname, 'renameFiles')) {
      if ($new_fail = _dfm_rename_dir_files($dfm, $dirname, $content['files'], $content['newfiles'])) {
        $fail = array_merge($fail, $new_fail);
      }
    }
    if (isset($content['subdirs']) && isset($content['newsubdirs']) && $dfm->dirPerm($dirname, 'renameFolders')) {
      if ($new_fail = _dfm_rename_dir_subdirs($dfm, $dirname, $content['subdirs'], $content['newsubdirs'])) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to rename: %names.', $fail);
  }
}

/**
 * Renames a list of files.
 */
function _dfm_rename_dir_files(Dfm $dfm, $dirname, array $filenames, array $newnames) {
  $success = array();
  // Use rename extensions if set, use upload extensions otherwise.
  $exts = $dfm->getConf('renameExtensions');
  if (!isset($exts)) $exts = $dfm->getConf('uploadExtensions', array());
  $secure = !$dfm->getConf('uploadInsecure');
  $execexts = $dfm->getConf('execExtensions');
  $extalter = $exts && $dfm->getConf('renameExtensionAlter');
  $extcheck = !$exts || $exts[0] !== '*';
  foreach ($filenames as $i => $filename) {
    // Check if filename exists
    if (!$filepath = $dfm->validateDirFile($dirname, $filename)) continue;
    // Check newname
    if (!isset($newnames[$i])) continue;
    $newname = $newnames[$i];
    // Check if extension is changed
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $newext = pathinfo($newname, PATHINFO_EXTENSION);
    if ($newext !== $ext) {
      // Altering the extension is not allowed
      if (!$extalter) continue;
      // Forbidden extension
      if ($extcheck && !in_array($newext, $exts)) continue;
      // Prevent executable extensions
      if ($secure && $execexts && in_array(strtolower($newext), $execexts)) continue;
    }
    // Make sure the new name is neutralized.
    if ($secure && $extcheck && $newname !== $dfm->neutralizeFileName($newname, $exts)) continue;
    // General filename validation.
    if (!$dfm->validateFileName($newname)) continue;
    // Check existence
    $newpath = $dfm->dirFilePath($dirname, $newname);
    if (file_exists($newpath)) {
      $dfm->setMessage('%filename already exists.', array('%filename' => $newname));
      continue;
    }
    // Rename
    if (!_dfm_rename_filepath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->removeDirFile($dirname, $filename);
    $dfm->addDirFile($dirname, $newname);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Renames a list of subdirectories.
 */
function _dfm_rename_dir_subdirs(Dfm $dfm, $dirname, array $filenames, array $newnames) {
  $success = array();
  foreach ($filenames as $i => $filename) {
    // Check if filename exists
    if (!$filepath = $dfm->validateDirSubdir($dirname, $filename)) continue;
    // Check predefined paths
    $subdirname = $dfm->joinPaths($dirname, $filename);
    $pdirname = $dfm->getPredefinedDirname($subdirname);
    if (isset($pdirname)) {
      $dfm->setMessage('%dirname is a predefined directory and can not be modified.', array('%dirname' => $pdirname));
      continue;
    }
    // Check newname
    if (!isset($newnames[$i])) continue;
    $newname = $newnames[$i];
    if (!$dfm->validateFileName($newname)) continue;
    // Check existence
    $newpath = $dfm->dirFilePath($dirname, $newname);
    if (file_exists($newpath)) {
      $dfm->setMessage('%filename already exists.', array('%filename' => $newname));
      continue;
    }
    // Rename
    if (!_dfm_rename_dirpath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->removeDirSubdir($dirname, $filename);
    $dfm->addDirSubdir($dirname, $newname);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Renames a file path.
 */
function _dfm_rename_filepath(Dfm $dfm, $filepath, $newpath) {
  if (in_array(FALSE, $dfm->invoke('rename_filepath_validate', $filepath, $newpath), TRUE)) return FALSE;
  if (!rename($filepath, $newpath)) return FALSE;
  $dfm->invoke('rename_filepath', $filepath, $newpath);
  return TRUE;
}

/**
 * Renames a directory path.
 */
function _dfm_rename_dirpath(Dfm $dfm, $dirpath, $newpath) {
  if (in_array(FALSE, $dfm->invoke('rename_dirpath_validate', $dirpath, $newpath), TRUE)) return FALSE;
  if (!rename($dirpath, $newpath)) return FALSE;
  $dfm->invoke('rename_dirpath', $dirpath, $newpath);
  return TRUE;
}