<?php

namespace Drupal\bee\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

class BeeAvailabilityAccessCheck implements AccessInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BeeAvailabilityAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(AccountInterface $account, Node $node = NULL) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    if (isset($bee_settings['bookable']) && $bee_settings['bookable']) {
      if ($account->hasPermission('admin ' . $node->bundle() . ' bee availability')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

}
