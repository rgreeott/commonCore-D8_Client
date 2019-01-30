<?php

/**
 * Callback for ajax_download.
 */
function _dfm_download_ajax_download($dfm) {
  // Create archive info
  $archive = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    // Handle selected files
    if (isset($content['files']) && $dfm->dirPerm($dirname, 'downloadFiles')) {
      foreach ($content['files'] as $filename) {
        // Archive must have unique filenames at the top level.
        if (isset($archive['files'][$filename])) continue;
        // Check existence. Do not allow archiving of unlisted files.
        if ($filepath = $dfm->validateDirFile($dirname, $filename)) {
          $archive['files'][$filename] = $filepath;
        }
      }
    }
    // Handle selected subfolders
    if (isset($content['subdirs']) && $dfm->dirPerm($dirname, 'downloadFolders')) {
      foreach ($content['subdirs'] as $subdirname) {
        // Archive must have unique containers. Keep the first one and ignore the rest.
        if (isset($archive['folders'][$subdirname])) continue;
        // Check existence. Do not allow archiving of unlisted folders.
        if ($dfm->validateDirSubdir($dirname, $subdirname)) {
          $archive['folders'][$subdirname] = $subdirname;
          // Get archive info of all files and folders under this directory recursively.
          $_dirname = $dfm->joinPaths($dirname, $subdirname);
          _dfm_download_extend_archive($archive, _dfm_download_dirname_archive_info($dfm, $_dirname, $subdirname));
        }
      }
    }
  }
  // Serve the archive
  if (!empty($archive)) {
    // Set the download token as a cookie value to allow response checking on client side.
    if (!empty($_POST['download_token'])) {
      setcookie('download_token', $_POST['download_token'], 0, '/');
    }
    // Directly serve if it is a single file
    if (empty($archive['folders']) && count($archive['files']) == 1) {
      $fileuri = reset($archive['files']);
      $realpath = $dfm->realpath($fileuri);
      $filename = key($archive['files']);
      $headers = array(
        // Use realpath in finfo_file to prevent stream_cast warnings of file stream wrappers
        'Content-type' => _dfm_download_mime_type($realpath ? $realpath : $fileuri),
        'Content-Length' => filesize($fileuri),
        'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
      );
      _dfm_download_transfer_file($fileuri, $headers);
    }
    // Serve multiple files and folders as a zip archive
    else {
      _dfm_download_serve_archive($dfm, $archive);
    }
  }
}

/**
 * Returns recursive archive info for the given DFM dirname.
 */
function _dfm_download_dirname_archive_info($dfm, $dirname, $localname) {
  $archive = array();
  $downloadFiles = $dfm->dirPerm($dirname, 'downloadFiles');
  $downloadFolders = $dfm->dirPerm($dirname, 'downloadFolders');
  if ($downloadFiles || $downloadFolders) {
    // Set scan options
    $options = $dfm->getFilterOptions();
    $options['listFiles'] = $downloadFiles && $dfm->dirPerm($dirname, 'listFiles');
    $options['listFolders'] = $downloadFolders && $dfm->dirPerm($dirname, 'listFolders');
    // Scan contents
    $content = $dfm->dirPathContent($dfm->dirPath($dirname), $options);
    // Process subdirs
    foreach ($content['subdirs'] as $name => $path) {
      $_dirname = $dirname . '/' . $name;
      $_localname = $localname . '/' . $name;
      $archive['folders'][$_localname] = $_localname;
      _dfm_download_extend_archive($archive, _dfm_download_dirname_archive_info($dfm, $_dirname, $_localname));
    }
    // Process files
    foreach ($content['files'] as $name => $path) {
      $archive['files'][$localname . '/' . $name] = $path;
    }
  }
  return $archive;
}

/**
 * Serve a zip archive.
 */
function _dfm_download_serve_archive($dfm, $info) {
  if (!class_exists('ZipArchive')) {
    return $dfm->setMessage('Missing PHP Class: ZipArchive.');
  }
  // Create temp file and initiate zip
  $zip = new ZipArchive();
  if (!($temp = _dfm_download_temp_file()) || $zip->open($temp, ZipArchive::OVERWRITE) !== TRUE) {
    return $dfm->setMessage('Unable to create a zip archive in temp directory.');
  }
  // Add folders
  if (!empty($info['folders'])) {
    foreach ($info['folders'] as $localname) {
      $zip->addEmptyDir($localname);
    }
  }
  // Add files
  if (!empty($info['files'])) {
    foreach ($info['files'] as $localname => $path) {
      // Require a local file with a real path.
      // @todo try addFromString() with file_get_contents() for non-local files.
      if ($realpath = $dfm->realpath($path)) {
        $zip->addFile($realpath, $localname);
      }
    }
  }
  // Close the zip.
  $zip->close();
  // Empty/failed archives are deleted by close() method.
  if (!file_exists($temp)) {
    return $dfm->setMessage('Failed to archive files.');
  }
  // Serve the file.
  $headers = array(
    'Content-type' => 'application/zip',
    'Content-Length' => filesize($temp),
    'Content-Disposition' => 'attachment; filename="' . basename($temp) . '.zip"',
  );
  _dfm_download_transfer_file($temp, $headers);
}

/**
 * Creates a temp file.
 */
function _dfm_download_temp_file() {
  if (($dir = _dfm_download_temp_dir()) && $file = tempnam($dir, 'dfm')) {
    register_shutdown_function('unlink', $file);
    return $file;
  }
}

/**
 * Returns system temp directory.
 */
function _dfm_download_temp_dir() {
  if ($dir = _dfm_download_writable_dir(sys_get_temp_dir())) return $dir;
  if ($dir = _dfm_download_writable_dir(ini_get('upload_tmp_dir'))) return $dir;
}

/**
 * Checks if the given path is a writable directory.
 * Returns realpath.
 */
function _dfm_download_writable_dir($dir) {
  if ($dir && is_dir($dir) && is_writable($dir)) return realpath($dir);
}

/**
 * Extend an archive array with another.
 */
function _dfm_download_extend_archive(&$a, $b) {
  foreach ($b as $key => $val) {
    if (isset($a[$key]) && is_array($a[$key]) && is_array($val)) {
      _dfm_download_extend_archive($a[$key], $val);
    }
    else {
      $a[$key] = $val;
    }
  }
}

/**
 * Returns the mimetype of a file.
 */
function _dfm_download_mime_type($filepath) {
  if (function_exists('finfo_open') && $finfo = finfo_open(FILEINFO_MIME_TYPE)) {
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    return $mime;
  }
  return 'application/octet-stream';
}

/**
 * Transfers a file to the client.
 */
function _dfm_download_transfer_file($filepath, array $headers = array()) {
  if (ob_get_level()) ob_end_clean();
  foreach ($headers as $key => $value) {
    header($key . ($value === '' ? '' : ': ' . $value));
  }
  if ($fd = fopen($filepath, 'rb')) {
    while (!feof($fd)) {
      print fread($fd, 1024);
    }
    fclose($fd);
  }
  exit;
}