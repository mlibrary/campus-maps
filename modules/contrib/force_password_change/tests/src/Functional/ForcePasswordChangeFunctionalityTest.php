<?php

namespace Drupal\Tests\force_password_change\Functional;
use Drupal\user\Entity\Role;

/**
 * @group force_password_change
 */
class ForcePasswordChangeFunctionalityTest extends ForcePasswordChangeBrowserTestBase
{
	/**
	 * Modules to enable.
	 *
	 * @var array
	 */
	protected static $modules = ['force_password_change'];

	/**
	 * Tests that users in the athenticated role that has it's passwords forced are forced
	 * to change their password immediately when the settings are set for immediate
	 * force.
	 */
	public function testImmedidatePasswordForceForAuthenticated()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);

		// Go to admin page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-roles-authenticated');
		$this->click('#edit-submit');

		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Attempt to submit without changing password
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Change password
		$this->fillTextValue('#edit-current-pass', $admin_user->passRaw);
		$this->fillTextValue('#edit-pass-pass1', 'asdf');
		$this->fillTextValue('#edit-pass-pass2', 'asdf');
		$this->click('#edit-submit');

		// Test that redirect happened, meaning password was succesfully changed
		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');

		// Go to the authenticated role page
		$this->drupalGet('admin/config/people/force_password_change/list/authenticated');
		$this->assertStatusCodeEquals(200);
		$this->assertCheckboxExists('#edit-force-password-change');
		$this->checkCheckbox('#edit-force-password-change');
		$this->click('#edit-submit');

		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');
	}

	/**
	 * Tests that users in a given role that has it's passwords forced are forced
	 * to change their password immediately when the settings are set for immediate
	 * force.
	 */
	public function testImmedidatePasswordForceForSecondaryGroup()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);
		$this->createRole([], 'admin');
		$admin_user->addRole('admin');
		$admin_user->save();

		// Go to admin page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-roles-admin');
		$this->click('#edit-submit');

		// Assert redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Attempt to submit without changing password
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Change password
		$this->fillTextValue('#edit-current-pass', $admin_user->passRaw);
		$this->fillTextValue('#edit-pass-pass1', 'asdf');
		$this->fillTextValue('#edit-pass-pass2', 'asdf');
		$this->click('#edit-submit');

		// Test that redirect happened, meaning password was succesfully changed
		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');

		// Go to the admin role page
		$this->drupalGet('admin/config/people/force_password_change/list/admin');
		$this->assertStatusCodeEquals(200);
		$this->assertCheckboxExists('#edit-force-password-change');
		$this->checkCheckbox('#edit-force-password-change');
		$this->click('#edit-submit');

		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');
	}

	/**
	 * Tests that users in a given role that has it's passwords forced are forced
	 * to change their password on next login when the settings are set for next login.
	 */
	public function testNextLoginPasswordForce()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);

		// Test link exists on admin page (restrict_ip.links.menu.yml)
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-roles-authenticated');
		$this->selectRadio('#edit-login-only-1');
		$this->click('#edit-submit');

		$this->assertSession()->addressMatches('/\/admin\/config\/people\/force_password_change$/');
		$this->assertSession()->pageTextContains('Users in the following roles will be required to change their password on their next login:');

		// Re-login the user
		$this->drupalLogout();
		$this->drupalLogin($admin_user);

		// Check that the redirect happened
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Change password
		$this->fillTextValue('#edit-current-pass', $admin_user->passRaw);
		$this->fillTextValue('#edit-pass-pass1', 'asdf');
		$this->fillTextValue('#edit-pass-pass2', 'asdf');
		$this->click('#edit-submit');

		// Check that the password force was completed
		$this->assertSession()->pageTextNotContains('An administrator has required that you change your password. Please change your password to proceed.');
	}

	public function testFirstTimeLoginForce()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);

		// Test link exists on admin page (restrict_ip.links.menu.yml)
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-first-time-login-password-change');
		$this->click('#edit-submit');
		$this->assertCheckboxChecked('#edit-first-time-login-password-change');

		$regular_user = $this->drupalCreateUser([]);
		$this->drupalLogout();
		$this->drupalLogin($regular_user);
		
		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/' . $regular_user->id() . '\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');
	}

	public function testAuthenticatedUserPasswordExpire()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$admin_role = $this->createRole([], 'admin');
		// Give the admin role to the user
		$admin_user->addRole($admin_role);
		$admin_user->save();
		$this->drupalLogin($admin_user);

		// Go to admin page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-expire-password');

		// Set the time after which the password should expire
		$this->fillTextValue('#edit-table-authenticated-time-time-quantity', '1');
		$this->selectSelectOption('#edit-table-authenticated-time-time-period', 'hour');
		$this->selectSelectOption('#edit-table-authenticated-weight', '-1');
		$this->click('#edit-submit');

		// Edit the value in the database so we don't have to wait an hour
		\Drupal::database()->query(
			'UPDATE {force_password_change_expiry} SET expiry = :expiry WHERE rid = :authenticated',
			[':expiry' => 1, ':authenticated' => 'authenticated']
		);
		sleep(2);

		// Reload the page. Our password should have expired.
		$this->drupalGet('admin/config/people/force_password_change');

		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/' . $admin_user->id() . '\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Change password
		$this->fillTextValue('#edit-current-pass', $admin_user->passRaw);
		$this->fillTextValue('#edit-pass-pass1', 'asdf');
		$this->fillTextValue('#edit-pass-pass2', 'asdf');
		$this->click('#edit-submit');
		$this->assertSession()->pageTextNotContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Test link exists on admin page (restrict_ip.links.menu.yml)
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->assertSession()->addressMatches('/^\/admin\/config\/people\/force_password_change$/');

		// Set the admin time to have a higher priority than the authenticated time limit
		$this->fillTextValue('#edit-table-admin-time-time-quantity', '1');
		$this->selectSelectOption('#edit-table-admin-time-time-period', 'hour');
		$this->selectSelectOption('#edit-table-admin-weight', '-1');
		$this->fillTextValue('#edit-table-authenticated-time-time-quantity', '1');
		$this->selectSelectOption('#edit-table-authenticated-time-time-period', 'hour');
		$this->selectSelectOption('#edit-table-authenticated-weight', '1');

		// Edit the value in the database so we don't have to wait an hour
		\Drupal::database()->query(
			'UPDATE {force_password_change_expiry} SET expiry = :expiry WHERE rid = :authenticated',
			[':expiry' => REQUEST_TIME - 1, ':authenticated' => 'authenticated']
		);

		// Reload the page. Our password should not have expired since the admin user has a longer
		// expiry time.
		$this->drupalGet('admin/config/people/force_password_change');

		// Should still be on the same page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
	}

	/**
	 * Test that the force password change checkbox on the user edit page works
	 */
	public function testUserEditPageForcePassword()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages', 'administer users']);
		$this->drupalLogin($admin_user);

		// Go to user edit page
		$this->drupalGet('user/' . $admin_user->id() . '/edit');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-force-password-change');
		$this->click('#edit-submit');

		// The user edit page is whitelisted, so we need to visit another page to test if force worked
		$this->drupalGet('user/' . $admin_user->id());
		$this->assertSession()->addressMatches('/\/user\/' . $admin_user->id() . '\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Change password
		$this->fillTextValue('#edit-current-pass', $admin_user->passRaw);
		$this->fillTextValue('#edit-pass-pass1', 'asdf');
		$this->fillTextValue('#edit-pass-pass2', 'asdf');
		$this->click('#edit-submit');

		// Go to admin page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		// Set system to check on login only.
		$this->selectRadio('#edit-login-only-1');
		$this->click('#edit-submit');

		// Go to user edit page
		$this->drupalGet('user/' . $admin_user->id() . '/edit');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-force-password-change');
		$this->click('#edit-submit');

		// The user edit page is whitelisted, so we need to visit another page to ensure the user
		// was not forced
		$this->drupalGet('user/' . $admin_user->id());
		$this->assertSession()->addressMatches('/\/user\/' . $admin_user->id() . '$/');

		// Create new user
		$regular_user = $this->drupalCreateUser([]);
		// Go to user edit page
		$this->drupalGet('user/' . $regular_user->id() . '/edit');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-force-password-change');
		$this->click('#edit-submit');

		$this->drupalLogout();
		$this->drupalLogin($regular_user);

		// Confirm the redirect occurred upon login.
		$this->assertSession()->addressMatches('/\/user\/' . $regular_user->id() . '\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');
	}

	public function testDisableModuleInSettingsPhp()
	{
		$admin_user = $this->drupalCreateUser(['administer force password change', 'access administration pages']);
		$this->drupalLogin($admin_user);

		// Go to admin page
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
		$this->checkCheckbox('#edit-roles-authenticated');
		$this->click('#edit-submit');

		// Check that redirect properly occurred
		$this->assertSession()->addressMatches('/\/user\/2\/edit$/');
		$this->assertSession()->pageTextContains('An administrator has required that you change your password. Please change your password to proceed.');

		// Disable module in cofig
		\Drupal::service('config.factory')->getEditable('force_password_change.settings')->set('enabled', FALSE)->save();

		// Go to admin page to ensure functionality is disabled
		$this->drupalGet('admin/config/people/force_password_change');
		$this->assertStatusCodeEquals(200);
	}
}
