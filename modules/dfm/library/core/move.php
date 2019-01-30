<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_move.
 */
function _dfm_move_ajax_move(Dfm $dfm) {
  // Validate/prepare destination directory.
  if (!isset($_POST['destDir'])) return;
  $dest = $_POST['destDir'];
  if (!$dfm->dirConf($dest) || !$dfm->prepareDir($dest, TRUE)) return;
  $fileperm = $dfm->dirPerm($dest, 'moveFiles');
  $folderperm = $dfm->dirPerm($dest, 'moveFolders');
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    // Make sure destination and source are not the same. Avoid ('x' == 0) case!
    if ("$dest" !== "$dirname") {
      if (isset($content['files']) && $fileperm && $dfm->dirPerm($dirname, 'moveFiles')) {
        if ($new_fail = _dfm_move_dir_files($dfm, $dirname, $content['files'], $dest)) {
          $fail = array_merge($fail, $new_fail);
        }
      }
      if (isset($content['subdirs']) && $folderperm && $dfm->dirPerm($dirname, 'moveFolders')) {
        if ($new_fail = _dfm_move_dir_subdirs($dfm, $dirname, $content['subdirs'], $dest)) {
          $fail = array_merge($fail, $new_fail);
        }
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to move: %names.', $fail);
  }
}

/**
 * Moves a list of files.
 */
function _dfm_move_dir_files(Dfm $dfm, $dirname, array $filenames, $dest) {
  $success = array();
  $destpath = $dfm->dirPath($dest);
  foreach ($filenames as $filename) {
    // Check existence
    if (!$filepath = $dfm->validateDirFile($dirname, $filename)) continue;
    // Move. Overwrite if exists. We do the confirmation on client side.
    $newpath = $dfm->joinPaths($destpath, $filename);
    if (!_dfm_move_filepath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->removeDirFile($dirname, $filename);
    $dfm->addDirFile($dest, $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Moves a list of subdirectories.
 */
function _dfm_move_dir_subdirs(Dfm $dfm, $dirname, array $filenames, $dest) {
  $success = array();
  $destpath = $dfm->dirPath($dest);
  foreach ($filenames as $filename) {
    // Check existence
    if (!$filepath = $dfm->validateDirSubdir($dirname, $filename)) continue;
    // Check (grand)parent appending
    $subdirname = $dfm->joinPaths($dirname, $filename);
    if (strpos($dest . '/', $subdirname . '/') === 0) continue;
    // Check predefined paths
    $pdirname = $dfm->getPredefinedDirname($subdirname);
    // Check also the destination dirname
    if (!isset($pdirname)) {
      $subdestname = $dfm->joinPaths($dest, $filename);
      $pdirname = $dfm->getPredefinedDirname($subdestname);
    }
    if (isset($pdirname)) {
      $dfm->setMessage('%dirname is a predefined directory and can not be modified.', array('%dirname' => $pdirname));
      continue;
    }
    // Move
    $newpath = $dfm->joinPaths($destpath, $filename);
    if (!_dfm_move_dirpath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->removeDirSubdir($dirname, $filename);
    $dfm->addDirSubdir($dest, $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Moves a file path.
 */
function _dfm_move_filepath(Dfm $dfm, $filepath, $newpath) {
  $existing = file_exists($newpath);
  if (in_array(FALSE, $dfm->invoke('move_filepath_validate', $filepath, $newpath, $existing), TRUE)) return FALSE;
  if (!rename($filepath, $newpath)) return FALSE;
  $dfm->invoke('move_filepath', $filepath, $newpath, $existing);
  return TRUE;
}

/**
 * Moves a directory path recursively.
 */
function _dfm_move_dirpath(Dfm $dfm, $dirpath, $newpath) {
  $existing = file_exists($newpath);
  if (in_array(FALSE, $dfm->invoke('move_dirpath_validate', $dirpath, $newpath, $existing), TRUE)) return FALSE;
  // Destination does not exist. Regular rename will suffice.
  if (!$existing) {
    if (!rename($dirpath, $newpath)) return FALSE;
    $dfm->invoke('move_dirpath', $dirpath, $newpath, $existing);
    return TRUE;
  }
  // Destinatination exists. We can't just rename the dir. We move its contents and then delete it.
  // Destination directory stays intact instead of the source.
  // Note that this is inconsistent with the rest of the move algorithm.
  // Get directory contents as a full list without any filtering.
  $dircontent = $dfm->dirPathContent($dirpath);
  if (!empty($dircontent['error'])) return FALSE;
  // Move the directories first. Terminate on failure.
  foreach ($dircontent['subdirs'] as $name => $path) {
    if (!_dfm_move_dirpath($dfm, $path, $newpath . '/' . $name)) return FALSE;
  }
  // Continue moving the files
  foreach ($dircontent['files'] as $name => $path) {
    if (!_dfm_move_filepath($dfm, $path, $newpath . '/' . $name)) return FALSE;
  }
  // Remove the directory if all went well.
  // On windows, directory stat update is delayed sometimes, causing non-empty directory errors on rmdir.
  if ($dfm->getConf('isWin')) {
    @closedir(@opendir($dirpath));
  }
  if (!rmdir($dirpath)) return FALSE;
  $dfm->invoke('move_dirpath', $dirpath, $newpath, $existing);
  return TRUE;
}