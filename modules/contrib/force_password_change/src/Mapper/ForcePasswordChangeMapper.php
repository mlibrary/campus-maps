<?php

namespace Drupal\force_password_change\Mapper;

use Drupal\Core\Database\Connection;
use Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface;

class ForcePasswordChangeMapper implements ForcePasswordChangeMapperInterface
{
	/**
	 * The database connection
	 *
	 * @var \Drupal\Core\Database\Connection
	 */
	protected $connection;

	/**
	 * Constructs a ForcePasswordChangeMapper object.
	 *
	 * @param \Drupal\Core\Database\Connection $connection
	 *   The database connection
	 */
	public function __construct(Connection $connection)
	{
	  $this->connection = $connection;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getActiveUserIds()
	{
		return $this->connection->query('SELECT uid FROM {users_field_data} WHERE status = :one', [':one' => 1])->fetchCol();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserCreatedTime($uid)
	{
		return $this->connection->select('users_field_data', 'ufd')
			->fields('ufd', ['created'])
			->condition('ufd.uid', $uid)
			->execute()
			->fetchField();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getExpiryTimeFromRoles(array $rids)
	{
		return $this->connection->select('force_password_change_expiry', 'fpce')
			->fields('fpce', ['expiry'])
			->condition('fpce.rid', $rids, 'IN')
			->orderBy('fpce.weight')
			->range(0, 1)
			->addTag('force_password_change_expiry_check')
			->execute()
			->fetchField();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLastChangeForRole($rid)
	{
		return $this->connection->query('SELECT last_force FROM {force_password_change_roles} WHERE rid = :rid', [':rid' => $rid])->fetchField();
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateLastChangeForRoles(array $rids)
	{
		$this->connection->update('force_password_change_roles')
			->fields(['last_force' => REQUEST_TIME])
			->condition('rid', $rids, 'IN')
			->execute();
	}

	/**
	 * {@inheritdoc}
	 */
	public function insertExpiryForRoles(array $values)
	{
		if(count($values))
		{
			// Prepare the insert query for new roles that have not had their password expiry set
			$query = $this->connection->insert('force_password_change_expiry')
				->fields(['rid', 'expiry', 'weight']);
			foreach($values as $role_values)
			{
				$query->values($role_values);
			}

			$query->execute();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateExpiryForRole($rid, $time_period, $weight)
	{
		$this->connection->update('force_password_change_expiry')
			->fields(['expiry' => $time_period, 'weight' => $weight])
			->condition('rid', $rid)
			->execute();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserCountForRole($rid = FALSE)
	{
		$sql = 'SELECT COUNT(ufd.uid) FROM {users_field_data} AS ufd ';
		$values = [];

		if($rid)
		{
			$sql .= 'JOIN {user__roles} AS roles ON roles.entity_id = ufd.uid AND roles.roles_target_id = :rid ';
			$values[':rid'] = $rid;
		}
		$sql .= 'WHERE ufd.status = 1';

		return $this->connection->query($sql, $values)->fetchField();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPendingUserIds($rid = FALSE)
	{
		$query = $this->connection->select('users_data', 'ud')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
		$query->join('users_field_data', 'ufd', 'ufd.uid = ud.uid AND ufd.status = :one', [':one' => 1]);
		$query->addField('ud', 'uid');
		$query->addTag('force_password_change_pending_users')
			->limit(20)
			->condition('ud.module', 'force_password_change')
			->condition('ud.name', 'pending_force')
			->condition('ud.value', 1);

		// If the role is anything other than the authenticated users role, we need to
		// limit the users to the members of that role
		if($rid)
		{
			$alias = $query->join('user__roles', 'ur', 'ur.entity_id = ud.uid');
			$query->condition($alias . '.roles_target_id', $rid);
		}

		return $query->execute()->fetchCol();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNonPendingUserIds($rid = FALSE)
	{
		$query = $this->connection->select('users', 'u')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
		$query->join('users_field_data', 'ufd', 'ufd.uid = u.uid AND ufd.status = :one', [':one' => 1]);
		$alias = $query->leftJoin('users_data', 'ud', 'ud.uid = u.uid AND ud.module = :force_password_change AND ud.name = :pending_force AND ud.value = :one', [':force_password_change' => 'force_password_change', ':pending_force' => 'pending_force', ':one' => 1]);
		$query->addField('u', 'uid');
		$query
			->addTag('force_password_change_pending_users')
			->limit(100)
			->condition('u.uid', 0, '!=')
			->isNull('ud.uid');

		if($rid)
		{
			$alias2 = $query->join('user__roles', 'ur', 'ur.entity_id = ud.uid');
			$query->condition($alias2 . '.roles_target_id', $rid);
		}

		return $query->execute()->fetchCol();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRoleExpiryTimePeriods()
	{
		return $this->connection->query('SELECT rid, expiry, weight from {force_password_change_expiry} ORDER BY weight, rid')->fetchAll();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserIdsForRole($rid)
	{
		return $this->connection->query
		(
			'SELECT entity_id ' .
			'FROM {user__roles} ' .
			'WHERE roles_target_id = :rid',
			[':rid' => $rid]
		)->fetchCol();
	}

	/**
	 * {@inheritdoc}
	 */
	public function addFirstTimeLogin($uid)
	{
		$this->connection->query(
			'INSERT INTO {force_password_change_uids} (category, uid) VALUES (:category, :uid)',
			[':category' => 'first_login', ':uid' => $uid]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function removeFirstTimeLogin($uid)
	{
		$this->connection->query(
			'DELETE FROM {force_password_change_uids} WHERE category= :category AND uid = :uid',
			[':category' => 'first_login', ':uid' => $uid]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFirstTimeLoginUids()
	{
		return $this->connection->query('SELECT uid FROM {force_password_change_uids} WHERE category = :category',
			[':category' => 'first_login']
		)->fetchCol();
	}
}
