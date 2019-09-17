<?php

namespace Drupal\force_password_change\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface;
use Drupal\user\UserDataInterface;

class ForcePasswordChangeService implements ForcePasswordChangeServiceInterface
{
	/**
	 * The force password change data mapper
	 *
	 * @var \Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface
	 */
	protected $mapper;

	/**
	 * The current user
	 *
	 * @var \Drupal\Core\Session\AccountProxyInterface
	 */
	protected $currentUser;

	/**
	 * The config factory object
	 *
	 * @var \Drupal\Core\Config\ConfigFactoryInterface
	 */
	protected $configFactory;

	/**
	 * The config factory object
	 *
	 * @var \Drupal\user\UserDataInterface
	 */
	protected $userData;

	/**
	 * Constructs a ForcePasswordChangeService object.
	 *
	 * @param \Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface $mapper
	 *   The force password change data mapper
	 * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
	 *   The current user
	 * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
	 *   The config factory
	 * @param \Drupal\user\UserDataInterface $userData
	 *   The user data service
	 */
	public function __construct(ForcePasswordChangeMapperInterface $mapper, AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory, UserDataInterface $userData)
	{
		$this->mapper = $mapper;
		$this->currentUser = $currentUser;
		$this->configFactory = $configFactory;
		$this->userData = $userData;
	}

	/**
	 * {@inheritdoc}
	 */
	public function forceUsersPasswordChange($uids = [])
	{
		if(!count($uids))
		{
			$uids = $this->mapper->getActiveUserIds();
		}

		foreach($uids as $uid)
		{
			$this->forceUserPasswordChange($uid);
			$this->registerForcePasswordTime($uid);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function forceUserPasswordChange($uid)
	{
		$this->userData->set('force_password_change', $uid, 'pending_force', 1);
	}

	/**
	 * {@inheritdoc}
	 */
	public function registerForcePasswordTime($uid)
	{
		$this->userData->set('force_password_change', $uid, 'last_force', $this->getRequestTime());
	}

	/**
	 * {@inheritdoc}
	 */
	public function checkForForce()
	{
		// Default is to not redirect
		$redirect = FALSE;

		// If the user's account has been flagged for expiry, a redirect is required
		if($this->userData->get('force_password_change', $this->currentUser->id(), 'pending_force'))
		{
			$redirect = 'admin_forced';
		}
		// Only check to see if their password has expired if password expiry
		// is turned on in the module settings page.
		elseif($this->configFactory->get('force_password_change.settings')->get('expire_password'))
		{
			// The user's account has not been flagged for password expiry. Check to see
			// if their password has expired according to the rules of the module.

			// First thing is to check the time of their last password change,
			// and the time of their account creation
			$last_change = $this->userData->get('force_password_change', $this->currentUser->id(), 'last_change');
			$created = $this->mapper->getUserCreatedTime($this->currentUser->id());

			// Get the time period after which their password should expire
			// according to the rules laid out in the module settings page. Only the
			// role with the highest priority is retrieved
			$expiry = $this->mapper->getExpiryTimeFromRoles($this->currentUser->getRoles());

			// Test to see if their password has expired
			if($expiry && ($last_change && ($this->getRequestTime() - $expiry) > $last_change) || (!$last_change && ($this->getRequestTime() - $expiry) > $created))
			{
				// Their password has expired, so their user account is flagged
				// and the expiration time period is returned, which will trigger the redirect
				// and be used to generate the message shown to the user
				$this->userData->set('force_password_change', $this->currentUser->id(), 'pending_force', 1);

				$redirect = 'expired';
			}
		}

		return $redirect;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLastChangeForRole($rid)
	{
		return $this->mapper->getLastChangeForRole($rid);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateLastChangeForRoles($rids)
	{
		$this->mapper->updateLastChangeForRoles($rids);
	}

	/**
	 * {@inheritdoc}
	 */
	public function insertExpiryForRoles($values)
	{
		$this->mapper->insertExpiryForRoles($values);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateExpiryForRole($rid, $time_period, $weight)
	{
		$this->mapper->updateExpiryForRole($rid, $time_period, $weight);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUserCountForRole($rid)
	{
		$rid = $rid == 'authenticated' ? FALSE : $rid;

		return $this->mapper->getUserCountForRole($rid);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPendingUsersForRole($rid, $countQuery = FALSE)
	{
		$rid = $rid == 'authenticated' ? FALSE : $rid;
		$uids = $this->mapper->getPendingUserIds($rid);

		if($countQuery)
		{
			return count($uids);
		}

		return $this->userLoadMultiple($uids);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNonPendingUsersForRole($rid)
	{
		$rid = $rid == 'authenticated' ? FALSE : $rid;

		$uids = $this->mapper->getNonPendingUserIds($rid);

		return $this->userLoadMultiple($uids);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRoleExpiryTimePeriods()
	{
		return $this->mapper->getRoleExpiryTimePeriods();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getUsersForRole($rid, $uidOnly = TRUE)
	{
		$uids = $this->mapper->getUserIdsForRole($rid);

		if($uidOnly)
		{
			return $uids;
		}

		return $this->userLoadMultiple($uids);
	}

	/**
	 * {@inheritdoc}
	 */
	public function setChangedTimeForUser($uid)
	{
		$this->userData->set('force_password_change', $uid, 'last_change', $this->getRequestTime());
	}

	/**
	 * {@inheritdoc}
	 */
	public function removePendingForce($uid)
	{
		$this->userData->set('force_password_change', $uid, 'pending_force', 0);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTextDate($seconds)
	{
		$year = 60 * 60 * 24 * 365;
		if($timestamp % $year === 0)
		{
			$time_period = $timestamp / $year;
			$time_period = ($time_period > 1) ? $time_period . ' ' . t('years') : t('year');
		}
		else
		{
			$week = 60 * 60 * 24 * 7;
			if($timestamp % $week === 0)
			{
				$time_period = $timestamp / $week;
				$time_period = ($time_period > 1) ? $time_period . ' ' . t('weeks') : t('week');
			}
			else
			{
				$day = 60 * 60 * 24;
				if($timestamp % $day === 0)
				{
					$time_period = $timestamp / $day;
					$time_period = ($time_period > 1) ? $time_period . ' ' . t('days') : t('day');
				}
				else
				{
					$hour = 60 * 60;
					if($timestamp % $hour === 0)
					{
						$time_period = $timestamp / $hour;
						$time_period = ($time_period > 1) ? $time_period . ' ' . t('hours') : t('hour');
					}
				}
			}
		}

		return $time_period;
	}

	/**
	 * {@inheritdoc}
	 */
	public function addFirstTimeLogin($uid)
	{
		$this->mapper->addFirstTimeLogin($uid);
	}

	/**
	 * {@inheritdoc}
	 */
	public function removeFirstTimeLogin($uid)
	{
		$this->mapper->removeFirstTimeLogin($uid);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFirstTimeLoginUids()
	{
		return $this->mapper->getFirstTimeLoginUids();
	}

	/**
	 * Helper function to load mulitple user objects. Spun out into a protected function
	 * to allow for overriding in Unit Tests.
	 *
	 * @param array $uids
	 *   The User IDs of the users to load.
	 *
	 * @return array
	 *   An array of fully loaded user objects.
	 */
	protected function userLoadMultiple(array $uids)
	{
		return user_load_multiple($uids);
	}

	/**
	 * Retrieve the timestamp for the current request. Spun out into a protected function
	 * to allow for overriding in Unit Tests.
	 *
	 * @return int
	 *   A UNIX timestamp representing the time of the current request
	 */
	protected function getRequestTime()
	{
		return REQUEST_TIME;
	}
}
