<?php

namespace Drupal\dfm\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Dfm Profile entity.
 *
 * @ConfigEntityType(
 *   id = "dfm_profile",
 *   label = @Translation("Dfm Profile"),
 *   handlers = {
 *     "list_builder" = "Drupal\dfm\DfmProfileListBuilder",
 *     "form" = {
 *       "add" = "Drupal\dfm\Form\DfmProfileForm",
 *       "edit" = "Drupal\dfm\Form\DfmProfileForm",
 *       "delete" = "Drupal\dfm\Form\DfmProfileDeleteForm",
 *       "duplicate" = "Drupal\dfm\Form\DfmProfileForm"
 *     }
 *   },
 *   admin_permission = "administer dfm",
 *   config_prefix = "profile",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/media/dfm/{dfm_profile}",
 *     "delete-form" = "/admin/config/media/dfm/{dfm_profile}/delete",
 *     "duplicate-form" = "/admin/config/media/dfm/{dfm_profile}/duplicate"
 *   }
 * )
 */
class DfmProfile extends ConfigEntityBase {

  /**
   * Profile ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Label.
   *
   * @var string
   */
  protected $label;

  /**
   * Description.
   *
   * @var string
   */
  protected $description;

  /**
   * Configuration options.
   *
   * @var array
   */
  protected $conf = [];

  /**
   * Returns configuration options.
   */
  public function getConf($key = NULL, $default = NULL) {
    $conf = $this->conf;
    if (isset($key)) {
      return isset($conf[$key]) ? $conf[$key] : $default;
    }
    return $conf;
  }

}
