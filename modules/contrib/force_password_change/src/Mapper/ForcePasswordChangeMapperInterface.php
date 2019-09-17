<?php

namespace Drupal\force_password_change\Mapper;

use Drupal\Core\Database\Connection;
use Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface;

interface ForcePasswordChangeMapperInterface
{
	/**
	 * Get a list of User IDs for active user accounts
	 *
	 * @return array
	 *   An array of User IDs for all active users in the system.
	 */
	public function getActiveUserIds();

	/**
	 * Get the timestamp at which a user account was created
	 *
	 * @param int $uid
	 *   The User ID for the account whose creation time should be retrieved.
	 *
	 * @return int
	 *   The Unix timstamp at which the given account was created.
	 */
	public function getUserCreatedTime($uid);

	/**
	 * Retrieve the highest priority expiry time from
	 *
	 * @param array $rids
	 *   An array of Role Ids (strings) of roles whose expiry time periods should be checked
	 *
	 * @return bool|int
	 *   The number of seconds of the role with the highest priority after which a users password
	 *   should expire. FALSE if no expiry time was found.
	 */
	public function getExpiryTimeFromRoles(array $rids);

	/**
	 * Retrieve the last timestamp at which all users in a role were
	 * forced to change their password.
	 *
	 * @param string $rid
	 *   The Role ID for which the timestamp should be retrieved.
	 *
	 * @return int
	 *   The Unix timestamp at which the users in the role were forced to
	 *   change their password.
	 */
	public function getLastChangeForRole($rid);

	/**
	 * Update the timestamp at which all users in the given roles have been
	 * forced to change their password.
	 *
	 * @param array $rids
	 *   An array of Role IDs for which the timestamp should be updated.
	 */
	public function updateLastChangeForRoles(array $rids);

	/**
	 * Insert time periods at which all users in the given roles will be
	 * forced to change their password.
	 *
	 * @param array $values
	 *   An array data representing the time period after which users in roles will be required
	 *   to change their password. Each row of the array must contain the following keys:
	 *   - rid: The Role ID of the role to be inserted
	 *   - expiry: The number of seconds after which their password should expire
	 *   - weight: The priority of the expiration in relation to other roles.
	 */
	public function insertExpiryForRoles(array $values);

	/**
	 * Update the time periods at which all users in the given role will be
	 * forced to change their password.
	 *
	 * @param string $rid
	 *   The Role ID of the role to be inserted
	 * @param int $expiry
	 *   The number of seconds after which their password should expire
	 * @param int $weight
	 *   The priority of the expiration in relation to other roles.
	 */
	public function updateExpiryForRole($rid, $time_period, $weight);

	/**
	 * Retrieve the number of users in the given role
	 *
	 * @param boolean|string $rid
	 *   The Role ID of the role for which the count should be retrieved. Set to FALSE to
	 *   retrieve the count for all authenticated users in the system.
	 *
	 * @return int
	 *   The number of users in the given role.
	 */
	public function getUserCountForRole($rid = FALSE);

	/**
	 * Retrieve the User IDs of all users in the given role with a pending forced password change.
	 *
	 * @param boolean|string $rid
	 *   The Role ID of the role to be checked. Set to FALSE to retrieve the UIDs of all
	 *   authenticated users.
	 *
	 * @return array
	 *   An array of User IDs for users in the given role who have a pending forced password
	 *   change.
	 */
	public function getPendingUserIds($rid = FALSE);

	/**
	 * Retrieve the User IDs of all users in the given role without a pending forced password change.
	 *
	 * @param boolean|string $rid
	 *   The Role ID of the role to be checked. Set to FALSE to retrieve the UIDs of all
	 *   authenticated users.
	 *
	 * @return array
	 *   An array of User IDs for users in the given role who do not have a pending forced
	 *   password change.
	 */
	public function getNonPendingUserIds($rid = FALSE);

	/**
	 * Retrieve the expiry time for the given roles
	 *
	 * @param array $rids
	 *   An array of Role Ids (strings) of roles whose expiry time periods should be retrieved
	 *
	 * @return array
	 *   An array of time periods in seconds after which passwords for users in the role will expire,
	 *   keyed by Role ID.
	 */
	public function getRoleExpiryTimePeriods();

	/**
	 * Retrieve the User ID for all users in a given role
	 *
	 * @param string $rid
	 *   The Role ID of the role for which User IDs should be retrieved.
	 *
	 * @return array
	 *   An array of User IDs for all users in the given role.
	 */
	public function getUserIdsForRole($rid);

	/**
	 * Set a user as having been forced to change their password on their first login
	 *
	 * @param int $uid
	 *   The User ID of the user who has been forced to change their password on first login.
	 */
	public function addFirstTimeLogin($uid);

	/**
	 * Remove the mark for a user who was forced to change their password on their first login
	 *
	 * @param int $uid
	 *   The User ID of the user who was forced to change their password on first login.
	 */
	public function removeFirstTimeLogin($uid);

	/**
	 * Retrieve a list of User IDs for users who have a pending forced password change
	 * on their first login.
	 *
	 * @return array
	 *   An array of User IDs of users who have a pending forced password change on their
	 *   first login.
	 */
	public function getFirstTimeLoginUids();
}
