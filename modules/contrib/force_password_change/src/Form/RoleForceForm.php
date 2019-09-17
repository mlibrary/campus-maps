<?php

namespace Drupal\force_password_change\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RoleForceForm extends FormBase
{
	/**
	 * The config factory object
	 *
	 * @var Drupal\Core\Config\ConfigFactoryInterface
	 */
	protected $configFactory;

	/**
	 * The force password change service
	 *
	 * @var Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface
	 */
	protected $passwordChangeService;

	/**
	 * The date formatter service
	 *
	 * @var Drupal\Core\Datetime\DateFormatterInterface
	 */
	protected $dateFormatter;

	/**
	 * Constructs an AdminForm object.
	 *
	 * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
	 *   The config factory service.
	 * @param Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface $passwordChangeService
	 *   The force password change service
	 * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
	 *   The date formatter service
	 */
	public function __construct(ConfigFactoryInterface $configFactory, DateFormatterInterface $dateFormatter, ForcePasswordChangeServiceInterface $passwordChangeService)
	{
		$this->configFactory = $configFactory;
		$this->dateFormatter = $dateFormatter;
		$this->passwordChangeService = $passwordChangeService;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container)
	{
		return new static (
			$container->get('config.factory'),
			$container->get('date.formatter'),
			$container->get('force_password_change.service')
		);
	}

	public function getFormId()
	{
		return 'force_password_change_role_admin_form';
	}

	public function buildForm(array $form, FormStateInterface $form_state, RoleInterface $role = NULL)
	{
		if($role)
		{
			if($this->configFactory->get('force_password_change.settings')->get('check_login_only'))
			{
				$description = $this->t('Users will be required to change their password upon their next login.');
			}
			else
			{
				$description = $this->t('Users who are not signed in will be required to change their password immediately upon login. Users who are currently signed in will be required to change their password upon their next page click, but after changing their password will be redirected back to the page they were attempting to access.');
			}
			$description .= '<br />' . $this->t('Note: When you return to this page, this box will be unchecked. This is because this setting is a trigger, not a persistant state.');

			$form['force_password_change'] = [
				'#type' => 'checkbox',
				'#title' => $this->t('Force users in this role to change their password'),
				'#description' => $description,
				'#weight' => -1,
			];

			$form['role'] = [
				'#type' => 'value',
				'#value' => $role,
			];

			$form['actions'] = [
				'#type' => 'actions',
			];

			$form['actions']['submit'] = [
				'#type' => 'submit',
				'#value' => $this->t('Force Password Change'),
			];
		}
		else
		{
			$form['no_role'] = [
				'#prefix' => '<p>',
				'#suffix' => '</p>',
				'#markup' => $this->t('No role supplied'),
			];
		}

		return $form;
	}

	public function submitForm(array &$form, FormStateInterface $form_state)
	{
		// Only execute the code if the checkbox was selected
		if($form_state->getValue('force_password_change'))
		{
			$role = $form_state->getValue('role');

			// If the role is the authenticated users role, force the change for
			// for all users
			if($role->id() == 'authenticated')
			{
				$this->passwordChangeService->forceUsersPasswordChange();
			}
			// Execute the following code if the role is anything other than
			// the authenticated users role
			else
			{
				// Get all UIDS for all members of the role
				$uids = $this->passwordChangeService->getUsersForRole($role->id());

				// If any users are found, force their password change
				if(count($uids))
				{
					$this->passwordChangeService->forceUsersPasswordChange($uids);
				}
			}

			// Log the force time for the role for statistics sake
			$this->passwordChangeService->updateLastChangeForRoles([$role->id()]);

			// Set a message depending on the site settings
			if($this->configFactory->get('force_password_change.settings')->get('check_login_only'))
			{
				drupal_set_message($this->t('Users in this role will be required to change their password on next login'));
			}
			else
			{
				drupal_set_message($this->t('Users in this role will be required to immediately change their password'));
			}
		}
	}
}
