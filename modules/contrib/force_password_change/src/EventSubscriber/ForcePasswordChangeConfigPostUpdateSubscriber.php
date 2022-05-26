<?php

namespace Drupal\force_password_change\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * {@inheritdoc}
 */
class ForcePasswordChangeConfigPostUpdateSubscriber implements EventSubscriberInterface {
  /**
   * The config factory object.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The force password change service.
   *
   * @var Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface
   */
  protected $passwordChangeService;

  /**
   * Creates an instance of the ForcePasswordChangeEventSubscriber class.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\force_password_change\Service\ForcePasswordChangeServiceInterface $passwordChangeService
   *   The force password change service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ForcePasswordChangeServiceInterface $passwordChangeService) {
    $this->configFactory = $configFactory;
    $this->passwordChangeService = $passwordChangeService;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['configSave'];
    return $events;
  }

  /**
   * React to a config object being saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   Config crud event.
   */
  public function configSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if ($config->getName() == 'force_password_change.settings') {
      // As an intermediate solution, we will use the
      // force_password_change_expiry table, although we need to get rid of it.
      $old_format_expiry_data = [];
      $expiry_data = $event->getConfig()->get('expiry_data');
      if (is_array($expiry_data)) {
        foreach ($expiry_data as $data) {
          $old_format_expiry_data[$data['rid']] = $data;
        }
        $getRoleExpiryTimePeriods = $this->passwordChangeService->getRoleExpiryTimePeriods();
        if (!empty($getRoleExpiryTimePeriods)) {
          foreach ($getRoleExpiryTimePeriods as $rid_data) {
            if (isset($old_format_expiry_data[$rid_data->rid])) {
              $this->passwordChangeService->updateExpiryForRole(
                $old_format_expiry_data[$rid_data->rid]['rid'],
                $old_format_expiry_data[$rid_data->rid]['expiry'],
                $old_format_expiry_data[$rid_data->rid]['weight']
              );
            }
            else {
              $this->passwordChangeService->insertExpiryForRoles(array_values($old_format_expiry_data));
            }
          }
        }
        else {
          $this->passwordChangeService->insertExpiryForRoles(array_values($old_format_expiry_data));
        }

      }
      \Drupal::messenger()->addStatus('Saved config: ' . $config->getName());
    }
  }

}
