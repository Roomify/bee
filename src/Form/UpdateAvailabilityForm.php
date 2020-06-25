<?php

namespace Drupal\bee\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class UpdateAvailabilityForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new UpdateAvailabilityForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

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
    $bee_settings = $this->configFactory->get('node.type.' . $node->bundle())->get('bee');
    $today = new \DateTime();

    $tomorrow = clone($today);
    $tomorrow->modify('+1 day');

    $one_hour_later = clone($today);
    $one_hour_later->modify('+1 hour');

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
      '#prefix' => '<div class="form-row">',
    ];

    $form['availability']['start_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('Start'),
      '#date_increment' => 60,
      '#default_value' => ($bee_settings['bookable_type'] == 'daily') ? $today->format('Y-m-d') : new DrupalDateTime($today->format('Y-m-d H:00')),
      '#required' => TRUE,
    ];

    $form['availability']['end_date'] = [
      '#type' => ($bee_settings['bookable_type'] == 'daily') ? 'date' : 'datetime',
      '#title' => t('End'),
      '#default_value' => ($bee_settings['bookable_type'] == 'daily') ? $tomorrow->format('Y-m-d') : new DrupalDateTime($one_hour_later->format('Y-m-d H:00')),
      '#date_increment' => 60,
      '#required' => TRUE,
      '#suffix' => '</div>',
    ];

    $form['availability']['repeat'] = [
      '#type' => 'checkbox',
      '#title' => t('This event repeats'),
      '#prefix' => '<div class="form-row">',
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
      '#suffix' => '</div>',
    ];

    $form['availability']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update Availability'),
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

    $start_date = $values['start_date'];
    $end_date = $values['end_date'];

    $bee_settings = $this->configFactory->get('node.type.' . $node->bundle())->get('bee');

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

    $bee_settings = $this->configFactory->get('node.type.' . $node->bundle())->get('bee');

    if ($bee_settings['bookable_type'] == 'daily') {
      $start_date = new \DateTime($start_date);
      $end_date = new \DateTime($end_date);

      if ($values['repeat']) {
        $repeat_interval = $start_date->diff($end_date);

        $interval = new \DateInterval('P1D');
        if ($values['repeat_frequency'] == 'weekly') {
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

          $this->createDailyEvent($node, $date, $temp_end_date, $bee_settings['type_id'], $values['state']);
        }
      }
      else {
        $this->createDailyEvent($node, $start_date, $end_date, $bee_settings['type_id'], $values['state']);
      }
    }
    else {
      $start_date = new \DateTime($start_date->format('Y-m-d H:i'));
      $end_date = new \DateTime($end_date->format('Y-m-d H:i'));

      if ($values['repeat']) {
        $repeat_interval = $start_date->diff($end_date);

        $interval = new \DateInterval('P1D');
        if ($values['repeat_frequency'] == 'weekly') {
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

          $this->createHoulyEvent($node, $date, $temp_end_date, $bee_settings['type_id'], $values['state']);
        }
      }
      else {
        $this->createHoulyEvent($node, $start_date, $end_date, $bee_settings['type_id'], $values['state']);
      }
    }
  }

  /**
   * @param \Drupal\node\Entity\Node $node
   * @param \DateTime $start_date
   * @param \DateTime $end_date
   * @param int $type_id
   * @param string $new_state
   */
  private function createDailyEvent(Node $node, \DateTime $start_date, \DateTime $end_date, $type_id, $new_state) {
    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    $booked_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_daily_booked'], $type_id, 'availability_daily');

    $available_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_daily_available', 'bee_daily_not_available'], $type_id, 'availability_daily');

    $units_ids = [];
    foreach ($node->get('field_availability_daily') as $unit) {
      if ($unit->entity) {
        $units_ids[] = $unit->entity->id();
      }
    }

    $units = array_intersect($units_ids, $available_units);

    if ($available_units) {
      if ($new_state == 'available') {
        $state = bat_event_load_state_by_machine_name('bee_daily_available');
      }
      else {
        $state = bat_event_load_state_by_machine_name('bee_daily_not_available');
      }

      foreach ($available_units as $unit) {
        $event = bat_event_create(['type' => 'availability_daily']);
        $event_dates = [
          'value' => $start_date->format('Y-m-d\TH:i:00'),
          'end_value' => $end_date->format('Y-m-d\TH:i:00'),
        ];
        $event->set('event_dates', $event_dates);
        $event->set('event_state_reference', $state->id());
        $event->set('event_bat_unit_reference', $unit);
        $event->save();
      }
    }

    foreach ($booked_units as $unit) {
      $bat_unit = bat_unit_load($unit);
      $this->messenger()->addWarning(t('Could not create event from @start to @end for unit @label with ID: @unit', [
        '@unit' => $unit,
        '@label' => $bat_unit->label(),
        '@start' => $start_date->format('Y-m-d'),
        '@end' => $end_date->format('Y-m-d'),
      ]));
    }
  }

  /**
   * @param \Drupal\node\Entity\Node $node
   * @param \DateTime $start_date
   * @param \DateTime $end_date
   * @param int $type_id
   * @param string $new_state
   */
  private function createHoulyEvent(Node $node, \DateTime $start_date, \DateTime $end_date, $type_id, $new_state) {
    $temp_end_date = clone($end_date);
    $temp_end_date->sub(new \DateInterval('PT1M'));

    $booked_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_hourly_booked'], $type_id, 'availability_hourly');

    $available_units = bat_event_get_matching_units($start_date, $temp_end_date, ['bee_hourly_available', 'bee_hourly_not_available'], $type_id, 'availability_hourly');

    $units_ids = [];
    foreach ($node->get('field_availability_hourly') as $unit) {
      if ($unit->entity) {
        $units_ids[] = $unit->entity->id();
      }
    }

    $units = array_intersect($units_ids, $available_units);

    if ($units) {
      if ($new_state == 'available') {
        $state = bat_event_load_state_by_machine_name('bee_hourly_available');
      }
      else {
        $state = bat_event_load_state_by_machine_name('bee_hourly_not_available');
      }

      foreach ($units as $unit) {
        $event = bat_event_create(['type' => 'availability_hourly']);
        $event_dates = [
          'value' => $start_date->format('Y-m-d\TH:i:00'),
          'end_value' => $end_date->format('Y-m-d\TH:i:00'),
        ];
        $event->set('event_dates', $event_dates);
        $event->set('event_state_reference', $state->id());
        $event->set('event_bat_unit_reference', $unit);
        $event->save();
      }
    }

    foreach ($booked_units as $unit) {
      $bat_unit = bat_unit_load($unit);
      $this->messenger()->addWarning(t('Could not create event from @start to @end for unit @label with ID: @unit', [
        '@unit' => $unit,
        '@label' => $bat_unit->label(),
        '@start' => $start_date->format('Y-m-d'),
        '@end' => $end_date->format('Y-m-d'),
      ]));
    }
  }

}
