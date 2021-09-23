<?php

namespace Drupal\webform_api_handler\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Base class for Webform API Handler Request plugins.
 */
abstract class RequestPluginBase extends PluginBase implements RequestInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $string_translation;
    $this->config = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('config.factory')
    );
  }

  /**
   * Default settings.
   */
  public function defaultSettings() {
    return [];
  }

  /**
   * Default settings form.
   */
  public function settingsForm(&$form, FormStateInterface $form_state, $webform = NULL) {
    // Provide a settings form.
  }

  /**
   * Submit method for the process plugin settings form.
   */
  public function settingsSubmit(&$form, FormStateInterface $form_state, $webform) {
    $settings = $form_state->getValue('webform_api_handler')['request'];
    $this->setConfig($webform, $settings);
  }

  /**
   * Get the plugin configuration.
   */
  public function getConfig() {
    $config = $this->config->get('webform_api_handler.request');
    return $config;
  }

  /**
   * Set the plugin configuration.
   */
  public function setConfig($webform, $data) {
    $config = $this->config->getEditable('webform_api_handler.request');
    $config->set($webform, $data)
      ->save();
  }

  /**
   * Process request prior to submission.
   */
  public function processRequest($data = [], WebformSubmission $submission, Webform $webform) {
    // Process the webform submission and perform token replacement.
  }

}
