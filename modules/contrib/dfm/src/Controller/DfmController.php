<?php

namespace Drupal\dfm\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\dfm\Dfm;

/**
 * Controller routines for dfm routes.
 */
class DfmController extends ControllerBase {

  /**
   * Returns an administrative overview of Dfm Profiles.
   */
  public function adminOverview(Request $request) {
    // Build the settings form first.(may redirect)
    $output['settings_form'] = \Drupal::formBuilder()->getForm('Drupal\dfm\Form\DfmSettingsForm') + ['#weight' => 10];
    // Buld profile list.
    $output['profile_list'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dfm-profile-list']],
      'title' => ['#markup' => '<h2>' . $this->t('Configuration Profiles') . '</h2>'],
      'list' => $this->entityTypeManager()->getListBuilder('dfm_profile')->render(),
    ];
    return $output;
  }

  /**
   * Handles requests to /dfm/{scheme} path.
   */
  public function page($scheme, Request $request) {
    return Dfm::response($request, $this->currentUser(), $scheme);
  }

  /**
   * Checks access to /dfm/{scheme} path.
   */
  public function checkAccess($scheme) {
    return AccessResult::allowedIf(Dfm::access($this->currentUser(), $scheme));
  }

}
