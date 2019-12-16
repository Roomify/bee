<?php

namespace Drupal\bee;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class BeePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new BeePermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   *
   */
  public function permissions() {
    $permissions = [];
    $bundles = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($bundles['node'] as $bundle_name => $bundle_info) {
      $permissions['manage availability for all ' . $bundle_name . ' nodes'] = [
        'title' => t('Manage availability for all %bundle nodes', ['%bundle' => $bundle_info['label']]),
      ];

      $permissions['manage availability for own ' . $bundle_name . ' nodes'] = [
        'title' => t('Manage availability for own %bundle nodes', ['%bundle' => $bundle_info['label']]),
      ];
    }

    return $permissions;
  }

}
