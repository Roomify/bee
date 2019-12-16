<?php

namespace Drupal\bee\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 *
 */
class BeeAddReservationAccessCheck implements AccessInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a BeeAddReservationAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(AccountInterface $account, Node $node = NULL) {
    $bee_settings = $this->configFactory->get('node.type.' . $node->bundle())->get('bee');

    if (isset($bee_settings['bookable']) && $bee_settings['bookable']) {
      if ($account->hasPermission('create bee reservation')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

}
