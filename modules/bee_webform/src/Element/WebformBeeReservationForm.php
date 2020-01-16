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
        ];
        $elements['end_date'] = [
          '#type' => ($bookable_type == 'daily') ? 'date' : 'datetime',
          '#title' => t('End date'),
        ];

        if ($element['#collect_capacity']) {
          $elements['capacity'] = [
            '#type' => 'number',
            '#title' => t('Capacity'),
          ];
        }

        $node_options = [];

        $node_storage = \Drupal::entityTypeManager()->getStorage('node');

        $query = \Drupal::entityQuery('node')
          ->condition('type', array_keys($content_type_options), 'IN');

        $nids = $query->execute();
        foreach ($node_storage->loadMultiple($nids) as $node) {
          $node_options[$node->id()] = $node->label();
        }

        $states = [
          [':input[name="' . $element['#webform_key'] . '[start_date][date]"]' => ['value' => '']],
          'or',
          [':input[name="' . $element['#webform_key'] . '[end_date][date]"]' => ['value' => '']],
        ];

        if ($element['#collect_capacity']) {
          $states[] = 'or';
          $states[] = [':input[name="' . $element['#webform_key'] . '[capacity]"]' => ['value' => '']];
        }
        if ($bookable_type == 'hourly') {
          $states[] = 'or';
          $states[] = [':input[name="' . $element['#webform_key'] . '[start_date][time]"]' => ['value' => '']];
          $states[] = 'or';
          $states[] = [':input[name="' . $element['#webform_key'] . '[end_date][time]"]' => ['value' => '']];
        }

        $elements['node'] = [
          '#type' => 'select',
          '#title' => t('Node'),
          '#options' => $node_options,
          '#states' => [
            'disabled' => $states,
          ],
        ];

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

}
