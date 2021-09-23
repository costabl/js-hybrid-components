<?php

namespace Drupal\webform_api_handler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Request Handler plugin for Webform API Handler.
 *
 * @see \Drupal\webform_api_handler\Plugin\RequestManager
 * @see \Drupal\webform_api_handler\Plugin\RequestInterface
 *
 * @ingroup webform_api_handler
 *
 * @Annotation
 */
class Request extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the request handler.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * A short description of the request handler.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * The name of the request handler class to use.
   *
   * @var string
   */
  public $class;

}