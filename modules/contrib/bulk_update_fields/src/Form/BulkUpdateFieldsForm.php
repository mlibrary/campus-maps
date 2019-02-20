<?php

namespace Drupal\bulk_update_fields\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;

/**
 * BulkUpdateFieldsForm.
 */
class BulkUpdateFieldsForm extends FormBase implements FormInterface {

  /**
   * Set a var to make stepthrough form.
   *
   * @var step
   */
  protected $step = 1;
  /**
   * Keep track of user input.
   *
   * @var userInput
   */
  protected $userInput = [];

  /**
   * Tempstorage.
   *
   * @var tempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Session.
   *
   * @var sessionManager
   */
  private $sessionManager;

  /**
   * User.
   *
   * @var currentUser
   */
  private $currentUser;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a \Drupal\bulk_update_fields\Form\BulkUpdateFieldsForm.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   Temp storage.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   Session.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   User.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   Route.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user, RouteBuilderInterface $route_builder) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_update_fields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function updateFields() {
    $entities = $this->userInput['entities'];
    $fields = $this->userInput['fields'];
    $batch = [
      'title' => $this->t('Updating Fields...'),
      'operations' => [
        [
          '\Drupal\bulk_update_fields\BulkUpdateFields::updateFields',
          [$entities, $fields],
        ],
      ],
      'finished' => '\Drupal\bulk_update_fields\BulkUpdateFields::bulkUpdateFieldsFinishedCallback',
    ];
    batch_set($batch);
    return 'All fields were updated successfully';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    switch ($this->step) {
      case 1:
        $this->userInput['fields'] = array_filter($form_state->getValues()['table']);
        $form_state->setRebuild();
        break;

      case 2:
        $this->userInput['fields'] = array_merge($this->userInput['fields'], $form_state->getValues()['default_value_input']);
        $form_state->setRebuild();
        break;

      case 3:
        if (method_exists($this, 'updateFields')) {
          $return_verify = $this->updateFields();
        }
        drupal_set_message($return_verify);
        $this->routeBuilder->rebuild();
        break;
    }
    $this->step++;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (isset($this->form)) {
      $form = $this->form;
    }
    $form['#title'] = $this->t('Bulk Update Fields');
    $submit_label = 'Next';

    switch ($this->step) {
      case 1:
        // Retrieve IDs from the temporary storage.
        $this->userInput['entities'] = $this->tempStoreFactory
          ->get('bulk_update_fields_ids')
          ->get($this->currentUser->id());
        $options = [];
        // Exclude some base fields.
        // TODO add date fields and revision log.
        $excluded_base_fields = [
          'nid',
          'uuid',
          'vid',
          'type',
          'revision_uid',
          'title',
          'path',
          'menu_link',
          'status',
          'uid',
          'default_langcode',
          'revision_timestamp',
          'revision_log',
          'created',
          'changed'
        ];
        foreach ($this->userInput['entities'] as $entity) {
          $this->entity = $entity;
          $fields = $entity->getFieldDefinitions();
          foreach ($fields as $field) {
            if (!in_array($field->getName(), $excluded_base_fields) && !isset($options[$field->getName()])) {
              $options[$field->getName()]['field_name'] = $field->getName();
            }
          }
        }
        $header = [
          'field_name' => $this->t('Field Name'),
        ];
        $form['#title'] .= ' - ' . $this->t('Select Fields to Alter');
        $form['table'] = [
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $options,
          '#empty' => $this->t('No fields found'),
        ];
        break;

      case 2:
        foreach ($this->userInput['entities'] as $entity) {
          $this->entity = $entity;
          foreach ($this->userInput['fields'] as $field_name) {
            $temp_form_element = [];
            $temp_form_state = new FormState();
            if ($field = $entity->getFieldDefinition($field_name)) {
              // TODO Dates fields are incorrect due to TODOs below.
              if ($field->getType() == 'datetime') {
                drupal_set_message($this->t('Cannot update field @field_name. Date field types are not yet updatable.',
                  [
                    '@field_name' => $field_name,
                  ]), 'error');
                continue;
              }
              // TODO
              // I cannot figure out how to get a form element for only a field.
              // Maybe someone else can.
              // TODO Doing it this way does not allow for feild labels on
              // textarea widgets.
              $form[$field_name] = $entity->get($field_name)->defaultValuesForm($temp_form_element, $temp_form_state);
            }
          }
        }
        $form['#title'] .= ' - ' . $this->t('Enter New Values in Appropriate Fields');
        break;

      case 3:
        $form['#title'] .= ' - ' . $this->t('Are you sure you want to alter @count_fields fields on @count_entities entities?',
            [
              '@count_fields' => count($this->userInput['fields']),
              '@count_entities' => count($this->userInput['entities']),
            ]
        );
        $submit_label = 'Update Fields';

        break;
    }
    drupal_set_message($this->t('This module is experiemental. PLEASE do not use on production databases without prior testing and a complete database dump.'), 'warning');
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $submit_label,
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // TODO.
  }

}
