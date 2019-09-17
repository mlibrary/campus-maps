<?php

namespace Drupal\Tests\force_password_change\Functional;
use Drupal\user\Entity\Role;

/**
 * @group force_password_change
 */
class ForcePasswordChangeAdminUiTest extends ForcePasswordChangeBrowserTestBase
{
	/**
	 * Modules to enable.
	 *
	 * @var array
	 */
	protected static $modules = ['force_password_change'];

	/**
	 * Test that the admin UI page is properly linked to, that all the required elements
	 * exist, and that the form is working properly
	 */
	public function testForcePasswordChangeAdminPage()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);

		// Test link exists on admin page (restrict_ip.links.menu.yml)
		$this->drupalGet('admin/config');
		$this->assertStatusCodeEquals(200);
		$this->assertSession()->pageTextContains('Force Password Change');
		$this->assertSession()->pageTextContains('Settings related to forcing password changes');
		$this->clickLink('Force Password Change');

		// Test admin page exists
		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');
		$this->assertStatusCodeEquals(200);

		// Test first time login checkbox exists
		$this->assertCheckboxExists('edit-first-time-login-password-change');

		// Test that pending password check time radios exist
		$this->assertRadioExists('edit-login-only-0');
		$this->assertRadioExists('edit-login-only-1');

		// Test that checkbox exists to force authenticated users to change role
		$this->assertCheckboxExists('edit-roles-authenticated');
		$this->assertElementExistsXpath('//div[@id="edit-roles"]//label[@for="edit-roles-authenticated"]/text()[contains(., "Authenticated user (Users in role: 2 | Users with pending forced password change: 0 | ")]');
		$this->assertElementExistsXpath('//div[@id="edit-roles"]//label[@for="edit-roles-authenticated"]/a[@href="/admin/config/people/force_password_change/list/authenticated" and text()="Details"]');

		// Create admin role
		$new_role = $this->createRole([], 'admin', 'Admin');

		// Reload page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');
		$this->assertStatusCodeEquals(200);

		// Test that checkbox exists to force admin role users to change role
		$this->assertCheckboxExists('edit-roles-admin');
		$this->assertElementExistsXpath('//div[@id="edit-roles"]//label[@for="edit-roles-admin"]/text()[contains(., "Admin (Users in role: 0 | Users with pending forced password change: 0 |")]');
		$this->assertElementExistsXpath('//div[@id="edit-roles"]//label[@for="edit-roles-admin"]/a[@href="/admin/config/people/force_password_change/list/admin" and text()="Details"]');

		// Add admin role to current user
		$admin_user->addRole($new_role);
		$admin_user->save();

		// Reload page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');
		$this->assertStatusCodeEquals(200);

		// Test that the number of admin users increased
		$this->assertElementExistsXpath('//div[@id="edit-roles"]//label[@for="edit-roles-admin"]/text()[contains(., "Admin (Users in role: 1 | Users with pending forced password change: 0 |")]');

		// Test that password expiry checkbox exists
		$this->assertCheckboxExists('edit-expire-password');

		// Test that the role expirty table exists
		$this->assertElementExists('#force_password_change_role_expiry_table');
		$this->assertElementExistsXpath('//table[@id="force_password_change_role_expiry_table"]//tr[@data-drupal-selector="edit-table-authenticated"]//td/text()[contains(., "Authenticated user (Users in role: 2 | Users with pending forced password change: 0")]');
		$this->assertElementExistsXpath('//table[@id="force_password_change_role_expiry_table"]//tr[@data-drupal-selector="edit-table-authenticated"]//td/a[@href="/admin/config/people/force_password_change/list/authenticated" and text()="Details"]');
		$this->assertElementExistsXpath('//table[@id="force_password_change_role_expiry_table"]//tr[@data-drupal-selector="edit-table-admin"]//td/text()[contains(., "Admin (Users in role: 1 | Users with pending forced password change: 0")]');
		$this->assertElementExistsXpath('//table[@id="force_password_change_role_expiry_table"]//tr[@data-drupal-selector="edit-table-admin"]//td/a[@href="/admin/config/people/force_password_change/list/admin" and text()="Details"]');

		// Test that the fields to alter role expiry for authenticated users exist
		$this->assertElementExists('#edit-table-authenticated-time-time-quantity');
		$this->assertElementExists('#edit-table-authenticated-time-time-period');
		$this->assertElementExists('#edit-table-authenticated-weight');
		
		// Test that the fields to alter role expiry for admin users exist
		$this->assertElementExists('#edit-table-admin-time-time-quantity');
		$this->assertElementExists('#edit-table-admin-time-time-period');
		$this->assertElementExists('#edit-table-admin-weight');

		// Test that submit button exists
		$this->assertElementExists('#edit-actions #edit-submit');

		// Next, fill in values to test that the form works and properly saves the values

		$this->checkCheckbox('#edit-first-time-login-password-change');
		$this->selectRadio('#edit-login-only-1');
		$this->checkCheckbox('#edit-expire-password');

		// Fill in expiry for Authenticated users
		$this->fillTextValue('#edit-table-authenticated-time-time-quantity', '3');
		$this->selectSelectOption('#edit-table-authenticated-time-time-period', 'hour');
		$this->selectSelectOption('#edit-table-authenticated-weight', '-1');

		// Fill in expiry for Admin users
		$this->fillTextValue('#edit-table-admin-time-time-quantity', '5');
		$this->selectSelectOption('#edit-table-admin-time-time-period', 'week');
		$this->selectSelectOption('#edit-table-admin-weight', '-5');
		$this->click('#edit-submit');

		// Confirm proper values have been selected
		$this->assertCheckboxChecked('#edit-first-time-login-password-change');
		$this->assertRadioSelected('#edit-login-only-1');
		$this->assertCheckboxChecked('#edit-expire-password');
		$this->assertTextValue('#edit-table-authenticated-time-time-quantity', '3');
		$this->assertSelectOption('#edit-table-authenticated-time-time-period', 'hour');
		$this->assertSelectOption('#edit-table-authenticated-weight', '-1');
		$this->assertTextValue('#edit-table-admin-time-time-quantity', '5');
		$this->assertSelectOption('#edit-table-admin-time-time-period', 'week');
		$this->assertSelectOption('#edit-table-admin-weight', '-5');
	}
}
