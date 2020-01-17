<?php

namespace Drupal\bee_webform\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * BEE reservation form
 *
 * @FormElement("webform_bee_reservation_form")
 */
class WebformBeeReservationForm extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];

    if (isset($element['#content_types'])) {
      $bookable_type = FALSE;
      $content_type_options = [];

      foreach (array_filter($element['#content_types']) as $node_type) {
        $node_type = NodeType::load($node_type);
        $content_type_options[$node_type->id()] = $node_type->label();

        $bee_settings = \Drupal::config('node.type.' . $node_type->id())->get('bee');

        if (isset($bee_settings['bookable_type'])) {
          $bookable_type = $bee_settings['bookable_type'];
        }
      }

      if ($content_type_options) {
        $elements['start_date'] = [
          '#type' => ($bookable_type == 'daily') ? 'date' : 'datetime',
          '#title' => t('Start date'),
          '#ajax' => [
            'callback' => [get_called_class(), 'ajaxCallback'],
            'wrapper' => 'bee-node-wrapper',
          ],
        ];
        $elements['end_date'] = [
          '#type' => ($bookable_type == 'daily') ? 'date' : 'datetime',
          '#title' => t('End date'),
          '#ajax' => [
            'callback' => [get_called_class(), 'ajaxCallback'],
            'wrapper' => 'bee-node-wrapper',
          ],
        ];

        if ($element['#collect_capacity']) {
          $elements['capacity'] = [
            '#type' => 'number',
            '#title' => t('Capacity'),
            '#ajax' => [
              'callback' => [get_called_class(), 'ajaxCallback'],
              'wrapper' => 'bee-node-wrapper',
              'event' => 'change',
            ],
          ];
        }

        $elements['repeat'] = [
          '#type' => 'checkbox',
          '#title' => t('This booking repeats'),
          '#prefix' => '<div class="form-row">',
        ];

        $elements['repeat_frequency'] = [
          '#type' => 'select',
          '#title' => t('Repeat frequency'),
          '#options' => [
            'daily' => t('Daily'),
            'weekly' => t('Weekly'),
            'monthly' => t('Monthly'),
          ],
          '#states' => [
            'visible' => [
              ':input[name="bee[repeat]"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $elements['repeat_until'] = [
          '#type' => 'date',
          '#title' => t('Repeat until'),
          '#states' => [
            'visible' => [
              ':input[name="bee[repeat]"]' => ['checked' => TRUE],
            ],
          ],
          '#suffix' => '</div>',
        ];

        $elements['node'] = [
          '#type' => 'select',
          '#title' => t('Node'),
          '#options' => [],
          '#prefix' => '<div id="bee-node-wrapper">',
          '#suffix' => '</div>',
          '#disabled' => TRUE,
          '#empty_option' => '',
        ];

        $elements['#attached']['library'][] = 'bee/bee_form';
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    $capacity = (isset($value['capacity'])) ? $value['capacity'] : 1;

    if ($value['node'] && $value['start_date'] && $value['end_date']) {
      $units = bee_webform_get_available_units($value);
      $available_units = count($units);

      if ($available_units < $capacity) {
        $form_state->setError($element, t('Unfortunately, not enough units of this type are available'));
      }
    }
  }

  public static function ajaxCallback($form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $key = $element['#parents'][0];

    $values = $form_state->getValue($key);

    if (!empty($values['start_date']['date']) && !empty($values['start_date']['time']) &&
        !empty($values['end_date']['date']) && !empty($values['end_date']['time']) &&
        (!isset($values['capacity']) || (isset($values['capacity']) && !empty($values['capacity'])))) {
      $content_types = array_filter($form['elements'][$key]['#content_types']);

      $capacity = (isset($values['capacity'])) ? $values['capacity'] : 1;

      $node_options = [];

      $node_storage = \Drupal::entityTypeManager()->getStorage('node');

      $query = \Drupal::entityQuery('node')
        ->condition('type', array_keys($content_types), 'IN');

      $nids = $query->execute();
      foreach ($node_storage->loadMultiple($nids) as $node) {
        $bee_settings = \Drupal::config('node.type.' . $node->bundle())->get('bee');

        $drupal_units = [];
        foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
          $drupal_units[$unit->entity->id()] = $unit->entity;
        }

        if ($bee_settings['bookable_type'] == 'daily') {
          $start_date = new \DateTime($values['start_date']);
          $end_date = new \DateTime($values['end_date']);
          $end_date->sub(new \DateInterval('PT1M'));

          $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bee_daily_available'], $bee_settings['type_id'], 'availability_daily', FALSE, $drupal_units);
        }
        else {
          $start_date = new \DateTime($values['start_date']['date'] . ' ' . $values['start_date']['time']);
          $end_date = new \DateTime($values['start_date']['date'] . ' ' . $values['end_date']['time']);
          $end_date->sub(new \DateInterval('PT1M'));

          $available_units_ids = bat_event_get_matching_units($start_date, $end_date, ['bee_hourly_available'], $bee_settings['type_id'], 'availability_hourly', FALSE, $drupal_units);
        }

        if (count($available_units_ids) >= $capacity) {
          $node_options[$node->id()] = $node->label();
        }
      }

      $form['elements'][$key]['#webform_composite_elements']['node']['#options'] = $node_options;

      if (empty($node_options)) {
        \Drupal::messenger()->addError('There are no units available for the selected date/time(s)');

        $form['elements'][$key]['#webform_composite_elements']['node']['#attributes'] = [
          'disabled' => TRUE,
        ];
      }
    }
    else {
      $form['elements'][$key]['#webform_composite_elements']['node']['#options'] = [];

      $form['elements'][$key]['#webform_composite_elements']['node']['#attributes'] = [
        'disabled' => TRUE,
      ];
    }

    $form['elements'][$key]['#webform_composite_elements']['node']['#prefix'] = '<div id="bee-node-wrapper">';
    $form['elements'][$key]['#webform_composite_elements']['node']['#suffix'] = '</div>';

    $form['elements'][$key]['#webform_composite_elements']['node']['#name'] = $key . '[node]';

    return $form['elements'][$key]['#webform_composite_elements']['node'];
  }

}
