<?php

namespace Drupal\bee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\office_hours\OfficeHoursDateHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class BeeController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new BeeController object.
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
   * Availability calendar page.
   */
  public function availability(NodeInterface $node) {
    $bee_settings = $this->configFactory->get('node.type.' . $node->bundle())->get('bee');

    $unit_type = $bee_settings['type_id'];

    $bat_unit_ids = [];
    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        $bat_unit_ids[] = $unit->entity->id();
      }
    }

    if ($bee_settings['bookable_type'] == 'daily') {
      $event_type = 'availability_daily';
      $event_granularity = 'bat_daily';

      $fc_user_settings = [
        'batCalendar' => [
          [
            'unitType' => $unit_type,
            'unitIds' => implode(',', $bat_unit_ids),
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'viewsTimelineThirtyDaySlotDuration' => ['days' => 1],
          ],
        ],
      ];
    }
    else {
      $minTime = FALSE;
      $maxTime = FALSE;
      $hidden_days = [];

      if ($node->get('field_use_open_hours')->value) {
        $business_hours = [];
        $hidden_days = range(0, 6, 1);

        foreach ($node->get('field_open_hours')->getValue() as $value) {
          $day = $value['day'];
          $starthours = OfficeHoursDateHelper::format($value['starthours'], 'H:i:s');
          $endhours = OfficeHoursDateHelper::format($value['endhours'], 'H:i:s');

          $business_hours[] = [
            'dow' => [$day],
            'start' => $starthours,
            'end' => $endhours,
          ];

          if ($minTime == FALSE || strtotime($starthours) < strtotime($minTime)) {
            $minTime = $starthours;
          }
          if ($maxTime == FALSE || strtotime($endhours) > strtotime($maxTime)) {
            $maxTime = $endhours;
          }

          unset($hidden_days[$day]);
        }
      }
      else {
        $business_hours = [
          'start' => '00:00',
          'end' => '24:00',
          'dow' => [0, 1, 2, 3, 4, 5, 6],
        ];
      }

      $event_type = 'availability_hourly';
      $event_granularity = 'bat_hourly';

      $fc_user_settings = [
        'batCalendar' => [
          [
            'unitType' => $unit_type,
            'unitIds' => implode(',', $bat_unit_ids),
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'views' => 'timelineDay, timelineTenDay, timelineMonth',
            'defaultView' => 'timelineDay',
            'businessHours' => $business_hours,
            'selectConstraint' => 'businessHours',
            'minTime' => ($minTime) ? $minTime : '00:00:00',
            'maxTime' => ($maxTime) ? $maxTime : '24:00:00',
            'hiddenDays' => array_keys($hidden_days),
          ],
        ],
      ];
    }

    $calendar_settings['user_settings'] = $fc_user_settings;
    $calendar_settings['calendar_id'] = 'fullcalendar-scheduler';

    $render_array = [
      'calendar' => [
        '#theme' => 'bat_fullcalendar',
        '#calendar_settings' => $calendar_settings,
        '#attached' => ['library' => ['bat_event_ui/bat_event_ui', 'bat_fullcalendar/bat-fullcalendar-scheduler']],
      ],
    ];

    return [
      'form' => $this->formBuilder()->getForm('Drupal\bee\Form\UpdateAvailabilityForm', $node),
      'calendar' => $render_array,
    ];
  }

  /**
   * The _title_callback for the page that renders the availability.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *
   * @return string
   *   The page title.
   */
  public function availabilityTitle(EntityInterface $node) {
    return $this->t('Availability for %label', ['%label' => $node->label()]);
  }

  /**
   * The _title_callback for the page that renders the add reservation form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *
   * @return string
   *   The page title.
   */
  public function addReservationTitle(EntityInterface $node) {
    return $this->t('Create a reservation for %label', ['%label' => $node->label()]);
  }

}
