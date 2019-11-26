<?php

namespace Drupal\bee\Resolvers;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

/**
 * Class SalepriceResolver
 *
 * @package Drupal\bee\Resolvers
 */
class SalepriceResolver implements PriceResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    if ($entity->bundle() != 'bee') {
      return;
    }

    $cart_provider = \Drupal::service('commerce_cart.cart_provider');

    $stores = $entity->getProduct()->getStores();
    $store = reset($stores);

    if ($cart = $cart_provider->getCart('default', $store)) {
      $order_items = $cart->getItems();
      foreach ($order_items as $order_item) {
        if ($order_item->bundle() == 'bee') {
          return $order_item->getUnitPrice();
        }
      }
    }
  }

}
