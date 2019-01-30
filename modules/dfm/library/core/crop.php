<?php
/**
 * @file
 * 
 */

/**
 * Callback for ajax_crop.
 */
function _dfm_crop_ajax_crop(Dfm $dfm) {
  if (!isset($_POST['x']) || !isset($_POST['y']) || empty($_POST['w']) || empty($_POST['h'])) return;
  // Validate parameters.
  $x = (int) $_POST['x'];
  $y = (int) $_POST['y'];
  $w = (int) $_POST['w'];
  $h = (int) $_POST['h'];
  if ($x < 0 || $y < 0 || $w < 1 || $h < 1) return;
  // Include resize library
  $dfm->includeFile('resize.php');
  // Check max dimensions
  if (!_dfm_resize_check_max_dim($dfm, $w, $h)) return;
  // Prepare parameters and do the cropping.
  $imgcopy = $dfm->getConf('imgCopy');
  $postcopy = !empty($_POST['cp']);
  $params = array('sx' => $x, 'sy' => $y, 'sw' => $w, 'sh' => $h, 'dw' => $w, 'dh' => $h, 'op' => 'crop');
  $fail = array();
  foreach ($dfm->getSelectedItems() as $dirname => $content) {
    if (isset($content['files']) && $dfm->dirPerm($dirname, 'crop')) {
      $params['cp'] = $postcopy && ($imgcopy || $dfm->dirPerm($dirname, 'copyFiles'));
      if ($new_fail = _dfm_resize_dir_files($dfm, $dirname, $content['files'], $params)) {
        $fail = array_merge($fail, $new_fail);
      }
    }
  }
  if ($fail) {
    $dfm->setNamesMessage('Failed to crop: %names.', $fail);
  }
}

/**
 * Validates crop parameters against the given image properties.
 */
function _dfm_resize_op_crop_validate(Dfm $dfm, $filepath, array $params, array $props) {
  // Check crop boundaries.
  return $props['width'] >= $params['sx'] + $params['sw'] && $props['height'] >= $params['sy'] + $params['sh'];
}