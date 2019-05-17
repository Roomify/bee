<?php

namespace Drupal\bee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Drupal\office_hours\OfficeHoursDateHelper;

class BeeController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new BeeController instance.
   */
  public function __construct() {
  }

  /**
   * Availability calendar page.
   */
  public function availability(NodeInterface $node) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $unit_type = $bee_settings['type_id'];

    $bat_unit_ids = [];
    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      $bat_unit_ids[] = $unit->entity->id();
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
          if ($maxTime == FALSE || strtotime($endhours) < strtotime($maxTime)) {
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
            'views' => 'timelineTenDay, timelineMonth',
            'defaultView' => 'timelineTenDay',
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
      'form' => \Drupal::formBuilder()->getForm('Drupal\bee\Form\UpdateAvailabilityForm', $node),
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
}
