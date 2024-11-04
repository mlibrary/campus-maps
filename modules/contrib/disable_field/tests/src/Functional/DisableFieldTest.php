<?php

namespace Drupal\Tests\disable_field\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\disable_field\Traits\DisableFieldTestTrait;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Disable field tests.
 *
 * @group disable_field.
 */
class DisableFieldTest extends BrowserTestBase {

  use DisableFieldTestTrait;
  use FieldUiTestTrait;
  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'disable_field',
    'block',
    'field_ui',
    'node',
    'paragraphs',
    'base_field_override_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Test role 1.
   *
   * @var string
   */
  protected $role1;

  /**
   * Test role 2.
   *
   * @var string
   */
  protected $role2;

  /**
   * Test user 1 with role 1.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user1;

  /**
   * Test user 2 with role 2.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->drupalCreateContentType(['type' => 'test']);

    $this->adminUser = $this->createUser([], NULL, TRUE);
    $this->role1 = $this->drupalCreateRole([
      'access content',
      'edit any test content',
      'create test content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'administer disable field settings',
    ]);
    $this->role2 = $this->drupalCreateRole([
      'access content',
      'edit any test content',
      'create test content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
    ]);

    $this->user1 = $this->createUser([], NULL, FALSE, ['roles' => [$this->role1]]);
    $this->user2 = $this->createUser([], NULL, FALSE, ['roles' => [$this->role2]]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Enable the field for all roles on the content add form.
   */
  public function testDisableFieldOnAddFormEnableForAllRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string');

    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');
  }

  /**
   * Disable the field for all roles on the content add form.
   */
  public function testDisableFieldOnAddFormDisableForAllRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], ['disable_field[add][disable]' => 'all']);

    // Make sure the field is disabled for all roles. Even the admin user.
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');
  }

  /**
   * Disable the field for certain roles on the content add form.
   */
  public function testDisableFieldOnAddFormDisableForCertainRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], [
      'disable_field[add][disable]' => 'roles',
      'disable_field[add][roles][]' => [$this->role1],
    ]);

    // Make sure the field is disabled for all roles. Even the admin user.
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');
  }

  /**
   * Enable the field for certain roles on the content edit form.
   */
  public function testDisableFieldOnAddFormEnableForCertainRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], [
      'disable_field[add][disable]' => 'roles_enable',
      'disable_field[add][roles][]' => [$this->role1],
    ]);

    // Make sure the field is disabled for all roles. Even the admin user.
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');
  }

  /**
   * Enable the field for all roles on the content edit form.
   */
  public function testDisableFieldOnEditFormEnableForAllRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string');

    $node = $this->drupalCreateNode(['type' => 'test']);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');
  }

  /**
   * Disable the field for all roles on the content edit form.
   */
  public function testDisableFieldOnEditFormDisableForAllRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], ['disable_field[edit][disable]' => 'all']);

    // Make sure the field is not disabled on the field config edit page.
    $this->drupalGet('/admin/structure/types/manage/test/fields/node.test.field_test');
    $this->checkIfFieldIsNotDisabledByFieldName('default-value-input-field-test');

    $node = $this->drupalCreateNode(['type' => 'test']);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');
  }

  /**
   * Disable the field for certain roles on the content edit form.
   */
  public function testDisableFieldOnEditFormDisableForCertainRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], [
      'disable_field[edit][disable]' => 'roles',
      'disable_field[edit][roles][]' => [$this->role1],
    ]);

    $node = $this->drupalCreateNode(['type' => 'test']);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');
  }

  /**
   * Enable the field for certain roles on the content edit form.
   */
  public function testDisableFieldOnEditFormEnableForCertainRoles(): void {
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], [
      'disable_field[edit][disable]' => 'roles_enable',
      'disable_field[edit][roles][]' => [$this->role1],
    ]);

    $node = $this->drupalCreateNode(['type' => 'test']);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');

    $this->drupalLogin($this->user1);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');

    $this->drupalLogin($this->user2);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');
  }

  /**
   * Test the permissions provided by the disable_field module.
   */
  public function testDisableFieldSettingsPermission() {
    $assert_session = $this->assertSession();
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string');

    $this->drupalLogin($this->user1);
    $this->drupalGet('/admin/structure/types/manage/test/fields/node.test.field_test');
    $assert_session->elementExists('css', 'select[name="disable_field[add][disable]"]');
    $assert_session->elementExists('css', 'select[name="disable_field[edit][disable]"]');

    $this->drupalLogin($this->user2);
    $this->drupalGet('/admin/structure/types/manage/test/fields/node.test.field_test');
    $assert_session->elementNotExists('css', 'select[name="disable_field[add][disable]"]');
    $assert_session->elementNotExists('css', 'select[name="disable_field[edit][disable]"]');
  }

  /**
   * Test that a disabled field keeps it's value.
   */
  public function testDisableFieldKeepValuesOnDisabledState() {
    $assert_session = $this->assertSession();
    $this->fieldUIAddNewField('admin/structure/types/manage/test', 'test', 'Test field', 'string', [], [
      'set_default_value' => TRUE,
      'default_value_input[field_test][0][value]' => 'default_test_value',
      'disable_field[add][disable]' => 'roles',
      'disable_field[add][roles][]' => [$this->role1],
      'disable_field[edit][disable]' => 'roles',
      'disable_field[edit][roles][]' => [$this->role1],
    ]);
    $node = $this->drupalCreateNode(['type' => 'test']);

    // The admin user can edit the field. Make sure the value is saved.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsNotDisabledByFieldName('field_test');
    $this->submitForm(['field_test[0][value]' => 'test_value'], 'Save');
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'test_value');

    // User 1 cannot edit the field. Make sure the value stays the same.
    $this->drupalLogin($this->user1);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'test_value');
    $this->submitForm([], 'Save');
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'test_value');

    // User 1 cannot edit the field. Make sure the value stays the same.
    // Even when the user is tampering with the data.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');
    $this->submitForm(['field_test[0][value]' => 'new_value'], 'Save');
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'test_value');

    // Check if a disabled field keeps it default value.
    $this->drupalGet('/node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('field_test');
    $this->submitForm(['title[0][value]' => 'test_title'], 'Save');
    $node = $this->drupalGetNodeByTitle('test_title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->checkIfFieldIsDisabledByFieldName('field_test');
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'default_test_value');
    $this->submitForm(['field_test[0][value]' => 'new_value'], 'Save');
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->elementAttributeContains('css', 'input[name="field_test[0][value]"]', 'value', 'default_test_value');
  }

  /**
   * Test the disable_field module with a paragraphs field.
   */
  public function testDisableFieldWithParagraphsField() {
    $this->addParagraphedContentType('paragraphed_content_type');

    // Disable the paragraph field on the add and edit form.
    $this->drupalGet('/admin/structure/types/manage/test/fields/node.paragraphed_content_type.field_paragraphs');
    $this->submitForm([
      'disable_field[add][disable]' => 'all',
      'disable_field[edit][disable]' => 'all',
    ], 'Save settings');

    // Add a Paragraph type.
    $paragraph_type = 'text_paragraph';
    $this->addParagraphsType($paragraph_type);

    // Add a text field to the text_paragraph type.
    $this->fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'text', 'Text', 'text_long');

    $this->drupalGet('/node/add/paragraphed_content_type');
    $this->checkIfFieldIsDisabledById('edit-field-paragraphs-0-subform-field-text-0-value');
    $this->checkIfFieldIsDisabledById('field-paragraphs-text-paragraph-add-more');
  }

  /**
   * Test disable_field module for base fields using base_field override ui.
   */
  public function testDisableFieldWithBaseFieldOverrideUi() {
    $this->drupalGet('/admin/structure/types/manage/test/fields/base-field-override/title/add');
    $this->submitForm([
      'disable_field[add][disable]' => 'all',
      'disable_field[edit][disable]' => 'all',
    ], 'Save settings');
    $this->drupalGet('node/add/test');
    $this->checkIfFieldIsDisabledByFieldName('title');
  }

}
