<?php

namespace Drupal\force_password_change\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ForcePasswordChangeEventSubscriber implements EventSubscriberInterface
{
	use RedirectDestinationTrait;

	/**
	 * The config factory object
	 *
	 * @var Drupal\Core\Config\ConfigFactoryInterface
	 */
	protected $configFactory;

	/**
	 * The current user
	 *
	 * @var Drupal\Core\Session\AccountProxyInterface
	 */
	protected $currentUser;

	/**
	 * The current path
	 *
	 * @var Drupal\Core\Path\CurrentPathStack
	 */
	protected $currentPath;

	/**
	 * The force password change service
	 *
	 * @var Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface
	 */
	protected $passwordChangeService;

	/**
	 * Creates an instance of the ForcePasswordChangeEventSubscriber class
	 *
	 * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
	 *   The current user
	 * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
	 *   The config factory service.
	 * @param \Drupal\Core\Path\CurrentPathStack $currentPath
	 *   The current path
	 * @param \Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface $passwordChangeService
	 *   The force password change service
	 */
	public function __construct(AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory, CurrentPathStack $currentPath, ForcePasswordChangeServiceInterface $passwordChangeService)
	{
		$this->currentUser = $currentUser;
		$this->configFactory = $configFactory;
		$this->currentPath = $currentPath;
		$this->passwordChangeService = $passwordChangeService;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		// On page load, we need to check for whether the user should be redirected
		// to the change password page
		$events[KernelEvents::REQUEST][] = ['checkForPasswordForce'];

		return $events;
	}

	/**
	 * This function is called on each page load. It checks two things:
	 *
	 * 1) Whether the user's account has been flagged to change their password
	 * 2) Whether their password has expired
	 * 
	 * If either of the two conditions above returns true, they are redirected to the change password page
	 */
	public function checkForPasswordForce(GetResponseEvent $event)
	{
		// Only do something if the module is set to be enabled. This can only be changed
		// in settings.php, and is added to allow users to disable the module if a problem arises.
		if($this->configFactory->get('force_password_change.settings')->get('enabled'))
		{
			// Check whether force password testing is done on every page, or only on login.
			// We only need to do the following code if the user is logged in, and we are 
			// checking on every page load.
			if($this->currentUser->id() && !$this->configFactory->get('force_password_change.settings')->get('check_login_only'))
			{
				// We should check for AJAX, but the request isn't. Sniffing headers isn't a
				// perfect method, but this header should be present much of the time.
				$ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && !$_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest';

				// Default is to not redirect
				$redirect = FALSE;

				/**
				 * Redirects should only happen if:
				 * - not in an ajax callback
				 * - not on the change password page
				 * - not logging out
				 * - not requesting a new password
				 */
				if(!$ajax_request && !in_array($this->currentPath->getPath(), ['/user/' . $this->currentUser->id() . '/edit', '/user/logout', '/user/password', '/unmasquerade']))
				{
					$time_period = $this->passwordChangeService->checkForForce();
					if($time_period)
					{
						$redirect = 'admin_forced';
					}
				}

				if($redirect)
				{
					// The user is redirected. A message needs to be set informing them of the reason
					// for being redirected to the password change page.
					if($redirect == 'admin_forced')
					{
						drupal_set_message(t('An administrator has required that you change your password. Please change your password to proceed.'), 'warning', FALSE);
					}
					else
					{
						$time_period = $this->passwordChangeService->getTextDate($time_period);
						drupal_set_message(t('This site requires that you change your password every @time_period. Please change your password to proceed.', ['@time_period' => $time_period]));
					}

					// Redirect the user to the change password page
					$url = Url::fromRoute('entity.user.edit_form', ['user' => $this->currentUser->id()], ['query' => $this->getDestinationArray()]);

					$event->setResponse(new RedirectResponse($url->toString()));
				}
			}
		}
	}
}
