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
        if (count($content_type_options) > 1) {
          $elements['content_type'] = [
            '#type' => 'radios',
            '#title' => t('Content type'),
            '#required' => TRUE,
            '#options' => $content_type_options,
          ];
        }
        else {
          $elements['content_type'] = [
            '#type' => 'hidden',
            '#title' => t('Content type'),
            '#value' => key($content_type_options),
          ];
        }

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

    if ($value['content_type'] && $value['start_date'] && $value['end_date'] && $value['capacity']) {
      $available_units = 0;
      $units = bee_webform_get_available_units($value);
      $available_units += count($units);

      if ($available_units < $value['capacity']) {
        $form_state->setError($element, t('Unfortunately, not enough units of this type are available'));
      }
    }
  }

}
