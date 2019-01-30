<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_resize.
 */
function _dfm_resize_ajax_resize(Dfm $dfm) {
  if (empty($_POST['w']) || empty($_POST['h'])) return;
  // Check params.
  $w = (int) $_POST['w'];
  $h = (int) $_POST['h'];
  // Min dimensions are 1x1
  if ($w < 1 || $h < 1) return;
  // Check max dimensions
  if (!_dfm_resize_check_max_dim($dfm, $w, $h)) return;
  // Prepare params and do the resizing.
  $imgcopy = $dfm->getConf('imgCopy');
  $postcopy = !empty($_POST['cp']);
  $params = array('dw' => $w, 'dh' => $h);
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    if (isset($content['files']) && $dfm->dirPerm($dirname, 'resize')) {
      $params['cp'] = $postcopy && ($imgcopy || $dfm->dirPerm($dirname, 'copyFiles'));
      if ($new_fail = _dfm_resize_dir_files($dfm, $dirname, $content['files'], $params)) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to resize: %names.', $fail);
  }
}

/**
 * Resizes/crops a list of files under the given directory.
 */
function _dfm_resize_dir_files(Dfm $dfm, $dirname, array $filenames, array $params) {
  $success = array();
  $dirpath = $dfm->dirPath($dirname);
  // Prepare copy
  if ($copy = !empty($params['cp'])) {
    $prefix = $dfm->getConf('copyNamePrefix', '');
    $suffix = $dfm->getConf('copyNameSuffix', ' - Copy');
  }
  if (!isset($params['q'])) $params['q'] = $dfm->getConf('imgJpegQuality', 85);
  // Operation validator
  if (!isset($params['op'])) $params['op'] = 'resize';
  $validator = '_dfm_resize_op_' . $params['op'] . '_validate';
  if (!function_exists($validator)) $validator = FALSE;
  // Iterate over each file
  foreach ($filenames as $filename) {
    // Check existence. Do not operate on unlisted files.
    if (!$filepath = $dfm->validateDirFile($dirname, $filename)) continue;
    $props = $dfm->fileProps($filepath);
    // Check if the file is an image
    if (empty($props['width']) || empty($props['height'])) continue;
    // Require dimension change.
    if ($params['dw'] == $props['width'] && $params['dh'] == $props['height']) continue;
    // Custom validation
    if ($validator && !$validator($dfm, $filepath, $params, $props)) continue;
    // Check required handler and prepare resize parameters.
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $type = $ext == 'jpg' ? 'jpeg' : $ext;
    if (!function_exists('imagecreatefrom' . $type)) {
      $dfm->setMessage('Missing image handler for %type type!', array('%type' => $type));
      continue;
    }
    // Prepare paths
    if ($copy) {
      $destname = _dfm_resize_create_filename($dirpath, $filename, $prefix, $suffix);
      $destpath = $dfm->joinPaths($dirpath, $destname);
    }
    else {
      $destpath = $filepath;
    } 
    // Perform resizing
    $params += array('sw' => $props['width'], 'sh' => $props['height'], 'type' => $type);
    if (!_dfm_resize_filepath($dfm, $filepath, $destpath, $params)) continue;
    $success[] = $filename;
    $dfm->addDirFile($dirname, $copy ? $destname : $filename);
  }
  // Return failures
  return array_diff($filenames, $success);
}

/**
 * Resizes/crops a file path by invoking hooks.
 */
function _dfm_resize_filepath(Dfm $dfm, $filepath, $destpath, array $params) {
  $params['existing'] = file_exists($destpath);
  if (in_array(FALSE, $dfm->invoke($params['op'] . '_filepath_validate', $filepath, $destpath, $params), TRUE)) return FALSE;
  if (!_dfm_resize_image($filepath, $destpath, $params)) return FALSE;
  if (!$params['existing']) @chmod($destpath, $dfm->getConf('fileMode', 0664));
  $dfm->invoke($params['op'] . '_filepath', $filepath, $destpath, $params);
  return TRUE;
}

/**
 * Resizes/crops an image file and returns success.
 */
function _dfm_resize_image($filepath, $destpath, array $params) {
  // Check required handler first.
  $type = $params['type'];
  $func = 'imagecreatefrom' . $type;
  if (!function_exists($func)) return FALSE;
  // Create source
  if (!$src = $func($filepath)) return FALSE;
  // Create destination
  if (!$destsrc = _dfm_resize_resample($src, $params)) {
    imagedestroy($src);
    return FALSE;
  }
  // Save destination
  $func = 'image' . $type;
  $ret = FALSE;
  if (function_exists($func)) {
    // imageX functions does not support stream wrappers. We'll use a local temp file and copy it to the destination.
    $realdest = FALSE;
    if (strpos($destpath, '://')) {
      if ($temp = tempnam(realpath(sys_get_temp_dir()), 'dfm')) {
        $realdest = $destpath;
        $destpath = $temp;
      }
    }
    // Save image
    if ($type == 'jpeg') $ret = $func($destsrc, $destpath, isset($params['q']) ? $params['q'] : 85);
    else $ret = $func($destsrc, $destpath);
    // Copy the temp file over the destination path
    if ($realdest) {
      $ret = copy($destpath, $realdest);
      @unlink($destpath);
    }
  }
  imagedestroy($src);
  imagedestroy($destsrc);
  return $ret;
}

/**
 * Resizes/crops the given image source and returns a new one.
 */
function _dfm_resize_resample($src, array $params) {
  extract($params + array('dx' => 0, 'dy' => 0, 'sx' => 0, 'sy' => 0));
  // Prepare destination source
  $dest = imagecreatetruecolor($dw, $dh);
  // Set gif and png transperancy
  if ($type == 'gif') {
    $trans = imagecolortransparent($src);
    if ($trans > -1 && $trans < imagecolorstotal($src)) {
      $transcolor = imagecolorsforindex($src, $trans);
      $trans = imagecolorallocate($dest, $transcolor['red'], $transcolor['green'], $transcolor['blue']);
      imagefill($dest, 0, 0, $trans);
      imagecolortransparent($dest, $trans);
    }
  }
  elseif ($type == 'png') {
    imagealphablending($dest, FALSE);
    $trans = imagecolorallocatealpha($dest, 0, 0, 0, 127);
    imagefill($dest, 0, 0, $trans);
    imagealphablending($dest, TRUE);
    imagesavealpha($dest, TRUE);
  }
  if (!imagecopyresampled($dest, $src, $dx, $dy, $sx, $sy, $dw, $dh, $sw, $sh)) {
    imagedestroy($dest);
    return FALSE;
  }
  return $dest;
}

/**
 * Returns a non-existing variant of filename under the directory path.
 */
function _dfm_resize_create_filename($dirpath, $filename, $prefix = '', $suffix = '') {
  $pos = strrpos($filename, '.');
  $name = substr($filename, 0, $pos);
  $ext = substr($filename, $pos);
  $name = $prefix . $name . $suffix;
  $filename = $name . $ext;
  for ($i = 2; file_exists($dirpath . '/' . $filename); $i++) {
    $filename = $name . ' ' . $i . $ext;
  }
  return $filename;
}

/**
 * Validates maximum dimensions.
 */
function _dfm_resize_check_max_dim(Dfm $dfm, $w, $h) {
  if ($maxdim = $dfm->getConf('imgMaxDim')) {
    list($maxw, $maxh) = explode('x', $maxdim);
    if ($maxw && $w > $maxw || $maxh && $h > $maxh) return FALSE;
  }
  return TRUE;
}

/**
 * Validates resize parameters against the given image properties.
 */
function _dfm_resize_op_resize_validate(Dfm $dfm, $filepath, array $params, array $props) {
  // Prevent upscale if not allowed
  return $dfm->getConf('imgUpscale', FALSE) || $props['width'] > $params['dw'] && $props['height'] > $params['dh'];
}