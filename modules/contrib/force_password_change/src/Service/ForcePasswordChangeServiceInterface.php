<?php

namespace Drupal\force_password_change\Service;

interface ForcePasswordChangeServiceInterface
{
	/**
	 * Force a password change for the given users.
	 *
	 * @param array $uids
	 *   An array of User IDs for users who should be forced to
	 *   change their password.
	 */
	public function forceUsersPasswordChange($uids = []);

	/**
	 * Force a password change for a given user.
	 *
	 * @param int $uids
	 *   The User ID for the user who should be forced to
	 *   change their password.
	 */
	public function forceUserPasswordChange($uid);

	/**
	 * Register the time at which a user has been forced to change
	 * their password.
	 *
	 * @param int $uid
	 *   The User ID for the user who has had a password change forced.
	 */
	public function registerForcePasswordTime($uid);

	/**
	 * Check to see if the current user has a pending forced password change.
	 *
	 * @return boolean
	 *   TRUE if the user has a pending forced password change.
	 *   FALSE if they do not.
	 */
	public function checkForForce();

	/**
	 * Get the last time at which a role was forced to change passwords for all users.
	 *
	 * @param string $rid
	 *   The Role ID of the role to be checked.
	 *
	 * @return int
	 *   A UNIX timestamp representing the last time all uses in the role were forced to
	 *   change their passwords.
	 */
	public function getLastChangeForRole($rid);

	/**
	 * Update the time at which all users in the given roles have been forced to change
	 * their passwords.
	 *
	 * @param array $rids
	 *   An array of Role IDs whose times should be updated.
	 */
	public function updateLastChangeForRoles($rids);

	/**
	 * Insert the time period after which passwords should expire, for the given roles.
	 *
	 * @param array $values
	 *   An array of data to be inserted. Each element of the array should be an array that
	 *   contains the following values:
	 *   - rid: The Role ID of the role being inserted
	 *   - expiry: The number of seconds after which the password should expire
	 *   - weight: The priority of the expiry in relation to other roles
	 */
	public function insertExpiryForRoles($values);

	/**
	 * Update the time period expiration data for a given role
	 *
	 * @param string $rid
	 *   The Role ID of the role being updated.
	 * @param $time_period
	 *   The number of seconds after which the password should expire for users in the role.
	 * @param $weight
	 *   The priority of the expiration in relation to other roles
	 */
	public function updateExpiryForRole($rid, $time_period, $weight);

	/**
	 * Get the number of users in the given role
	 *
	 * @param string $rid
	 *   The Role for which the user count should be returned.
	 *
	 * @return int
	 *   The number of users in the role.
	 */
	public function getUserCountForRole($rid);

	/**
	 * Retrieve the users with a pending forced password change in a given role
	 *
	 * @param string $rid
	 *   The Role ID of the role in which users with a pending forced password
	 *   change should be retrieved.
	 * @param boolean $countQuery
	 *   A boolean indicating whether to return the number of users, or the loaded user objects.
	 *
	 * @return int|array
	 *   If $countQuery is TRUE, the number of users in the role with a pending forced password
	 *   change is returned. If $countQuery is FALSE, the loaded user objects of all users in the
	 *   role who have a pending forced password change will be returned.
	 */
	public function getPendingUsersForRole($rid, $countQuery = FALSE);

	/**
	 * Retrieve the user accounts of users in the role who do not have a pending forced
	 * password change.
	 *
	 * @param string $rid
	 *   The Role ID of the role for which users without a pending forced password change should
	 *   be retrieved.
	 */
	public function getNonPendingUsersForRole($rid);

	/**
	 * Get the time periods after which each role will expire
	 *
	 * @return array
	 *   An array of time periods in seconds, keyed by Role ID
	 */
	public function getRoleExpiryTimePeriods();

	/**
	 * Retrieve the users in a given role.
	 *
	 * @param string $rid
	 *   The Role ID of the role for which users should be retrieved.
	 * @param boolean $uidOnly
	 *   A boolean indicating whether the the User IDs should be returned, or the fullly loaded
	 *   user objects.
	 *
	 * @return array
	 *   If $uidOnly is set to TRUE, an array of User IDs for users in the given role.
	 *   If $uidOnly is set to FALSE, an array of fully loaded user objects for users in the given role.
	 */
	public function getUsersForRole($rid, $uidOnly = FALSE);

	/**
	 * Set the last time a user's password was changed to the current timestamp.
	 *
	 * @param int $uid
	 *   The User ID of the user whose last password time change should be registered.
	 */
	public function setChangedTimeForUser($uid);

	/**
	 * Remove a pending force for a given user
	 *
	 * @param int $uid
	 *   The User ID of the user whose pending password force should be removed.
	 */
	public function removePendingForce($uid);

	/**
	 * Converts a number of seconds to a human-friendly time period.
	 *
	 * @param int $seconds
	 *   The number of seconds to convert.
	 *
	 * @return Drupal\Core\StringTranslation\TranslatableMarkup
	 *   The amount of time in years, weeks, days or hours. The returned values is a translated string.
	 */
	public function getTextDate($seconds);

	/**
	 * Mark a user as having been forced to change their password on their first login
	 *
	 * @param int $uid
	 *   The User ID of the user who has been forced to change their password on first login. Note
	 *   that this is metadata only - the force still needs to be performed through other actions.
	 */
	public function addFirstTimeLogin($uid);

	/**
	 * Remove the mark for a user who was forced to change their password on their first login
	 *
	 * @param int $uid
	 *   The User ID of the user who was forced to change their password on first login. Note
	 *   that this is metadata only - the force still needs to be removed through other actions.
	 */
	public function removeFirstTimeLogin($uid);

	/**
	 * Retrieve a list of users who have a pending forced password change on their first login.
	 *
	 * @return array
	 *   An array of User IDs of users who have a pending forced password change on their
	 *   first login.
	 */
	public function getFirstTimeLoginUids();
}
