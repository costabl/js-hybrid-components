<?php

namespace Drupal\webform_api_handler\Plugin\WebformAPIHandler\Request;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformTokenManager;
use Drupal\webform_api_handler\Plugin\RequestPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class TokenizedRequest.
 *
 * @package Drupal\webform_api_handler\Plugin\WebformAPIHandler\Request
 *
 * @Request(
 *   id = "tokenized_request",
 *   label = "Tokenized API Request",
 * )
 */
class TokenizedRequest extends RequestPluginBase {

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, ConfigFactoryInterface $config_factory, WebformTokenManager $token_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $string_translation, $config_factory);
    $this->tokenManager = $token_manager;
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
      $container->get('config.factory'),
      $container->get('webform.token_manager')
    );
  }

  /**
   * The request plugin settings form.
   */
  public function settingsForm(&$form, FormStateInterface $form_state, $webform = NULL) {
    $config = $this->getConfig();
    $settings = [];

    if (!empty($config) && isset($webform)) {
      $settings = $config->get($webform);
    }

    $form['webform_api_handler']['request'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tokenized Request Settings'),
      '#tree' => TRUE,
    ];
    $form['webform_api_handler']['request']['tokenized_request'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tokenized Request'),
      '#description' => $this->t('Use this field to layout the tokenized data to send to the API endpoint. Values from the submission will replace their tokens prior to sending the request.'),
      '#default_value' => !empty($settings) ? $settings['tokenized_request'] : '',
    ];

    // Tokens.
    $form['webform_api_handler']['request']['token_tree_link'] = $this->buildTokenTreeElement();
  }

  /**
   * Process order data.
   */
  public function processRequest($data, WebformSubmission $submission, Webform $webform) {
    // Get config for this plugin and webform.
    $config = $this->getConfig();
    if (!empty($config) && isset($webform)) {
      $settings = $config->get($webform->id());
    }
    $processed_submission = $this->replaceTokens($settings['tokenized_request'], $submission);
    return ['body' => $processed_submission];
  }

  /**
   * Replace tokens in text with no render context.
   *
   * @param string|array $text
   *   A string of text that may contain tokens.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   A Webform or Webform submission entity.
   * @param array $data
   *   (optional) An array of keyed objects.
   * @param array $options
   *   (optional) A keyed array of settings and flags to control the token
   *   replacement process. Supported options are:
   *   - langcode: A language code to be used when generating locale-sensitive
   *     tokens.
   *   - callback: A callback function that will be used to post-process the
   *     array of token replacements after they are generated.
   *   - clear: A boolean flag indicating that tokens should be removed from the
   *     final text if no replacement value can be generated.
   *
   * @return string|array
   *   Text or array with tokens replaced.
   */
  protected function replaceTokens($text, EntityInterface $entity = NULL, array $data = [], array $options = []) {
    return $this->tokenManager->replaceNoRenderContext($text, $entity, $data, $options);
  }

  /**
   * Build token tree element.
   *
   * @param array $token_types
   *   (optional) An array containing token types that should be shown in the tree.
   * @param string $description
   *   (optional) Description to appear after the token tree link.
   *
   * @return array
   *   A render array containing a token tree link wrapped in a div.
   */
  protected function buildTokenTreeElement(array $token_types = ['webform', 'webform_submission'], $description = NULL) {
    return $this->tokenManager->buildTreeElement($token_types, $description);
  }

  /**
   * Validate form that should have tokens in it.
   *
   * @param array $form
   *   A form.
   * @param array $token_types
   *   An array containing token types that should be validated.
   *
   * @see token_element_validate()
   */
  protected function elementTokenValidate(array &$form, array $token_types = ['webform', 'webform_submission', 'webform_handler']) {
    return $this->tokenManager->elementValidate($form, $token_types);
  }
}
