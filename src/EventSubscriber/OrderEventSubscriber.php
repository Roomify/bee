<?php

namespace Drupal\bee\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new OrderEventSubscriber object.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = ['commerce_order.place.pre_transition' => 'finalizeCart'];
    return $events;
  }

  /**
   * Finalizes the cart when the order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function finalizeCart(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();

    foreach ($order->getItems() as $item) {
      if ($booking = $item->get('field_booking')->entity) {
        $node = $item->get('field_node')->entity;

        $start_date = new \DateTime($booking->get('booking_start_date')->value);
        $end_date = new \DateTime($booking->get('booking_end_date')->value);

        $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

        if ($bee_settings['bookable_type'] == 'daily') {
          $booked_state = bat_event_load_state_by_machine_name('bee_daily_booked');

          $event = bat_event_create(['type' => 'availability_daily']);
          $event_dates = [
            'value' => $start_date->format('Y-m-d\TH:i:00'),
            'end_value' => $end_date->format('Y-m-d\TH:i:00'),
          ];
          $event->set('event_dates', $event_dates);
          $event->set('event_state_reference', $booked_state->id());
        }
        else {
          $booked_state = bat_event_load_state_by_machine_name('bee_hourly_booked');

          $event = bat_event_create(['type' => 'availability_hourly']);
          $event_dates = [
            'value' => $start_date->format('Y-m-d\TH:i:00'),
            'end_value' => $end_date->format('Y-m-d\TH:i:00'),
          ];
          $event->set('event_dates', $event_dates);
          $event->set('event_state_reference', $booked_state->id());
        }

        $available_units = $this->getAvailableUnits($node, $start_date, $end_date);

        $event->set('event_bat_unit_reference', reset($available_units));
        $event->save();

        $booking->set('booking_event_reference', $event->id());
        $booking->save();
      }
    }
  }

  /**
   * Get available Units.
   *
   * @param $node
   * @param $start_date
   * @param $end_date
   *
   * return array
   */
  protected function getAvailableUnits($node, $start_date, $end_date) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      $units_ids[] = $unit->entity->id();
    }

    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    if ($bee_settings['bookable_type'] == 'daily') {
      $available_units_ids = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_daily_available'], $bee_settings['type_id'], 'availability_daily');
    }
    else {
      $available_units_ids = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_hourly_available'], $bee_settings['type_id'], 'availability_hourly');
    }

    return array_intersect($units_ids, $available_units_ids);
  }

}
