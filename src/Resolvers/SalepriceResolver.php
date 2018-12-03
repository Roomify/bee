<?php

namespace Drupal\bee\Resolvers;

use Drupal\node\Entity\Node;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
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
          if ($order_item->getPurchasedEntityId() == $entity->id()) {
            $query = \Drupal::entityQuery('node')
              ->condition('field_product', $entity->getProductId());

            $nids = $query->execute();
            $node = Node::load(reset($nids));

            $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

            if ($booking = $order_item->get('field_booking')->entity) {
              $start_date = new \DateTime($booking->get('booking_start_date')->value);
              $end_date = new \DateTime($booking->get('booking_end_date')->value);

              $interval = $start_date->diff($end_date);

              $reservation_context = [
                'order_item' => $order_item,
                'booking' => $booking,
                'node' => $node,
              ];

              $base_price = $node->get('field_price')->number;
              $currency_code = $node->get('field_price')->currency_code;

              if ($bee_settings['bookable_type'] == 'daily') {
                $days = $interval->days;
                $amount = number_format($base_price * $days, 2, '.', '');
              }
              else {
                $field_price_frequency = $node->get('field_price_frequency')->value;

                if ($field_price_frequency == 'hour') {
                  $hours = ($interval->days * 24) + $interval->h;
                  $amount = number_format($base_price * $hours, 2, '.', '');
                }
                else {
                  $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                  $amount = number_format($base_price * $minutes, 2, '.', '');
                }
              }

              $price = new Price($amount, $currency_code);

              \Drupal::moduleHandler()->alter('bee_reservation_price', $price, $reservation_context);

              return $price;
            }
          }
        }
      }
    }
  }

}
