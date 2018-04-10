<?php

namespace Drupal\bee\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;

class UpdateAvailabilityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bee_update_availability_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $form['availability'] = [
      '#type' => 'details',
      '#title' => t('Update availability'),
    ];

    $form['availability']['state'] = [
      '#type' => 'select',
      '#title' => t('State'),
      '#options' => [
        'available' => t('Available'),
        'unavailable' => t('Unavailable'),
      ],
    ];

    $form['availability']['start_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('Start'),
      '#date_increment' => 3600,
      '#required' => TRUE,
    ];

    $form['availability']['end_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('End'),
      '#date_increment' => 3600,
      '#required' => TRUE,
    ];

    $form['availability']['repeat'] = [
      '#type' => 'checkbox',
      '#title' => t('This event repeats'),
    ];

    $form['availability']['repeat_frequency'] = [
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

    $form['availability']['repeat_until'] = [
      '#type' => 'date',
      '#title' => t('Repeat until'),
      '#states' => [
        'visible' => [
          ':input[name="repeat"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['availability']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update Availability'),
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

    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);
    }
    else {
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));
    }

    // The end date must be greater or equal than start date.
    if ($end_date < $start_date) {
      $form_state->setErrorByName('end_date', t('End date must be on or after the start date.'));
    }

    if ($values['repeat']) {
      if (empty($values['repeat_until'])) {
        $form_state->setErrorByName('repeat_until', t('Repeat until is required if "This event repeats" is checked.'));
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

      if ($values['repeat']) {
        $repeat_interval = $start_date->diff($end_date);

        if ($values['repeat_frequency'] == 'daily') {
          $interval = new \DateInterval('P1D');
        }
        elseif ($values['repeat_frequency'] == 'weekly') {
          $interval = new \DateInterval('P1W');
        }
        elseif ($values['repeat_frequency'] == 'monthly') {
          $interval = new \DateInterval('P1M');
        }

        $repeat_until_date = new \DateTime($values['repeat_until']);

        $repeat_period = new \DatePeriod($start_date, $interval, $repeat_until_date);

        foreach ($repeat_period as $date) {
          $temp_end_date = clone($date);
          $temp_end_date->add($repeat_interval);

          $this->createDailyEvent($date, $temp_end_date, $bee_settings['type_id'], $values['state']);
        }
      }
      else {
        $this->createDailyEvent($start_date, $end_date, $bee_settings['type_id'], $values['state']);
      }
    }
    else {
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));

      if ($values['repeat']) {
        $repeat_interval = $start_date->diff($end_date);

        if ($values['repeat_frequency'] == 'daily') {
          $interval = new \DateInterval('P1D');
        }
        elseif ($values['repeat_frequency'] == 'weekly') {
          $interval = new \DateInterval('P1W');
        }
        elseif ($values['repeat_frequency'] == 'monthly') {
          $interval = new \DateInterval('P1M');
        }

        $repeat_until_date = new \DateTime($values['repeat_until']);

        $repeat_period = new \DatePeriod($start_date, $interval, $repeat_until_date);

        foreach ($repeat_period as $date) {
          $temp_end_date = clone($date);
          $temp_end_date->add($repeat_interval);

          $this->createHoulyEvent($date, $temp_end_date, $bee_settings['type_id'], $values['state']);
        }
      }
      else {
        $this->createHoulyEvent($start_date, $end_date, $bee_settings['type_id'], $values['state']);
      }
    }
  }

  /**
   * @param $start_date
   * @param $end_date
   * @param $type_id
   * @param $new_state
   */
  private function createDailyEvent($start_date, $end_date, $type_id, $new_state) {
    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    $booked_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_daily_booked'], $type_id, 'availability_daily');

    $available_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_daily_available', 'bee_daily_not_available'], $type_id, 'availability_daily');

    if ($booked_units === FALSE) {
      if ($new_state == 'available') {
        $state = bat_event_load_state_by_machine_name('bee_daily_available');
      }
      else {
        $state = bat_event_load_state_by_machine_name('bee_daily_not_available');
      }

      $event = bat_event_create(['type' => 'availability_daily']);
      $event_dates = [
        'value' => $start_date->format('Y-m-d\TH:i:00'),
        'end_value' => $end_date->format('Y-m-d\TH:i:00'),
      ];
      $event->set('event_dates', $event_dates);
      $event->set('event_state_reference', $state->id());
      $event->set('event_bat_unit_reference', reset($available_units));
      $event->save();
    }
    else {
      drupal_set_message(t('Cannot create event @start @end', [
        '@start' => $start_date->format('Y-m-d'),
        '@end' => $end_date->format('Y-m-d'),
      ]), 'warning');
    }
  }

  /**
   * @param $start_date
   * @param $end_date
   * @param $type_id
   * @param $new_state
   */
  private function createHoulyEvent($start_date, $end_date, $type_id, $new_state) {
    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    $booked_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_hourly_booked'], $type_id, 'availability_hourly');

    $available_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_hourly_available', 'bee_hourly_not_available'], $type_id, 'availability_hourly');

    if ($booked_units === FALSE) {
      if ($new_state == 'available') {
        $state = bat_event_load_state_by_machine_name('bee_hourly_available');
      }
      else {
        $state = bat_event_load_state_by_machine_name('bee_hourly_not_available');
      }

      $event = bat_event_create(['type' => 'availability_hourly']);
      $event_dates = [
        'value' => $start_date->format('Y-m-d\TH:i:00'),
        'end_value' => $end_date->format('Y-m-d\TH:i:00'),
      ];
      $event->set('event_dates', $event_dates);
      $event->set('event_state_reference', $state->id());
      $event->set('event_bat_unit_reference', reset($available_units));
      $event->save();
    }
    else {
      drupal_set_message(t('Cannot create event @start @end', [
        '@start' => $start_date->format('Y-m-d H:i'),
        '@end' => $end_date->format('Y-m-d H:i'),
      ]), 'warning');
    }
  }

}
