<?php

namespace Drupal\opentelemetry\Exception;

use Drupal\Core\Extension\MissingDependencyException;

/**
 * Exception when a class dependency is missing.
 */
class MissingClassDependencyException extends MissingDependencyException {

  public function __construct(
    protected string $name,
    protected string $composerPackage,
  ) {
    $message = "Missing class dependency: {$name} from the composer package \"{$composerPackage}\". Check if all composer dependencies for the module \"opentelemetry\" are installed successfully.";
    parent::__construct($message);
  }

}
