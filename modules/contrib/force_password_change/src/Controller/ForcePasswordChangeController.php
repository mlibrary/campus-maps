<?php

namespace Drupal\force_password_change\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\force_password_change\Controller\ForcePasswordChangeControllerInterface;
use Drupal\force_password_change\Service\ForcePasswordChangeService;
use Drupal\user\Entity\Role;
use Drupal\user\UserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ForcePasswordChangeController extends ControllerBase implements ForcePasswordChangeControllerInterface
{
	/**
	 * A form builder object
	 *
	 * @var \Drupal\Core\Form\FormBuilderInterface
	 */
	protected $formBuilder;

	/**
	 * The date formatter service
	 *
	 * @var \Drupal\Core\Datetime\DateFormatter
	 */
	protected $dateFormatter;

	/**
	 * The current user
	 *
	 * @var \Drupal\Core\Session\AccountProxy
	 */
	protected $currentUser;

	/**
	 * The configuration factory
	 *
	 * @var \Drupal\Core\Config\ConfigFactory
	 */
	protected $configFactory;

	/**
	 * The config factory object
	 *
	 * @var \Drupal\user\UserData
	 */
	protected $userData;

	/**
	 * The database connection
	 *
	 * @var \Drupal\force_password_change\Service\ForcePasswordChangeService
	 */
	protected $forcePasswordChangeService;

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container)
	{
		return new static
		(
			$container->get('form_builder'),
			$container->get('date.formatter'),
			$container->get('current_user'),
			$container->get('config.factory'),
			$container->get('user.data'),
			$container->get('force_password_change.service')
		);
	}

	/**
	 * Constructs a FriendController object.
	 *
	 * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
	 *   The form builder service.
	 * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
	 *   The date formatter service
	 * @param \Drupal\Core\Session\AccountProxy $currentUser
	 *   The current user
	 * @param \Drupal\Core\Config\ConfigFactory $configFactory
	 *   The configuration factory
	 * @param \Drupal\user\UserData $userData
	 *   The user data service
	 * @param \Drupal\force_password_change\Service\ForcePasswordChangeService $forcePasswordChangeService
	 *   The force password change service
	 */
	public function __construct(FormBuilderInterface $formBuilder, DateFormatter $dateFormatter, AccountProxy $currentUser, ConfigFactory $configFactory, UserData $userData, ForcePasswordChangeService $forcePasswordChangeService)
	{
		$this->formBuilder = $formBuilder;
		$this->dateFormatter = $dateFormatter;
		$this->currentUser = $currentUser;
		$this->configFactory = $configFactory;
		$this->userData = $userData;
		$this->forcePasswordChangeService = $forcePasswordChangeService;
	}

	public function adminPage()
	{
		$page = array
		(
			'#prefix' => '<div id="force_password_change_admin_page">',
			'#suffix' => '</div>',
			'form' => $this->formBuilder->getForm('Drupal\force_password_change\Form\AdminForm'),
		);

		return $page;
	}

	public function roleListPage($rid)
	{
		$page = array
		(
			'#prefix' => '<div id="force_password_change_role_page">',
			'#suffix' => '</div>',
		);

		$role = Role::load($rid);
		if($role && $role->id() != 'anonymous')
		{
			// Get a list of users that have a pending forced password change
			$pending_users = $this->forcePasswordChangeService->getPendingUsersForRole($rid);

			// build the header for the table
			$header = array($this->t('Username'), $this->t('Last Force'), $this->t('Last Change'));

			// Next build the rows of the table, and the stats that will be included for each user shown
			$rows = array();
			$force_password_change_installation_date = $this->configFactory->get('force_password_change.settings')->get('installation_date');
			$first_time_uids = $this->forcePasswordChangeService->getFirstTimeLoginUids();
			foreach($pending_users as $pending_user)
			{
				$row = array();

				if($this->currentUser->hasPermission('access user profiles'))
				{
					$url = Url::fromRoute('entity.user.canonical', array('user' => $pending_user->id()));
					$row[] = Link::fromTextAndUrl($pending_user->getDisplayName(), $url);
				}
				else
				{
					$row[] = $pending_user->getDisplayName();
				}

				$last_force_time = $this->userData->get('force_password_change', $pending_user->id(), 'last_force');
				if($last_force_time)
				{
					$last_force = $this->dateFormatter->format($last_force_time, 'short');
				}
				elseif($this->configFactory->get('force_password_change.settings')->get('first_time_login_password_change') && $pending_user->getCreatedTime() > $force_password_change_installation_date)
				{
					$last_force = $this->t('First login');
				}
				else
				{
					if(is_array($first_time_uids) && in_array($pending_user->id(), $first_time_uids))
					{
						$last_force = $this->t('First login');
					}
					else
					{
						$last_force = $this->t('Never');
					}
				}

				$row[] = $last_force;

				$user_change_time = $this->userData->get('force_password_change', $pending_user->id(), 'last_change');
				$row[] = $user_change_time ? $this->dateFormatter->format($user_change_time, 'short') : $this->t('Never');

				$rows[] = $row;
			}

			// Build the table containing the retreived data
			$page['pending_users_table'] = array
			(
				'header' => array
				(
					'#prefix' => '<h2>',
					'#suffix' => '</h2>',
					'#markup' => $this->t('Users in this role with pending password changes'),
				),
				'table' => array
				(
					'#type' => 'table',
					'#header' => $header,
					'#rows' => $rows,
					'#empty' => $this->t('No users found'),
					'#caption' => $this->t('Only active users are shown'),
				),
				'pager' => array
				(
					'#type' => 'pager',
					'#quantity' => 5,
				),
			);

			// Perform the same steps as the previous table, for users who do not have a pending forced password change
			$nonpending_users = $this->forcePasswordChangeService->getNonPendingUsersForRole($rid);
			$header = array($this->t('Username'), $this->t('Last Force'), $this->t('Last Change'));
			$rows = array();
			foreach($nonpending_users as $nonpending_user)
			{
				$row = array();
				if($this->currentUser->hasPermission('access user profiles'))
				{
					$url = Url::fromRoute('entity.user.canonical', array('user' => $nonpending_user->id()));
					$row[] = Link::fromTextAndUrl($nonpending_user->getDisplayName(), $url);
				}
				else
				{
					$row[] = $nonpending_user->getDisplayName();
				}

				$last_force_time = $this->userData->get('force_password_change', $nonpending_user->id(), 'last_force');
				if($last_force_time)
				{
					$last_force = $this->dateFormatter->format($last_force_time, 'short');
				}
				elseif($this->configFactory->get('force_password_change.settings')->get('first_time_login_password_change') && $nonpending_user->getCreatedTime() > $force_password_change_installation_date)
				{
					$last_force = $this->t('First login');
				}
				else
				{
					if(is_array($first_time_uids) && in_array($nonpending_user->id(), $first_time_uids))
					{
						$last_force = $this->t('First login');
					}
					else
					{
						$last_force = $this->t('Never');
					}
				}
				$row[] = $last_force;

				$last_change_time = $this->userData->get('force_password_change', $nonpending_user->id(), 'last_change');
				$row[] = $last_change_time ? $this->dateFormatter->format($last_change_time, 'short') : $this->t('Never');

				$rows[] = $row;
			}

			// Build the table containing the retrieved data
			$page['nonpending_users_table'] = array
			(
				'header' => array
				(
					'#prefix' => '<h2>',
					'#suffix' => '</h2>',
					'#markup' => $this->t('Users in this role without pending password changes'),
				),
				'table' => array
				(
					'#type' => 'table',
					'#header' => $header,
					'#rows' => $rows,
					'#empty' => $this->t('No users found'),
					'#caption' => $this->t('Only active users are shown'),
				),
				'pager' => array
				(
					'#type' => 'pager',
					'#quantity' => 5,
				),
			);

			$page['form'] = $this->formBuilder()->getForm('Drupal\force_password_change\Form\RoleForceForm', $role);
		}
		else
		{
			$page['invalid_role'] = array
			(
				'#prefix' => '<p>',
				'#suffix' => '</p>',
				'#markup' => $this->t('Invalid role'),
			);
		}

		return $page;
	}
}
