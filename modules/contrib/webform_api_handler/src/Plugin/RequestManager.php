<?php

namespace Drupal\webform_api_handler\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a Webform API Handler Request plugin manager.
 *
 * @see \Drupal\webform_api_handler\Annotation\Request
 * @see \Drupal\webform_api_handler\Plugin\RequestInterface
 * @see plugin_api
 */
class RequestManager extends DefaultPluginManager {
  /**
   * Constructs a RequestManager object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/WebformAPIHandler/Request',
      $namespaces,
      $module_handler,
      'Drupal\webform_api_handler\Plugin\RequestInterface',
      'Drupal\webform_api_handler\Annotation\Request'
    );
    $this->alterInfo('request_info');
    $this->setCacheBackend($cache_backend, 'webform_api_handler-request_info_plugins');
  }
}