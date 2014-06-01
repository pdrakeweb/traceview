<?php

/*
 * Replace the standard Symfony EventDispatcher with one that reports data to TraceView.
 *
 * This file is a somewhat modified port of the TraceView Symfony bundle: https://packagist.org/packages/appneta/traceview-bundle
 */

namespace Drupal\traceview\EventDispatcher;

use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Evaluate dependency injection container events, wrapped in TraceView events.
 */
class TraceViewContainerAwareEventDispatcher extends ContainerAwareEventDispatcher
{
    public function dispatch($eventName, Event $event = null)
    {
        // On an untraced request, bail out early.
        if (!oboe_is_tracing()) {
            return parent::dispatch($eventName, $event);
        }

        // Store whether this event had any listeners when dispatch started. This
        // prevents broken traces as a result of adding/removing listeners.
        $had_listeners = $this->hasListeners($eventName);

        // Check whether this is a kernel request, response, finish request, or terminate.
        $is_request = ($eventName === "kernel.request");
        $is_response = ($eventName === "kernel.response");
        $is_finish_request = ($eventName === "kernel.finish_request");
        $is_terminate = ($eventName === "kernel.terminate");

        // If this event was being listened to, report a layer or profile entry.
        if ($had_listeners) {
            // On the start of a kernel request, finish request, or terminate, enter a layer.
            if ($is_request) {
                oboe_log(($event->getRequestType() === HttpKernelInterface::MASTER_REQUEST) ? 'HttpKernel.master_request' : 'HttpKernel.sub_request', "entry", array('Event' => get_class($event)), TRUE);
                oboe_log(NULL,"profile_entry", array('Event' => get_class($event), 'ProfileName' => $eventName), TRUE);
            } elseif ($is_finish_request) {
                oboe_log("HttpKernel.finish_request", "entry", array('Event' => get_class($event)), TRUE);
            } elseif ($is_terminate) {
                oboe_log("HttpKernel.terminate", "entry", array('Event' => get_class($event)), TRUE);
            }
            // Otherwise, enter a profile.
            else {
                oboe_log(NULL, "profile_entry", array('Event' => get_class($event), 'ProfileName' => $eventName), TRUE);
            }
        }

        // Try to dispatch the event as normal.
        try {
            $ret = parent::dispatch($eventName, $event);
        // Catch any exceptions that occur during dispatch.
        } catch (\Exception $e) {
            // If this event had listeners that created a profile, exit it.
            if ($had_listeners && !($is_finish_request) && !($is_terminate)) {
                oboe_log(NULL, "profile_exit", array('ProfileName' => $eventName), FALSE);
            }
            // Report the exception via oboe.
            $info = array(
                "ErrorClass" => get_class($e),
                "ErrorMsg" => $e->getMessage(),
                "Backtrace" => $e->getTraceAsString(),
            );
            oboe_log(NULL, "info", $info, FALSE);

            // Then re-raise the exception.
            throw $e;
        }

        // If the event is a kernel controller, report controller/action information.
        if ($eventName === "kernel.controller") {
            oboe_log(NULL, 'info', $this->getControllerAction($ret), FALSE);
        }

        // If this event was being listened to, report a layer or profile exit.
        if ($had_listeners) {
            // On the end of a kernel response, finish response, or terminate, exit a layer.
            if ($is_response) {
                oboe_log(NULL, "profile_exit", array('ProfileName' => $eventName), FALSE);
                oboe_log(($event->getRequestType() === HttpKernelInterface::MASTER_REQUEST) ? 'HttpKernel.master_request' : 'HttpKernel.sub_request', "exit", array(), FALSE);
            } elseif ($is_finish_request) {
                oboe_log("HttpKernel.finish_request", "exit", array(), FALSE);
            } elseif ($is_terminate) {
                oboe_log('HttpKernel.terminate', "exit", array(), FALSE);
            // Otherwise, exit a profile.
            } else {
                oboe_log(NULL, "profile_exit", array('ProfileName' => $eventName), FALSE);
            }
        }

        return $ret;
    }

    public function getControllerAction($event) {
        $event_controller = $event->getController();
        return $this->parseControllerAction($event_controller);
    }

    public function parseControllerAction($event_controller) {
        // We use the same logic as the Symfony debug toolbar to parse out controller/action.
        if (is_array($event_controller)) {
            $controller = $event_controller[0];
            $action = $event_controller[1];
        } elseif ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($event_controller);
            $controller = $r->getName();
            $action = NULL;
        } elseif (is_string($event_controller) && strpos($event_controller, '::') !== FALSE) {
            $ca = explode("::", $event_controller);
            $controller = $ca[0];
            $action = $ca[1];
        } else {
            $controller = (string) $event_controller ? : NULL;
            $action = NULL;
        }

        // Proxy controller for a full HTML page. Re-parse the underlying controller.
        if (is_a($controller, 'Drupal\Core\Controller\HtmlPageController')) {
            return $this->parseControllerAction(\Drupal::request()->attributes->get("_content"));
        }

        // Proxy controller for an HTML form. Use the matching form
        // (e.g., 'Drupal\system\Form\ModulesListForm') as the action.
        if (is_a($controller, 'Drupal\Core\Controller\HtmlFormController')) {
            $controller = 'HtmlFormController';
            $action = \Drupal::request()->attributes->get("_form");
        }

        // Proxy class for an HTML entity form. Use the matching entity form
        // (e.g., 'node.edit') as the action.
        if (is_a($controller, 'Drupal\Core\Entity\HtmlEntityFormController')) {
            $controller = 'HtmlEntityFormController';
            $action = \Drupal::request()->attributes->get("_entity_form");
        }

        // Proxy class for a View. Use the matching View ID and display ID.
        if ($controller === 'Drupal\views\Routing\ViewPageController') {
            $controller = 'ViewPageController::'.\Drupal::request()->attributes->get('view_id');
            $action = \Drupal::request()->attributes->get('display_id');
        }

        return array("Controller" => (is_object($controller)) ? get_class($controller) : $controller, "Action" => (is_object($action)) ? get_class($action) : $action);
    }
}
