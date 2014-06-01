<?php

/**
 * @file
 * Contains Drupal\traceview\EventSubscriber\Partition.
 */

namespace Drupal\traceview\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A subscriber that repots additional data to TraceView when a request terminates.
 */
class Partition implements EventSubscriberInterface {

  /**
   * Report additional data about the request to TraceView.
   *
   * @param Symfony\Component\HttpKernel\Event\PostResponseEvent $event
   *   The Event to process.
   */
  public function onTerminate(PostResponseEvent $event) {
    $config = \Drupal::config('traceview.settings');

    // Partition traffic.
    if ($config->get('partition.track', FALSE)) {
      $traceview_partition = traceview_set_partition();
      if (!empty($traceview_partition)) {
        traceview_oboe_log(NULL, 'info', array('Partition' => $traceview_partition));
      }
      else {
        switch (\Drupal::currentUser()->id()) {
          case 1:
            traceview_oboe_log(NULL, 'info', array('Partition' => 'Admin'));
            break;

          case 0:
            traceview_oboe_log(NULL, 'info', array('Partition' => 'Anonymous'));
            break;

          default:
            traceview_oboe_log(NULL, 'info', array('Partition' => 'Authenticated'));
            break;
        }
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::TERMINATE][] = array('onTerminate', -255);

    return $events;
  }

}