<?php

namespace Drupal\bee_webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;

/**
 * BEE reservation form
 *
 * @WebformElement(
 *   id = "webform_bee_reservation_form",
 *   label = @Translation("BEE reservation form"),
 *   description = @Translation("BEE reservation form."),
 *   category = @Translation("Advanced elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformBeeReservationForm extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
      'content_types' => [],
      'only_check_availability' => FALSE,
      'collect_capacity' => FALSE,
    ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $node_types = NodeType::loadMultiple();

    $options = [];

    foreach ($node_types as $node_type) {
      $bee_settings = $this->configFactory->get('node.type.' . $node_type->id())->get('bee');

      if (!empty($bee_settings['bookable'])) {
        $options[$node_type->id()] = $node_type->label();
      }
    }

    $form['composite']['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content type(s)'),
      '#options' => $options,
      '#element_validate' => [[get_class($this), 'validateContentTypes']],
    ];

    $form['composite']['collect_capacity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collect Capacity?'),
      '#description' => $this->t('If this is checked, BEE will attempt to reserve the number of units entered.'),
    ];

    $form['composite']['only_check_availability'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only check availability'),
    ];

    return $form;
  }

  /**
   * Webform element validation handler.
   */
  public static function validateContentTypes(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];

    $bookable_type = FALSE;

    if (count($value) > 1) {
      foreach ($value as $node_type) {
        $bee_settings = \Drupal::configFactory()->get('node.type.' . $node_type)->get('bee');

        if (isset($bee_settings['bookable_type'])) {
          if ($bookable_type) {
            if ($bookable_type != $bee_settings['bookable_type']) {
              $form_state->setError($element, t('Daily and Hourly content types cannot both be selected.'));
            }
          }
          else {
            $bookable_type = $bee_settings['bookable_type'];
          }
        }
      }
    }
  }

}
