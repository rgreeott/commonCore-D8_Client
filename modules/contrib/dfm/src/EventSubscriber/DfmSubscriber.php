<?php

namespace Drupal\dfm\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\dfm\Dfm;

class DfmSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['requestInit'];
    return $events;
  }

  /**
   * Performs operations before the request is processed.
   */
  public function requestInit(GetResponseEvent $event) {
    // Allow image derivatives temporarily.
    $request = $event->getRequest();
    $itok = $request->query->get('dfm_itok');
    if ($itok && $request->query->get('file')) {
      $path = $request->getPathInfo();
      if (!\Drupal::config('image.settings')->get('allow_insecure_derivatives') && $pos = strpos($path, '/styles/')) {
        $args = explode('/', substr($path, $pos + 8));
        if ($itok === Dfm::itok($args[0])) {
          $GLOBALS['config']['image.settings']['allow_insecure_derivatives'] = TRUE;
          // Reset the config to rebuild overrides.
          \Drupal::configFactory()->reset('image.settings');
        }
      }
    }
  }

}
?>