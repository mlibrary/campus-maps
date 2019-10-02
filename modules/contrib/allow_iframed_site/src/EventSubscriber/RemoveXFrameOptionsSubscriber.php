<?php

namespace Drupal\allow_iframed_site\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * An event subscriber to remove the X-Frame-Options header.
 */
class RemoveXFrameOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Remove the X-Frame-Options header.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event object.
   */
  public function RemoveXFrameOptions(FilterResponseEvent $event) {
    $response = $event->getResponse();
    $response->headers->remove('X-Frame-Options');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = array('RemoveXFrameOptions', -10);
    return $events;
  }

}
