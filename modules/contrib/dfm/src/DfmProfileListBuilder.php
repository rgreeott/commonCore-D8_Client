<?php

namespace Drupal\dfm;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a list of Dfm Profile entities.
 *
 * @see \Drupal\dfm\Entity\DfmProfile
 */
class DfmProfileListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Name');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $dfm_profile) {
    $row['label'] = $dfm_profile->label();
    $row['description'] = $dfm_profile->get('description');
    return $row + parent::buildRow($dfm_profile);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $dfm_profile) {
    $operations = parent::getDefaultOperations($dfm_profile);
    $operations['duplicate'] = [
      'title' => t('Duplicate'),
      'weight' => 15,
      'url' => $dfm_profile->toUrl('duplicate-form'),
    ];
    return $operations;
  }

}
