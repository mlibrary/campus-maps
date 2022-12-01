<?php

namespace Drupal\disable_field;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ConfigFormAlter.
 *
 * @package Drupal\disable_field
 */
class ConfigFormBuilder {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The role storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $roleStorage;

  /**
   * ConfigFormAlter constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entityTypeManager) {
    $this->currentUser = $current_user;
    $this->roleStorage = $entityTypeManager->getStorage('user_role');
  }

  /**
   * Add the disable field config form to the given form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   */
  public function addDisableFieldConfigFormToEntityForm(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$this->currentUser->hasPermission('administer disable field settings')) {
      return;
    }

    $role_options = [];
    $roles = $this->roleStorage->loadMultiple();
    foreach ($roles as $role) {
      $role_options[$role->id()] = $role->label();
    }

    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $form_object->getEntity();
    $settings = $field_config->getThirdPartySettings('disable_field');

    // Prepare group with fields for settings.
    $form['disable_field'] = [
      '#type' => 'details',
      '#title' => $this->t('Disable Field Settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#weight' => 20,
      'add' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Disable this field on add content form?'),
      ],
      'edit' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Disable this field on edit content form?'),
      ],
    ];
    $form['disable_field']['add']['disable'] = [
      '#type' => 'select',
      '#options' => [
        'none' => $this->t('Enable for all users'),
        'all' => $this->t('Disable for all users'),
        'roles' => $this->t('Disable for certain roles'),
        'roles_enable' => $this->t('Enable for certain roles'),
      ],
      '#default_value' => !empty($settings['add_disable']) ? $settings['add_disable'] : 'none',
      '#required' => TRUE,
    ];
    $form['disable_field']['add']['roles'] = [
      '#type' => 'select',
      '#options' => $role_options,
      '#title' => $this->t('Enable field on the add content form for next roles:'),
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="disable_field[add][disable]"]' => [
            ['value' => 'roles'],
            ['value' => 'roles_enable'],
          ],
        ],
        'required' => [
          ':input[name="disable_field[add][disable]"]' => [
            ['value' => 'roles'],
            ['value' => 'roles_enable'],
          ],
        ],
      ],
      '#default_value' => !empty($settings['add_roles']) ? $settings['add_roles'] : [],
    ];
    $form['disable_field']['edit']['disable'] = [
      '#type' => 'select',
      '#options' => [
        'none' => $this->t('Enable for all users'),
        'all' => $this->t('Disable for all users'),
        'roles' => $this->t('Disable for certain roles'),
        'roles_enable' => $this->t('Enable for certain roles'),
      ],
      '#default_value' => !empty($settings['edit_disable']) ? $settings['edit_disable'] : 'none',
      '#required' => TRUE,
    ];
    $form['disable_field']['edit']['roles'] = [
      '#type' => 'select',
      '#options' => $role_options,
      '#title' => $this->t('Disable field on the edit content form for next roles:'),
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="disable_field[edit][disable]"]' => [
            ['value' => 'roles'],
            ['value' => 'roles_enable'],
          ],
        ],
        'required' => [
          ':input[name="disable_field[edit][disable]"]' => [
            ['value' => 'roles'],
            ['value' => 'roles_enable'],
          ],
        ],
      ],
      '#default_value' => !empty($settings['edit_roles']) ? $settings['edit_roles'] : [],
    ];

    $form['#validate'][] = [$this, 'validateDisableFieldConfigForm'];
    $form['#entity_builders'][] = [
      $this,
      'assignDisableFieldThirdPartySettingsToEntity',
    ];
  }

  /**
   * Validation rules for the disable field config form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateDisableFieldConfigForm(array &$form, FormStateInterface $form_state): void {
    // Check if the add roles field contains values when required.
    $add_roles = $form_state->getValue(['disable_field', 'add', 'roles']);
    $add_option = $form_state->getValue(['disable_field', 'add', 'disable']);
    if (empty($add_roles) && in_array($add_option, ['roles', 'roles_enable'])) {
      $form_state->setErrorByName('disable_field][add][roles', $this->t('Please, choose at least one role.'));
    }

    // Check if the edit roles field contains values when required.
    $edit_roles = $form_state->getValue(['disable_field', 'edit', 'roles']);
    $edit_option = $form_state->getValue(['disable_field', 'edit', 'disable']);
    if (empty($edit_roles) && in_array($edit_option, ['roles', 'roles_enable'])) {
      $form_state->setErrorByName('disable_field][edit][roles', $this->t('Please, choose at least one role.'));
    }
  }

  /**
   * Assign the disable field settings to the given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldConfigInterface $field_config
   *   The field config entity.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function assignDisableFieldThirdPartySettingsToEntity(string $entity_type, FieldConfigInterface $field_config, array &$form, FormStateInterface $form_state): void {
    $add_option = $form_state->getValue(['disable_field', 'add', 'disable']);
    $add_roles = $form_state->getValue(['disable_field', 'add', 'roles']);
    $field_config->setThirdPartySetting('disable_field', 'add_disable', $add_option);

    $field_config->unsetThirdPartySetting('disable_field', 'add_roles');
    if (is_array($add_roles) && in_array($add_option, ['roles', 'roles_enable'])) {
      $field_config->setThirdPartySetting('disable_field', 'add_roles', array_keys($add_roles));
    }

    $edit_option = $form_state->getValue(['disable_field', 'edit', 'disable']);
    $edit_roles = $form_state->getValue(['disable_field', 'edit', 'roles']);
    $field_config->setThirdPartySetting('disable_field', 'edit_disable', $edit_option);

    $field_config->unsetThirdPartySetting('disable_field', 'edit_roles');
    if (is_array($edit_roles) && in_array($edit_option, [
      'roles',
      'roles_enable',
    ])) {
      $field_config->setThirdPartySetting('disable_field', 'edit_roles', array_keys($edit_roles));
    }
  }

}
