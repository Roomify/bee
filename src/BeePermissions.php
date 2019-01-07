<?php

namespace Drupal\bee;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BeePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BeePermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   *
   */
  public function permissions() {
    $permissions = [];

    foreach (entity_get_bundles('node') as $bundle_name => $bundle_info) {
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
