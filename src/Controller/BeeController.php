<?php

namespace Drupal\bee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\node\NodeInterface;

class BeeController extends ControllerBase implements ContainerInjectionInterface {

  /**
   *
   */
  public function __construct() {
  }

  /**
   *
   */
  public function availability(NodeInterface $node) {
    $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

    $unit_type = $bee_settings['type_id'];
    $bat_unit = $node->get('field_availability_' . $bee_settings['bookable_type'])->entity;

    if ($bee_settings['bookable_type'] == 'daily') {
      $event_type = 'availability_daily';
      $event_granularity = 'bat_daily';

      $fc_user_settings = [
        'batCalendar' => [
          [
            'unitType' => $unit_type,
            'unitIds' => $bat_unit->id(),
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'viewsTimelineThirtyDaySlotDuration' => ['days' => 1],
          ],
        ],
      ];
    }
    else {
      $event_type = 'availability_hourly';
      $event_granularity = 'bat_hourly';

       $fc_user_settings = [
        'batCalendar' => [
          [
            'unitType' => $unit_type,
            'unitIds' => $bat_unit->id(),
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'views' => 'timelineTenDay, timelineMonth',
            'defaultView' => 'timelineTenDay',
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
