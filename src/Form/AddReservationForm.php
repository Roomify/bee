<?php

namespace Drupal\bee\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\bat_event_series\Entity\EventSeries;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\office_hours\OfficeHoursDateHelper;
use RRule\RRule;

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
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, EventSeries $bat_event_series = NULL) {
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
      '#date_increment' => 60,
    ];

    $form['end_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('End date'),
      '#default_value' => ($bee_settings['bookable_type'] == 'daily') ? $tomorrow->format('Y-m-d') : new DrupalDateTime($one_hour_later->format('Y-m-d H:00')),
      '#date_increment' => 60,
    ];

    if ($bat_event_series) {
      $form['event_series'] = [
        '#type' => 'hidden',
        '#value' => $bat_event_series->id(),
      ];
    }
    else {
      $form['repeat'] = [
        '#type' => 'checkbox',
        '#title' => t('This booking repeats'),
        '#prefix' => '<div class="form-row">',
      ];

      $form['repeat_frequency'] = [
        '#type' => 'select',
        '#title' => t('Repeat frequency'),
        '#options' => [
          'daily' => t('Daily'),
          'weekly' => t('Weekly'),
          'monthly' => t('Monthly'),
        ],
        '#states' => [
          'visible' => [
            ':input[name="repeat"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['repeat_until'] = [
        '#type' => 'date',
        '#title' => t('Repeat until'),
        '#states' => [
          'visible' => [
            ':input[name="repeat"]' => ['checked' => TRUE],
          ],
        ],
        '#suffix' => '</div>',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Reservation'),
    ];

    $form['#attached']['library'][] = 'bee/bee_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $node = Node::load($values['node']);
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }

    $date_start_date = $start_date->format('Y-m-d');
    $date_end_date = $end_date->format('Y-m-d');

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
      if ($end_date <= $start_date) {
        $form_state->setErrorByName('end_date', $this->t('End date must be after the start date.'));
        return;
      }

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

      if (!(isset($values['repeat']) && $values['repeat'])) {
        $available_units = $this->getAvailableUnits($values);

        if (empty($available_units)) {
          $form_state->setError($form, t('No available units.'));
        }
      }
    }

    if (isset($values['repeat']) && $values['repeat']) {
      if (empty($values['repeat_until'])) {
        $form_state->setErrorByName('repeat_until', t('Repeat until is required if "This booking repeats" is checked.'));
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
      $quantity = 1;

      $booking = bat_booking_create([
        'type' => 'bee',
        'label' => $node->label(),
      ]);
      $booking->set('booking_start_date', $start_date->format('Y-m-d\TH:i:s'));
      $booking->set('booking_end_date', $end_date->format('Y-m-d\TH:i:s'));

      if (isset($values['repeat']) && $values['repeat']) {
        $booking->set('booking_repeat_frequency', $values['repeat_frequency']);
        $booking->set('booking_repeat_until', $values['repeat_until']);

        $quantity = $this->getRepeatingEventsCount($start_date, $values['repeat_frequency'], $values['repeat_until']);
      }

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

      $unit_price = bee_get_unit_price($node, $booking, $start_date, $end_date);

      $order_item = \Drupal::entityTypeManager()->getStorage('commerce_order_item')->create([
        'title' => $node->label(),
        'type' => 'bee',
        'purchased_entity' => $product_variation->id(),
        'quantity' => $quantity,
        'unit_price' => $product_variation->getPrice(),
      ]);
      $order_item->set('field_booking', $booking);
      $order_item->set('field_node', $node);
      $order_item->setUnitPrice($unit_price, TRUE);
      $order_item->save();

      $cart_manager->addOrderItem($cart, $order_item);

      $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $cart->id()]);
    }
    else {
      if ($bee_settings['bookable_type'] == 'daily') {
        $booked_state = bat_event_load_state_by_machine_name('bee_daily_booked');

        if (isset($values['repeat']) && $values['repeat']) {
          $repeat_until = new \DateTime($values['repeat_until'] . 'T235959Z');

          $frequency = t('Day');
          if ($values['repeat_frequency'] == 'weekly') {
            $frequency = $start_date->format('l');
          } elseif ($values['repeat_frequency'] == 'monthly') {
            $frequency = t('@day of Month', ['@day' => $start_date->format('jS')]);
          }

          $label = t('Reservations for @node Every @frequency from @start_date -> @end_date', [
            '@node' => $node->label(),
            '@frequency' => $frequency,
            '@start_date' => $start_date->format('M j Y'),
            '@end_date' => $repeat_until->format('M j Y'),
          ]);

          $rrule = new RRule([
            'FREQ' => strtoupper($values['repeat_frequency']),
            'UNTIL' => $values['repeat_until'] . 'T235959Z',
          ]);

          $event = bat_event_series_create([
            'type' => 'availability_daily',
            'label' => $label,
            'rrule' => $rrule->rfcString(),
          ]);
        }
        else {
          $event = bat_event_create(['type' => 'availability_daily']);
        }

        $event_dates = [
          'value' => $start_date->format('Y-m-d\TH:i:00'),
          'end_value' => $end_date->format('Y-m-d\TH:i:00'),
        ];
        $event->set('event_dates', $event_dates);
        $event->set('event_state_reference', $booked_state->id());
      }
      else {
        $booked_state = bat_event_load_state_by_machine_name('bee_hourly_booked');

        if (isset($values['repeat']) && $values['repeat']) {
          $repeat_until = new \DateTime($values['repeat_until'] . 'T235959Z');

          $frequency = t('Day');
          if ($values['repeat_frequency'] == 'weekly') {
            $frequency = $start_date->format('l');
          } elseif ($values['repeat_frequency'] == 'monthly') {
            $frequency = t('@day of Month', ['@day' => $start_date->format('jS')]);
          }

          $label = t('Reservations for @node Every @frequency from @start_time-@end_time from @start_date -> @end_date', [
            '@node' => $node->label(),
            '@frequency' => $frequency,
            '@start_time' => $start_date->format('gA'),
            '@end_time' => $end_date->format('gA'),
            '@start_date' => $start_date->format('M j Y'),
            '@end_date' => $repeat_until->format('M j Y'),
          ]);

          $rrule = new RRule([
            'FREQ' => strtoupper($values['repeat_frequency']),
            'UNTIL' => $values['repeat_until'] . 'T235959Z',
          ]);

          $event = bat_event_series_create([
            'type' => 'availability_hourly',
            'label' => $label,
            'rrule' => $rrule->rfcString(),
          ]);
        }
        else {
          $event = bat_event_create(['type' => 'availability_hourly']);
        }

        $event_dates = [
          'value' => $start_date->format('Y-m-d\TH:i:00'),
          'end_value' => $end_date->format('Y-m-d\TH:i:00'),
        ];
        $event->set('event_dates', $event_dates);
        $event->set('event_state_reference', $booked_state->id());
      }

      if (isset($values['repeat']) && $values['repeat']) {
        $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

        foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
          if ($unit->entity) {
            $event->set('event_bat_unit_reference', $unit->entity->id());
          }
        }
      }
      else {
        $available_units = $this->getAvailableUnits($values);

        $event->set('event_bat_unit_reference', reset($available_units));
      }

      if (isset($values['event_series'])) {
        $event->set('event_series', $values['event_series']);
      }

      $event->save();

      $this->messenger()->addMessage(t('Reservation created!'));

      if (isset($values['event_series'])) {
        $form_state->setRedirect('entity.bat_event_series.canonical', ['bat_event_series' => $values['event_series']]);
      } else {
        $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
      }
    }
  }

  /**
   * @param $start_date
   * @param $repeat_frequency
   * @param $repeat_until
   *
   * @return int
   */
  protected function getRepeatingEventsCount($start_date, $repeat_frequency, $repeat_until) {
    $rrule = new RRule([
      'FREQ' => strtoupper($repeat_frequency),
      'UNTIL' => $repeat_until . 'T235959Z',
      'DTSTART' => $start_date,
    ]);

    return $rrule->count();
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
      if ($unit->entity) {
        $units_ids[] = $unit->entity->id();
      }
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
