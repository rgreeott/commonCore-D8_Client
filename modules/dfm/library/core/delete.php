<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_delete.
 */
function _dfm_delete_ajax_delete(Dfm $dfm) {
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    if (isset($content['files']) && $dfm->dirPerm($dirname, 'deleteFiles')) {
      if ($new_fail = _dfm_delete_dir_files($dfm, $dirname, $content['files'])) {
        $fail = array_merge($fail, $new_fail);
      }
    }
    if (isset($content['subdirs']) && $dfm->dirPerm($dirname, 'deleteFolders')) {
      if ($new_fail = _dfm_delete_dir_subdirs($dfm, $dirname, $content['subdirs'])) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to delete: %names.', $fail);
  }
}

/**
 * Deletes a list of files.
 */
function _dfm_delete_dir_files(Dfm $dfm, $dirname, array $filenames) {
  $success = array();
  foreach ($filenames as $filename) {
    // Check existence. Do not allow deletion of unlisted files.
    if (!$filepath = $dfm->validateDirFile($dirname, $filename)) continue;
    // Delete path
    if (!_dfm_delete_filepath($dfm, $filepath)) continue;
    $success[] = $filename;
    $dfm->removeDirFile($dirname, $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Deletes a list of subdirectories.
 */
function _dfm_delete_dir_subdirs(Dfm $dfm, $dirname, array $filenames) {
  $success = array();
  foreach ($filenames as $filename) {
    // Check existence
    if (!$filepath = $dfm->validateDirSubdir($dirname, $filename)) continue;
    // Check predefined paths
    $subdirname = $dfm->joinPaths($dirname, $filename);
    $pdirname = $dfm->getPredefinedDirname($subdirname);
    if (isset($pdirname)) {
      $dfm->setMessage('%dirname is a predefined directory and can not be modified.', array('%dirname' => $pdirname));
      continue;
    }
    // Delete path
    if (!_dfm_delete_dirpath($dfm, $filepath)) continue;
    $success[] = $filename;
    $dfm->removeDirSubdir($dirname, $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Deletes a file path.
 */
function _dfm_delete_filepath(Dfm $dfm, $filepath) {
  $validation = $dfm->invoke('delete_filepath_validate', $filepath);
  if (in_array(FALSE, $validation, TRUE)) return FALSE;
  if (!in_array('skip', $validation, TRUE)) {
    if (!unlink($filepath)) return FALSE;
  }
  $dfm->invoke('delete_filepath', $filepath);
  return TRUE;
}

/**
 * Deletes a directory path recursively.
 */
function _dfm_delete_dirpath(Dfm $dfm, $dirpath) {
  if (in_array(FALSE, $dfm->invoke('delete_dirpath_validate', $dirpath), TRUE)) return FALSE;
  // Get directory contents as a full list without any filtering.
  $dircontent = $dfm->dirPathContent($dirpath);
  if (!empty($dircontent['error'])) return FALSE;
  // Delete the directories first. Terminate on failure.
  foreach ($dircontent['subdirs'] as $name => $path) {
    if (!_dfm_delete_dirpath($dfm, $path)) return FALSE;
  }
  // Continue deleting the files
  foreach ($dircontent['files'] as $name => $path) {
    if (!_dfm_delete_filepath($dfm, $path)) return FALSE;
  }
  // Remove the directory if all went well.
  // On windows, directory stat update is delayed sometimes, causing non-empty directory errors on rmdir.
  if ($dfm->getConf('isWin')) {
    @closedir(@opendir($dirpath));
  }
  if (!rmdir($dirpath)) return FALSE;
  $dfm->invoke('delete_dirpath', $dirpath);
  return TRUE;
}