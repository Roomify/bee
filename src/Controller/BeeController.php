<?php

namespace Drupal\bee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
      if ($node->get('field_use_open_hours')) {
        $business_hours = [];

        foreach ($node->get('field_open_hours')->getValue() as $value) {
          $business_hours[] = [
            'dow' => [$value['day']],
            'start' => OfficeHoursDateHelper::format($value['starthours'], 'H:i'),
            'end' => OfficeHoursDateHelper::format($value['endhours'], 'H:i'),
          ];
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
      '#markup' => render($render_array),
    ];
  }

}
