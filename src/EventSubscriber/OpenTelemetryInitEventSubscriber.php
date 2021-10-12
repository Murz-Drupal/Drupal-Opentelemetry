<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class OpenTelemetryInitEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {

    $events = [];
    // $events[KernelEvents::CONTROLLER][] = ['onLoad'];

    return $events;
  }

  public function onLoad(ControllerEvent $event) {
  }

}