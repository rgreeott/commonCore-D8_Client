<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_copy.
 */
function _dfm_copy_ajax_copy(Dfm $dfm) {
  // Validate/prepare destination directory.
  if (!isset($_POST['destDir'])) return;
  $dest = $_POST['destDir'];
  if (!$dfm->dirConf($dest) || !$dfm->prepareDir($dest, TRUE)) return;
  $fileperm = $dfm->dirPerm($dest, 'copyFiles');
  $folderperm = $dfm->dirPerm($dest, 'copyFolders');
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    if (isset($content['files']) && $fileperm && $dfm->dirPerm($dirname, 'copyFiles')) {
      if ($new_fail = _dfm_copy_dir_files($dfm, $dirname, $content['files'], $dest)) {
        $fail = array_merge($fail, $new_fail);
      }
    }
    if (isset($content['subdirs']) && $folderperm && $dfm->dirPerm($dirname, 'copyFolders')) {
      if ($new_fail = _dfm_copy_dir_subdirs($dfm, $dirname, $content['subdirs'], $dest)) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to copy: %names.', $fail);
  }
}

/**
 * Copies a list of files.
 */
function _dfm_copy_dir_files(Dfm $dfm, $dirname, array $filenames, $dest) {
  $success = array();
  // Check selfcopy. Avoid ('x' == 0) case!
  $selfcopy = "$dest" === "$dirname";
  $dirpath = $dfm->dirPath($dirname);
  $destpath = $selfcopy ? $dirpath : $dfm->dirPath($dest);
  $prefix = $dfm->getConf('copyNamePrefix', '');
  $suffix = $dfm->getConf('copyNameSuffix', ' - Copy');
  foreach ($filenames as $filename) {
    // Check existence
    if (!$filepath = $dfm->validateDirFile($dirname, $filename)) continue;
    // Copy. Overwrite if exists. We do the confirmation on client side.
    $newname = $selfcopy ? _dfm_copy_create_filename($dirpath, $filename, $prefix, $suffix) : $filename;
    $newpath = $dfm->joinPaths($destpath, $newname);
    if (!_dfm_copy_filepath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->addDirFile($dest, $newname);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Copies a list of subdirectories.
 */
function _dfm_copy_dir_subdirs(Dfm $dfm, $dirname, array $filenames, $dest) {
  $success = array();
  // Check selfcopy. Avoid ('x' == 0) case!
  $selfcopy = "$dest" === "$dirname";
  $dirpath = $dfm->dirPath($dirname);
  $destpath = $selfcopy ? $dirpath : $dfm->dirPath($dest);
  $prefix = $dfm->getConf('copyNamePrefix', '');
  $suffix = $dfm->getConf('copyNameSuffix', ' - Copy');
  foreach ($filenames as $filename) {
    // Check existence
    if (!$filepath = $dfm->validateDirSubdir($dirname, $filename)) continue;
    // Check (grand)parent appending
    $subdirname = $dfm->joinPaths($dirname, $filename);
    if (strpos($dest . '/', $subdirname . '/') === 0) continue;
    // Check predefined paths
    if (!$selfcopy) {
      $subdestname = $dfm->joinPaths($dest, $filename);
      $pdirname = $dfm->getPredefinedDirname($subdestname);
      if (isset($pdirname)) {
        $dfm->setMessage('%dirname is a predefined directory and can not be modified.', array('%dirname' => $pdirname));
        continue;
      }
    }
    // Copy
    $newname = $selfcopy ? _dfm_copy_create_filename($dirpath, $filename, $prefix, $suffix) : $filename;
    $newpath = $dfm->joinPaths($destpath, $newname);
    if (!_dfm_copy_dirpath($dfm, $filepath, $newpath)) continue;
    // Set success
    $success[] = $filename;
    $dfm->addDirSubdir($dest, $newname);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Copies a file path.
 */
function _dfm_copy_filepath(Dfm $dfm, $filepath, $newpath) {
  $existing = file_exists($newpath);
  if (in_array(FALSE, $dfm->invoke('copy_filepath_validate', $filepath, $newpath, $existing), TRUE)) return FALSE;
  if (!copy($filepath, $newpath)) return FALSE;
  $dfm->invoke('copy_filepath', $filepath, $newpath, $existing);
  return TRUE;
}

/**
 * Copies a directory path recursively.
 */
function _dfm_copy_dirpath(Dfm $dfm, $dirpath, $newpath) {
  $existing = file_exists($newpath);
  if (in_array(FALSE, $dfm->invoke('copy_dirpath_validate', $dirpath, $newpath, $existing), TRUE)) return FALSE;
  // Create the destination dir if not exists.
  if (!$existing) {
    if (!mkdir($newpath, $dfm->getConf('directoryMode', 0775))) return FALSE;
  }
  // We can't just copy the dir. We copy its contents.
  // Get directory contents as a full list without any filtering.
  $dircontent = $dfm->dirPathContent($dirpath);
  if (!empty($dircontent['error'])) return FALSE;
  // Copy the directories first. Terminate on failure.
  foreach ($dircontent['subdirs'] as $name => $path) {
    if (!_dfm_copy_dirpath($dfm, $path, $newpath . '/' . $name)) return FALSE;
  }
  // Continue copying the files
  foreach ($dircontent['files'] as $name => $path) {
    if (!_dfm_copy_filepath($dfm, $path, $newpath . '/' . $name)) return FALSE;
  }
  $dfm->invoke('copy_dirpath', $dirpath, $newpath, $existing);
  return TRUE;
}

/**
 * Returns a non-existing variant of filename under the directory path.
 */
function _dfm_copy_create_filename($dirpath, $filename, $prefix = '', $suffix = '') {
  $filepath = $dirpath . '/' . $filename;
  if (!file_exists($filepath)) return $filename;
  if (is_file($filepath) && $pos = strrpos($filename, '.')) {
    $name = substr($filename, 0, $pos);
    $ext = substr($filename, $pos);
  }
  else {
    $name = $filename;
    $ext = '';
  }
  $name = $prefix . $name . $suffix;
  $filename = $name . $ext;
  for ($i = 2; file_exists($dirpath . '/' . $filename); $i++) {
    $filename = $name . ' ' . $i . $ext;
  }
  return $filename;
}