<?php

namespace Drupal\allow_iframed_site\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;

/**
 * An event subscriber to remove the X-Frame-Options header.
 */
class RemoveXFrameOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Array of Config options.
   *
   * @var array $config
   */
  protected $config;

  /**
   * Condition manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $conditionManager;

  /**
   * RemoveXFrameOptionsSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactory.
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $condition_manager
   *   ConditionManager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ExecutableManagerInterface $condition_manager) {
    $this->config = $config_factory->get('allow_iframed_site.settings');
    $this->conditionManager = $condition_manager;
  }

  /**
   * Remove the X-Frame-Options header.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function RemoveXFrameOptions(FilterResponseEvent $event) {
    $xframe = TRUE;
    /**
     * Don't remove X-Frame-Options if pages and negate fields are not configured.
     */
    $rowData = $this->config->getRawData();
    if(!empty($rowData)) {
      if(empty($rowData['request_path']['pages']) && $rowData['request_path']['negate'] == 0) {
        $xframe = FALSE;
      }
      else {
        foreach ($this->config->getRawData() as $key => $config) {
          try {
            /* @var \Drupal\system\Plugin\Condition\RequestPath $condition */
            $condition = $this->conditionManager->createInstance($key);
            $condition->setConfiguration($this->config->get($key));
            if (
                ($condition->evaluate() && $condition->isNegated()) ||
                (!$condition->evaluate() && !$condition->isNegated())
              ) {
                $xframe = FALSE;
            }
          } catch (PluginException $exception) {
            // Just ignore it, there's probably not much else to do.
          }
        }
      }
    }

    // If we got here we should be fine, but check it anyway.
    if ($xframe) {
      $response = $event->getResponse();
      $response->headers->remove('X-Frame-Options');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['RemoveXFrameOptions', -10];
    return $events;
  }

}
