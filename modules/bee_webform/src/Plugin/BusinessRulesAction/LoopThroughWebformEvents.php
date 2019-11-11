<?php

namespace Drupal\bee_webform\Plugin\BusinessRulesAction;

use Drupal\business_rules\ActionInterface;
use Drupal\business_rules\BusinessRulesItemObject;
use Drupal\business_rules\Entity\Action;
use Drupal\business_rules\Entity\Condition;
use Drupal\business_rules\Events\BusinessRulesEvent;
use Drupal\business_rules\ItemInterface;
use Drupal\business_rules\Plugin\BusinessRulesActionPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class LoopThroughWebformEvents.
 *
 * @package Drupal\bee_webform\Plugin\BusinessRulesAction
 *
 * @BusinessRulesAction(
 *   id = "loop_through_webform_events",
 *   label = @Translation("Loop through webform events"),
 *   group = @Translation("BEE"),
 *   description = @Translation("Loop through webform events and execute actions and/or conditions."),
 *   isContextDependent = FALSE,
 *   hasTargetEntity = FALSE,
 *   hasTargetBundle = FALSE,
 *   hasTargetField = FALSE,
 * )
 */
class LoopThroughWebformEvents extends BusinessRulesActionPlugin {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * We don't want to use the same wait two times for an item.
   *
   * @var array
   */
  private $usedWeight = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration = [], $plugin_id = 'loop_through_view_result', $plugin_definition = []) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $this->util->container->get('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array &$form, FormStateInterface $form_state, ItemInterface $item) {
    $settings = [];

    if (!$item->isNew()) {
      // The items to be executed.
      $settings['items'] = [
        '#type'  => 'details',
        '#title' => t('Items to execute during the loop'),
        '#open'  => TRUE,
      ];

      $settings['items'][] = $this->formItems($form, $form_state, $item);
    }

    return $settings;
  }

  /**
   * Provide the form fields for add Business Rule's Items.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\business_rules\ItemInterface $action
   *   The current action.
   *
   * @return array
   *   The render array.
   */
  public function formItems(array $form, FormStateInterface $form_state, ItemInterface $action) {
    $user_input = $form_state->getUserInput();

    $label        = t('Item');
    $label_plural = t('Items');
    $raw_items    = $action->getSettings('items');

    $items = [];
    if (is_array($raw_items)) {
      foreach ($raw_items as $key => $item) {
        $itemObj     = new BusinessRulesItemObject($key, $item['type'], $item['weight']);
        $items[$key] = $itemObj;
      }

      uasort($items, function ($a, $b) {
        return $a->getWeight() < $b->getWeight() ? -1 : 1;
      });
    }

    $header = [
      'item_type'   => t('Type'),
      'label'       => $label,
      'weight'      => t('Weight'),
      'id'          => t('Machine name'),
      'subtype'     => t('Subtype'),
      'description' => t('Description'),
      'operations'  => t('Operations'),
      'type'        => [
        'data'  => '',
        'width' => '0px',
      ],
    ];

    $table['items'] = [
      '#type'       => 'table',
      '#header'     => $header,
      '#attributes' => ['id' => 'business_rules-items'],
      '#empty'      => t('There are currently no @label in this item. Add one by selecting an option below.', ['@label' => $label_plural]),
      '#tabledrag'  => [
        [
          'action'       => 'order',
          'relationship' => 'sibling',
          'group'        => 'items-order-weight',
        ],
      ],
    ];

    if (is_array($items)) {
      foreach ($items as $value) {

        if ($value->getType() == 'condition') {
          $item = Condition::load($value->getId());
        }
        elseif ($value->getType() == 'action') {
          $item = Action::load($value->getId());
        }

        $item_weight       = !empty($item) ? $value->getWeight() : '';
        $route_remove_item = 'business_rules.loop_through_view_result.items.remove';

        if (!empty($item)) {

          $key         = $item->id();
          $listBuilder = $this->entityTypeManager->getListBuilder($item->getEntityTypeId());
          $operations  = $listBuilder->buildOperations($item);

          $operations['#links']['remove'] = [
            'title'  => t('Remove'),
            'url'    => Url::fromRoute($route_remove_item, [
              'action_id' => $action->id(),
              'item_type' => $value->getType(),
              'item_id'   => $item->id(),
              'method'    => 'nojs',
            ],
              [
                'attributes' => [
                  'class' => ['use-ajax'],
                ],
              ]
            ),
            'weight' => 1,
          ];
          uasort($operations['#links'], function ($a, $b) {
            return $a['weight'] < $b['weight'] ? -1 : 1;
          });
          foreach ($operations['#links'] as $i => $link) {
            $uri = $this->util->getCurrentUri()->toString();
            $operations['#links'][$i]['url']->setRouteParameter('destination', $uri);
          }

          $item_weight = $this->generateItemWeight('item', $item_weight);

          $weight = [
            '#type'          => 'weight',
            '#title'         => t('Weight for item'),
            '#title_display' => 'invisible',
            '#delta'         => 100,
            '#default_value' => $item_weight,
            '#attributes'    => [
              'class' => ['items-order-weight'],
            ],
          ];

          $table['items'][$key] = [
            '#attributes' => [
              'class' => 'draggable',
              'id'    => $item->id(),
            ],
            '#weight'     => isset($user_input['effects']) ? $user_input['effects'][$key]['weight'] : NULL,
            'item_type'   => ['#markup' => $item->getBusinessRuleItemTranslatedType()],
            'item'        => [
              '#tree' => FALSE,
              'label' => ['#markup' => $item->label()],
            ],
            'weight'      => $weight,
            'id'          => ['#markup' => $item->id()],
            'subtype'     => ['#markup' => $item->getTypeLabel()],
            'description' => ['#markup' => $item->getDescription()],
            'operations'  => $operations,
            'type'        => [
              '#type'  => 'value',
              '#value' => $value->getType(),
            ],
          ];

        }
      }
    }

    $add_condition = Link::createFromRoute(t('Add Condition'), 'business_rules.loop_through_view_result.items.table', [
      'action_id' => $action->id(),
      'item_type' => 'condition',
      'method'    => 'nojs',
    ], ['attributes' => ['class' => ['use-ajax']]]);
    $add_action    = Link::createFromRoute(t('Add Action'), 'business_rules.loop_through_view_result.items.table', [
      'action_id' => $action->id(),
      'item_type' => 'action',
      'method'    => 'nojs',
    ], ['attributes' => ['class' => ['use-ajax']]]);

    $table['add'][] = [
      'data' => [
        'add' => [
          '#type'   => 'markup',
          '#markup' => $add_condition->toString() . ' | ' . $add_action->toString(),
          '#prefix' => '<div id="business_rule-add_buttons">',
          '#suffix' => '</div>',
        ],
      ],
    ];

    return $table;
  }

  /**
   * Generate the item weight.
   *
   * @param string $settings_type
   *   The settings type: success|fail.
   * @param int $weight
   *   The weight.
   *
   * @return int
   *   The generated weight
   */
  private function generateItemWeight($settings_type, $weight) {

    if (!isset($this->usedWeight[$settings_type])) {
      $this->usedWeight[$settings_type][] = $weight;

      return $weight;
    }

    if (!in_array($weight, $this->usedWeight[$settings_type])) {
      $this->usedWeight[$settings_type][] = $weight;

      return $weight;
    }
    else {
      $weight++;

      return $this->generateItemWeight($settings_type, $weight);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state) {
    parent::buildForm($form, $form_state);

    // We don't need variables for this action.
    unset($form['variables']);
  }

  /**
   * {@inheritdoc}
   */
  public function processSettings(array $settings, ItemInterface $item) {
    if (empty($settings['items'])) {
      $settings['items'] = [];
    }
    else {
      foreach ($settings['items'] as $key => $item) {
        $settings['items'][$key]['id'] = $key;
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ActionInterface $action, BusinessRulesEvent $event) {
    /** @var \Drupal\business_rules\VariablesSet $event_variables */
    $event_variables = $event->getArgument('variables');
    $action_items = $action->getSettings('items');

    $webform_submission = $event->getSubject();
    $webform = $webform_submission->getWebform();

    $webform_bee_element = _bee_webform_get_webform_bee_reservation_element($webform);
    $bee_element_id = $webform_bee_element['#id'];

    $data = $webform_submission->getData();
    $bat_events = explode(',', $data[$bee_element_id]['events']);

    // Execute action items.
    if (!empty($action_items)) {
      foreach ($bat_events as $bat_event_id) {
        $bat_event = bat_event_load($bat_event_id);

        // Load the bat event and set as event argument.
        $event->setArgument('entity', $bat_event);
        $event->setArgument('entity_type_id', 'bat_event');

        $items = BusinessRulesItemObject::itemsArrayToItemsObject($action_items);
        $this->processor->processItems($items, $event, $action->id());

        $bat_event->save();
      }
    }
  }

}
