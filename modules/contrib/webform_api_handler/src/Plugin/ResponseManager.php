<?php

namespace Drupal\webform_api_handler\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a Webform API Handler Response plugin manager.
 *
 * @see \Drupal\webform_api_handler\Annotation\Response
 * @see \Drupal\webform_api_handler\Plugin\ResponseInterface
 * @see plugin_api
 */
class ResponseManager extends DefaultPluginManager {
  /**
   * Constructs a ResponseManager object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/WebformAPIHandler/Response',
      $namespaces,
      $module_handler,
      'Drupal\webform_api_handler\Plugin\ResponseInterface',
      'Drupal\webform_api_handler\Annotation\Response'
    );
    $this->alterInfo('response_info');
    $this->setCacheBackend($cache_backend, 'webform_api_handler-response_info_plugins');
  }
}