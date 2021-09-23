<?php

namespace Drupal\webform_api_handler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Response Handler plugin for Webform API Handler.
 *
 * @see \Drupal\webform_api_handler\Plugin\ResponseManager
 * @see \Drupal\webform_api_handler\Plugin\ResponseInterface
 *
 * @ingroup webform_api_handler
 *
 * @Annotation
 */
class Response extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the response handler.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * A short description of the response handler.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * The name of the response handler class to use.
   *
   * @var string
   */
  public $class;

}