<?php

namespace Drupal\bee\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;

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

    $available_units = $this->getAvailableUnits($values);
    
    if (empty($available_units)) {
      $form_state->setError($form, t('No available units.'));
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

    $units_ids = [];
    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      $units_ids[] = $unit->entity->id();
    }

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);

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
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));

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
