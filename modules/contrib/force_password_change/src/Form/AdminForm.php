<?php

namespace Drupal\force_password_change\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdminForm extends FormBase
{
	/**
	 * The config factory object
	 *
	 * @var Drupal\Core\Config\ConfigFactory
	 */
	protected $configFactory;

	/**
	 * The force password change service
	 *
	 * @var Drupal\force_password_change\Service\ForcePasswordChangeService
	 */
	protected $passwordChangeService;

	/**
	 * Constructs an AdminForm object.
	 *
	 * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
	 *   The config factory service.
	 * @param \Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface $forcePasswordChangeService
	 *   The Force Password Change service
	 */
	public function __construct(ConfigFactoryInterface $configFactory, ForcePasswordChangeServiceInterface $passwordChangeService)
	{
		$this->configFactory = $configFactory;
		$this->passwordChangeService = $passwordChangeService;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container)
	{
		return new static (
			$container->get('config.factory'),
			$container->get('force_password_change.service')
		);
	}

	public function getFormId()
	{
		return 'force_password_change_admin_form';
	}

	public function buildForm(array $form, FormStateInterface $form_state)
	{
		$form['#attached']['library'] = [
			'force_password_change/settings_page',
		];

		$form['first_time_login_password_change'] = [
			'#type' => 'checkbox',
			'#title' => $this->t('Force password change on first-time login'),
			'#default_value' => $this->configFactory->get('force_password_change.settings')->get('first_time_login_password_change'),
		];

		$form['login_only'] = [
			'#type' => 'radios',
			'#title' => $this->t('Check for pending password change'),
			'#options' => [0 => $this->t('On every page load'), 1 => $this->t('On login only')],
			'#default_value' => (int) $this->configFactory->get('force_password_change.settings')->get('check_login_only'),
			'#description' => $this->t('Select when to check if a user has a pending password change. Checking on every page load adds a little overhead to every page load, but is the most secure method. Checking on login will only check if a change is pending when the user first logs in, but on sites where users may stay logged in for lengthy periods of time, it may be a long time before they are forced to change their password.'),
		];

		$roles = [];
		// Get stats for each of the roles on the site
		$all_roles = Role::loadMultiple();
		unset($all_roles[RoleInterface::ANONYMOUS_ID]);
		foreach($all_roles as $rid => $role)
		{
			$user_count = $this->passwordChangeService->getUserCountForRole($rid);
			$pending_count = $this->passwordChangeService->getPendingUsersForRole($rid, TRUE);

			$url = Url::fromRoute('force_password_change.admin.role.list', ['rid' => $rid]);
			$roles[$rid] = $this->t(
				'@role (Users in role: @user_count | Users with pending forced password change: @pending_user_count | <a href=":url">Details</a>)',
				[
					'@role' => $role->label(),
					'@user_count' => $user_count,
					'@pending_user_count' => $pending_count,
					':url' =>$url->toString(),
				]
			);
		}

		$form['roles'] = [
			'#type' => 'checkboxes',
			'#options' => $roles,
			'#title' => $this->t('Force users in the following roles to change their password'),
			'#description' => $this->t('Users will be forced to change their password either on their next page load, or on their next login, depending on the setting in "Check for pending password change". If pending password changes are checked on every page load, logged in users will be forced to immediately change their password, and after changing it will be redirected back to the page they were attempting to access.') . '<br />' . $this->t('Note: When you return to this page, no roles will be selected. This is because this setting is a trigger, not a persistant state.'),
		];

		$expiry_data = $this->passwordChangeService->getRoleExpiryTimePeriods();
		$expiry = [];
		foreach($expiry_data  as $data)
		{
			$expiry[$data->rid] = [
				'expiry' => $data->expiry,
				'weight' => $data->weight,
			];
		}

		$form['expiry_data'] = [
			'#type' => 'value',
			'#value' => $expiry,
		];

		$form['expiry'] = [
			'#type' => 'fieldset',
			'#title' => $this->t('Password Expiry'),
			'#collapsible' => TRUE,
		];

		$form['expiry']['expire_password'] = [
			'#type' => 'checkbox',
			'#title' => $this->t('Enable password expiration'),
			'#default_value' => $this->configFactory->get('force_password_change.settings')->get('expire_password'),
			'#description' => $this->t('When this box is checked, passwords will be set to expire according to the rules set out below. If this box is un-checked, password expiry will be disabled, and the password expiry options below will be ignored.'),
		];

		$form['expiry']['header'] = [
			'#markup' => '<p>' . $this->t('Select the amount of time after which you would like users in a role to be automatically forced to change their password. Any users who do not change their password in this amount of time will be forced to change their password on their next login or page load (depending on the setting in "Check for pending password change"). If you do not wish passwords to expire for a certain role, leave/set the value for that role to zero.') . '</p>',
		];

		$form['expiry']['table'] = [
			'#tree' => TRUE,
			'#type' => 'table',
			'#header' => [$this->t('Role'), $this->t('Expire password after:'), $this->t('Weight')],
			'#id' => 'force_password_change_role_expiry_table',
			'#tabledrag' => [
				[
					'action' => 'order',
					'relationship' => 'sibling',
					'group' => 'role-expiry-order-weight',
				],
			],
		];

		$time_periods = [
			'hour' => 60 * 60,
			'day' => 60 * 60 * 24,
			'week' => 60 * 60 * 24 * 7,
			'year' => 60 * 60 * 24 * 365,
		];

		$time_options = [
			'hour' => $this->t('hours'),
			'day' => $this->t('days'),
			'week' => $this->t('weeks'),
			'year' => $this->t('years'),
		];

		$heaviest_weight = 0;
		if(count($expiry))
		{
			foreach($expiry as $rid => $data)
			{
				if(isset($all_roles[$rid]))
				{
					$form['expiry']['table'][$rid] = [
						'#weight' => $data['weight'],
						'#attributes' => ['class' => ['draggable']],
					];

					$form['expiry']['table'][$rid]['role'] = [
						'#plain_text' => $all_roles[$rid]->label(),
					];

					$time_period_default = 0;
					$time_quantity_default = 0;
					if($data['expiry'] != '' && $data['expiry'])
					{
						$expires = $data['expiry'];
						if($expires % $time_periods['year'] === 0)
						{
							$time_period_default = 'year';
							$time_quantity_default = $expires / $time_periods['year'];
						}
						else
						{
							if($expires % $time_periods['week'] === 0)
							{
								$time_period_default = 'week';
								$time_quantity_default = $expires / $time_periods['week'];
							}
							else
							{
								if($expires % $time_periods['day'] === 0)
								{
									$time_period_default = 'day';
									$time_quantity_default = $expires / $time_periods['day'];
								}
								else
								{
									$time_period_default = 'hour';
									if($expires % $time_periods['hour'] === 0)
									{
										$time_quantity_default = $expires / $time_periods['hour'];
									}
								}
							}
						}
					}

					$form['expiry']['table'][$rid]['time']['time_quantity'] = [
						'#type' => 'textfield',
						'#default_value' => $time_quantity_default,
						'#size' => 4,
					];

					$form['expiry']['table'][$rid]['time']['time_period'] = [
						'#type' => 'select',
						'#options' => $time_options,
						'#default_value' => $time_period_default,
					];

					$form['expiry']['table'][$rid]['weight'] = [
						'#type' => 'weight',
						'#title' => $this->t('Weight for expiry roles'),
						'#title_display' => 'invisible',
						'#default_value' => ($data['weight'] != '') ? $data['weight'] : 0,
						'#attributes' => ['class' => ['role-expiry-order-weight']],
					];

					$heaviest_weight = ($data['weight'] != '') ? $data['weight'] : 0;
				}
			}
		}

		foreach($roles as $rid => $r)
		{
			if(!isset($form['expiry']['table'][$rid]))
			{
				$heaviest_weight++;
				$form['expiry']['table'][$rid] = [
					'#weight' => $heaviest_weight,
					'#attributes' => ['class' => ['draggable']],
				];

				$form['expiry']['table'][$rid]['role'] = [
					'#markup' => $r,
				];

				$form['expiry']['table'][$rid]['time']['time_quantity'] = [
					'#type' => 'textfield',
					'#default_value' => 0,
					'#size' => 4,
				];

				$form['expiry']['table'][$rid]['time']['time_period'] = [
					'#type' => 'select',
					'#options' => $time_options,
				];

				$form['expiry']['table'][$rid]['weight'] = [
					'#type' => 'weight',
					'#title' => $this->t('Weight for expiry roles'),
					'#title_display' => 'invisible',
					'#default_value' => $heaviest_weight,
					'#attributes' => ['class' => ['role-expiry-order-weight']],
				];
			}
		}

		$form['expiry']['footer'] = [
			'#markup' => '<p>' . $this->t('Drag and drop the rows to set the priority for password expiry. The roles with the highest priority should be placed at the top of the list. If a user is a member of more than one role, then the time after which their password expires will be determined by whichever of their roles has the highest priority (highest in the list). Expiry rules for any roles of lower priority (lower in the list) will be ignored. As such, any roles lower in priority (below) the authenticated user role will effectively be ignored, since all users are members of the authenticated users role.') . '</p>',
		];

		$form['actions'] = [
			'#type' => 'actions',
		];

		$form['actions']['submit'] = [
			'#type' => 'submit',
			'#value' => $this->t('Submit'),
		];

		return $form;
	}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		// First set some variable defaults
		$this->configFactory->getEditable('force_password_change.settings')
			->set('first_time_login_password_change', (bool) $form_state->getValue('first_time_login_password_change'))
			->set('check_login_only', (bool) $form_state->getValue('login_only'))
			->save();

		$selected_roles = [];
		$au = FALSE;
		foreach($form_state->getValue('roles') as $rid)
		{
			// The authenticated user role. All users on the site will have a password change forced
			if($rid === 'authenticated')
			{
				// Update all user's {user} table
				$this->passwordChangeService->forceUsersPasswordChange();
				$selected_roles[] = $rid;
				// Since all users on the site have had their password change forced,
				// no other queries need to be run on the users table. However, we do want
				// to log the rid for each role. In order to set up this functionality,
				// a flag is set indicating that the authenticated users role was selected.
				$au = TRUE;
			}
			// Selected roles other than authenticated user
			elseif($rid)
			{
				$selected_roles[] = $rid;
				// Only execute the following code if the authenticated users role was not selected
				if(!$au)
				{
					// Get a list of UIDs for the users in that role
					$uids = $this->passwordChangeService->getUsersForRole($rid, TRUE);
					// If the role has any users, force them to change their password
					if(count($uids))
					{
						$this->passwordChangeService->forceUsersPasswordChange($uids);
					}
				}
			}
		}

		// If any roles have had a forced password change, enter the following conditional
		if(count($selected_roles))
		{
			// Log the time of the force for the role
			$this->passwordChangeService->updateLastChangeForRoles($selected_roles);

			// Build an list of the names of the roles that had their password change forced
			$roles = Role::loadMultiple();
			unset($roles[RoleInterface::ANONYMOUS_ID]);

			$items = [];
			foreach($selected_roles as $sr)
			{
				$items[] = $roles[$sr]->label();
			}

			$list = [
				'#theme' => 'item_list',
				'#items' => $items,
			];

			$item_list = new FormattableMarkup('@item_list', ['@item_list' => render($list)]);
			// Set a message informing the user of the roles that had a forced password change
			if($form_state->getValue('login_only'))
			{
				drupal_set_message($this->t('Users in the following roles will be required to change their password on their next login: @roles', ['@roles' => $item_list]), 'status');
			}
			else
			{
				drupal_set_message($this->t('Users in the following roles will be required to immediately change their password: @roles', ['@roles' => $item_list]), 'status');
			}
		}

		$this->configFactory->getEditable('force_password_change.settings')
			->set('expire_password', (bool) $form_state->getValue('expire_password'))
			->save();

		$insert_roles = [];
		// Loop through the roles and either update their row in the expiry table, or add to the
		// insert query
		$time_periods = [
			'hour' => 60 * 60,
			'day' => 60 * 60 * 24,
			'week' => 60 * 60 * 24 * 7,
			'year' => 60 * 60 * 24 * 365,
		];

		foreach($form_state->getValue('table') as $rid => $expiry)
		{
			// Convert the selected time period into a UNIX timestamp
			// that will be used to calculate whether or not the password has expired.
			$time_period = $expiry['time']['time_quantity'] * $time_periods[$expiry['time']['time_period']];
			// If the role already exists in the database, and the value has changed,
			if($form_state->getValue(['expiry_data', $rid])
			  && ($time_period != $form_state->getValue(['expiry_data', $rid, 'expiry'])
			  || $expiry['weight'] != $form_state->getValue(['expiry_data', $rid, 'weight'])))
			{
				$this->passwordChangeService->updateExpiryForRole($rid, $time_period, $expiry['weight']);
			}
			// If the role doesn't have a row in the database, add to the insert query
			elseif(!$form_state->getValue(['expiry_data', $rid]))
			{
				$insert_roles[] = [
					'rid' => $rid,
					'expiry' => $time_period,
					'weight' => $expiry['weight'],
				];
			}
		}

		// Execute the query only if new roles were found
		if(count($insert_roles))
		{
			$this->passwordChangeService->insertExpiryForRoles($insert_roles);
		}
	}
}
