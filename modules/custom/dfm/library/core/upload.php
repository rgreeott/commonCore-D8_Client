<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_upload.
 */
function _dfm_upload_ajax_upload(Dfm $dfm) {
  $dirname = $dfm->activeDir();
  if (empty($_FILES['files']['name'][0]) || !$dfm->dirPerm($dirname, 'uploadFiles')) return;
  $fail = array();
  $destdir = $dfm->dirPath($dirname);
  foreach ($_FILES['files']['error'] as $source => $error) {
    if ($file = _dfm_upload_process_source($dfm, $source, $destdir)) {
      $dfm->addDirFile($dirname, $file->filename, $dfm->fileProps($file->filepath, $file->jsprops));
    }
    else {
      $fail[] = $dfm->basename($_FILES['files']['name'][$source]);
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to upload: %names.', $fail);
  }
}

/**
 * Processes an uploaded file by upload index.
 */
function _dfm_upload_process_source(Dfm $dfm, $source, $destdir) {
  if (!isset($_FILES['files']['error'][$source]) || $_FILES['files']['error'][$source] !== UPLOAD_ERR_OK) return FALSE;
  if (!$exts = $dfm->getConf('uploadExtensions')) return FALSE;

  // Create file object
  $file = new stdClass();
  $file->jsprops = array();
  $file->filename = $dfm->basename($_FILES['files']['name'][$source]);
  $file->filepath = $_FILES['files']['tmp_name'][$source];
  $file->filesize = $_FILES['files']['size'][$source];
  $file->destdir = $destdir;
  $file->source = $source;
  $file->dfm = 1;

  // Trim dot files
  $secure = !$dfm->getConf('uploadInsecure');
  if ($secure) $file->filename = trim($file->filename, '.');
  $file->ext = pathinfo($file->filename, PATHINFO_EXTENSION);

  // Convert exec extensions to txt.
  $execexts = $dfm->getConf('execExtensions');
  if ($secure && $execexts && in_array(strtolower($file->ext), $execexts)) {
    $file->filename .= '.txt';
    $file->ext = 'txt';
  }

  // Validate file size. Js validation is not supported in <IE10. We set the message here too.
  $maxsize = $dfm->getConf('uploadMaxSize');
  if ($maxsize && $file->filesize > $maxsize) {
    $dfm->setMessage('%filename (%filesize:formatSize) exceeds the file size limit (%maxsize:formatSize).', array('%filename' => $file->filename, '%filesize:formatSize' => $file->filesize, '%maxsize:formatSize' => $maxsize));
    return FALSE;
  }

  // Validate file extension.
  $extcheck = $exts[0] !== '*';
  // Invalid extension
  if (strlen($file->ext)) {
    if ($extcheck && !in_array(strtolower($file->ext), $exts)) return FALSE;
  }
  // No extension
  else {
    if ($secure || $extcheck) return FALSE;
  }

  // Neutralize
  if ($secure && $extcheck) $file->filename = $dfm->neutralizeFileName($file->filename, $exts);

  // Validate file name (length, invalid chars, filtered names|dotfiles|nonascii)
  if (!$dfm->validateFileName($file->filename)) return FALSE;

  // Check destination.
  $file->destination = $dfm->joinPaths($file->destdir, $file->filename);
  $existing = file_exists($file->destination);
  if ($existing && empty($_REQUEST['uploadOverwrite'])) {
    $dfm->setMessage('%filename already exists.', array('%filename' => $file->filename));
    return FALSE;
  }

  // Check max image dimensions
  $maxdim = $dfm->getConf('imgMaxDim');
  $imgexts = $dfm->getConf('imgExtensions');
  $isimg = $imgexts && in_array(strtolower($file->ext), $imgexts);
  if ($maxdim && $isimg && $imginfo = getimagesize($file->filepath)) {
    list($maxw, $maxh) = explode('x', $maxdim);
    list($w, $h) = $imginfo;
    if ($w > $maxw || $h > $maxh) {
      // Check if upload resizing is prevented.
      if ($dfm->getConf('uploadNoScale') || !($scaled = _dfm_upload_scale_file($dfm, $file, $w, $h, $maxw, $maxh))) {
        $dfm->setMessage('%filename is %dimensions which is higher than the allowed value %maxdim.', array('%filename' => $file->filename, '%dimensions' => $w . 'x' . $h, '%maxdim' => $maxdim));
        return FALSE;
      }
    }
  }

  // Invoke upload validators.
  if (in_array(FALSE, $dfm->invoke('upload_file_validate', $file, $existing), TRUE)) return FALSE;

  // Record image size for ajax response before moving the file as it might already be available if scaling is on.
  if ($isimg && !isset($file->jsprops['width'])) {
    $w = $h = 0;
    // getimagesize() has never run
    if (!isset($imginfo)) {
      $imginfo = getimagesize($file->filepath);
    }
    if ($imginfo) {
      // Get dimensions from scale params.
      if (isset($scaled)) {
        $w = $scaled['dw'];
        $h = $scaled['dh'];
      }
      else {
        list($w, $h) = $imginfo;
      }
    }
    $file->jsprops['width'] = $w;
    $file->jsprops['height'] = $h;
  }

  // Move to destination.
  $file->filepath = $file->destination;
  if ($file->filepath && !move_uploaded_file($_FILES['files']['tmp_name'][$source], $file->filepath)) {
    $dfm->log('Unable to move uploaded file %filename to destination %destination.', array('%filename' => $file->filename, '%destination' => $file->filepath));
    $dfm->setMessage('Unable to move %filename.', array('%filename' => $file->filename));
    return FALSE;
  }
  @chmod($file->filepath, $dfm->getConf('fileMode', 0664));

  // Invoke upload_file hooks.
  $dfm->invoke('upload_file', $file, $existing);
  // Set js properties that will be included in ajax response.
  if (!isset($file->jsprops['size'])) $file->jsprops['size'] = $file->filesize;
  if (!isset($file->jsprops['date'])) $file->jsprops['date'] = time();
  return $file;
}

/**
 * Scales the file according to the given max width/height
 */
function _dfm_upload_scale_file($dfm, $file, $w, $h, $maxw, $maxh) {
  $dfm->includeFile('resize.php');
  $ratio = $h / $w;
  if ($ratio < $maxh / $maxw) {
    $maxh = round($maxw * $ratio);
  }
  else {
    $maxw = round($maxh / $ratio);
  }
  $ext = strtolower($file->ext);
  $params = array('dw' => $maxw, 'dh' => $maxh, 'sw' => $w, 'sh' => $h, 'type' => $ext == 'jpg' ? 'jpeg' : $ext);
  // Update file size after resizing
  if (_dfm_resize_image($file->filepath, $file->filepath, $params)) {
    $file->filesize = filesize($file->filepath);
    return $params;
  }
  return FALSE;
}