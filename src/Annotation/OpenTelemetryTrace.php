<?php

namespace Drupal\opentelemetry\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a OpenTelemetryTrace plugin annotation.
 *
 * @Annotation
 */
class OpenTelemetryTrace extends Plugin {

  /**
   * The OpenTelemetry tracking event ID, in machine name format.
   *
   * @var string
   */
  public $id;

  /**
   * The display label/name of the event plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A longer explanation of what this event tracks.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
