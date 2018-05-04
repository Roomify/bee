<?php

namespace Drupal\bee\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\office_hours\OfficeHoursDateHelper;

class AddReservationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bee_add_reservation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $today = new \DateTime();

    $tomorrow = clone($today);
    $tomorrow->modify('+1 day');

    $one_hour_later = clone($today);
    $one_hour_later->modify('+1 hour');

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $form['start_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('Start date'),
      '#default_value' => ($bee_settings['bookable_type'] == 'daily') ? $today->format('Y-m-d') : new DrupalDateTime($today->format('Y-m-d H:00')),
      '#date_increment' => 3600,
    ];

    $form['end_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('End date'),
      '#default_value' => ($bee_settings['bookable_type'] == 'daily') ? $tomorrow->format('Y-m-d') : new DrupalDateTime($one_hour_later->format('Y-m-d H:00')),
      '#date_increment' => 3600,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Reservation'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $node = Node::load($values['node']);

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $date_start_date = $start_date->format('Y-m-d');
    $date_end_date = $end_date->format('Y-m-d');

    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $dates_valid = TRUE;

    if ($bee_settings['bookable_type'] == 'hourly') {
      // Validate the input dates.
      if (!$start_date instanceof DrupalDateTime) {
        $form_state->setErrorByName('start_date', $this->t('The start date is not valid.'));
        $dates_valid = FALSE;
      }
      if (!$end_date instanceof DrupalDateTime) {
        $form_state->setErrorByName('end_date', $this->t('The end date is not valid.'));
        $dates_valid = FALSE;
      }
    }

    if ($dates_valid) {
      if ($bee_settings['bookable_type'] == 'hourly') {
        if ($node->get('field_use_open_hours')->value) {
          $open_hours = $node->get('field_open_hours')->getValue();

          $start_date_open_hour = FALSE;
          $end_date_open_hour = FALSE;

          foreach ($open_hours as $open_hour) {
            if ($open_hour['day'] == $start_date->format('N')) {
              $starthours = OfficeHoursDateHelper::format($open_hour['starthours'], 'H:i');
              $endhours = OfficeHoursDateHelper::format($open_hour['endhours'], 'H:i');

              $start_date_open_hour = [
                'start' => new DrupalDateTime($date_start_date . ' ' . $starthours),
                'end' => new DrupalDateTime($date_start_date . ' ' . $endhours),
              ];

              if ($start_date_open_hour['end'] < $start_date_open_hour['start']) {
                $start_date_open_hour['end']->modify('+1 day');

                if ($start_date_open_hour['end']->format('Y-m-d') == $date_end_date) {
                  $end_date_open_hour = $start_date_open_hour;
                }
              }
            }

            if ($date_start_date == $date_end_date) {
              if ($open_hour['day'] == $end_date->format('N')) {
                $starthours = OfficeHoursDateHelper::format($open_hour['starthours'], 'H:i');
                $endhours = OfficeHoursDateHelper::format($open_hour['endhours'], 'H:i');

                $end_date_open_hour = [
                  'start' => new DrupalDateTime($date_end_date . ' ' . $starthours),
                  'end' => new DrupalDateTime($date_end_date . ' ' . $endhours),
                ];

                if ($end_date_open_hour['end'] < $end_date_open_hour['start']) {
                  $end_date_open_hour['end']->modify('+1 day');
                }
              }
            }
          }

          if ((!$start_date_open_hour || !$end_date_open_hour) ||
              !(($start_date >= $start_date_open_hour['start'] && $start_date <= $start_date_open_hour['end']) &&
                ($end_date >= $end_date_open_hour['start'] && $end_date <= $end_date_open_hour['end']))) {
            $form_state->setError($form, t('Please select start and end times within the opening hours.'));
          }
        }
      }

      $available_units = $this->getAvailableUnits($values);

      if (empty($available_units)) {
        $form_state->setError($form, t('No available units.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $node = Node::load($values['node']);
    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }
    else {
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));
    }

    if ($bee_settings['payment']) {
      $booking = bat_booking_create([
        'type' => 'bee',
        'label' => $node->label(),
      ]);
      $booking->set('booking_start_date', $start_date->format('Y-m-d H:i:s'));
      $booking->set('booking_end_date', $end_date->format('Y-m-d H:i:s'));
      $booking->save();

      $product = $node->get('field_product')->entity;

      $stores = $product->getStores();
      $store = reset($stores);

      $variations = $product->getVariations();
      $product_variation = reset($variations);

      $cart_manager = \Drupal::service('commerce_cart.cart_manager');
      $cart_provider = \Drupal::service('commerce_cart.cart_provider');

      $cart = $cart_provider->getCart('default', $store);
      if (!$cart) {
        $cart = $cart_provider->createCart('default', $store);
      }
      else {
        $cart_manager->emptyCart($cart);
      }

      $order_item = \Drupal::entityManager()->getStorage('commerce_order_item')->create([
        'title' => $node->label(),
        'type' => 'bee',
        'purchased_entity' => $product_variation->id(),
        'quantity' => 1,
        'unit_price' => $product_variation->getPrice(),
      ]);
      $order_item->set('field_booking', $booking);
      $order_item->set('field_node', $node);
      $order_item->save();

      $cart_manager->addOrderItem($cart, $order_item);

      $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $cart->id()]);
    }
    else {
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

      $available_units = $this->getAvailableUnits($values);

      $event->set('event_bat_unit_reference', reset($available_units));
      $event->save();

      drupal_set_message(t('Reservation created!'));

      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }
  }

  /**
   * Get available Units.
   *
   * @param $values
   *
   * return array
   */
  protected function getAvailableUnits($values) {
    $node = Node::load($values['node']);
    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      $units_ids[] = $unit->entity->id();
    }

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
      $end_date->sub(new \DateInterval('PT1M'));

      $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bee_daily_available'], $bee_settings['type_id'], 'availability_daily');
    }
    else {
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));
      $end_date->sub(new \DateInterval('PT1M'));

      $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bee_hourly_available'], $bee_settings['type_id'], 'availability_hourly');
    }

    return array_intersect($units_ids, $available_units_ids);
  }

}
