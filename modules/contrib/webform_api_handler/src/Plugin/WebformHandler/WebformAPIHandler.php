<?php

namespace Drupal\webform_api_handler\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Plugin\WebformElement\WebformManagedFileBase;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Plugin\WebformHandler\RemotePostWebformHandler;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformMessageManagerInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\webform_api_handler\Plugin\RequestManager;
use Drupal\webform_api_handler\Plugin\ResponseManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission remote post handler.
 *
 * @WebformHandler(
 *   id = "webform_api_handler",
 *   label = @Translation("Remote Post with Custom Handlers"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to a URL and passes response to a response handler."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class WebformAPIHandler extends RemotePostWebformHandler {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * A webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * Request Manager.
   *
   * @var \Drupal\webform_api_handler\Plugin\RequestManager
   */
  protected $requestManager;

  /**
   * Response Manager.
   *
   * @var \Drupal\webform_api_handler\Plugin\ResponseManager
   */
  protected $responseManager;

  /**
   * List of unsupported webform submission properties.
   *
   * The below properties will not being included in a remote post.
   *
   * @var array
   */
  protected $unsupportedProperties = [
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requestManager = $container->get('plugin.manager.webform_api_handler.request');
    $instance->responseManager = $container->get('plugin.manager.webform_api_handler.response');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'request_plugin' => '',
      'response_plugin' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $webform = $this->getWebform();

    $request_plugins = $this->requestManager->getDefinitions();
    $request_options = [NULL => $this->t('Select one')];
    foreach ($request_plugins as $plugin) {
      $request_options[$plugin['id']] = $plugin['label'];
    }

    $ajax_wrapper = 'request-plugin-ajax-wrapper';
    $form['request_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Request plugin'),
      '#description' => $this->t('Select a request plugin. Request plugins are used to manipulate form data into a format compatible with the target API before a request is submitted.'),
      '#options' => $request_options,
      '#default_value' => $this->configuration['request_plugin'],
      '#ajax' => [
        'callback' => [$this, 'requestPluginChange'],
        'event' => 'change',
        'wrapper' => $ajax_wrapper,
      ],
    ];

    $form['webform_api_handler']['request'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_wrapper,
      ],
    ];

    $plugin = !empty($form_state->getUserInput()['settings']['request_plugin']) ? $form_state->getUserInput()['settings']['request_plugin'] : $this->configuration['request_plugin'];
    if (!empty($plugin)) {
      $requestPlugin = $this->requestManager->createInstance($plugin);
      $requestPlugin->settingsForm($form, $form_state, $webform->id());
    }

    $response_plugins = $this->responseManager->getDefinitions();
    $response_options = [NULL => $this->t('Select one')];
    foreach ($response_plugins as $plugin) {
      $response_options[$plugin['id']] = $plugin['label'];
    }
    $form['response_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Response plugin'),
      '#description' => $this->t('Select a response plugin. Response plugins are used to manipulate form data into a format compatible with the target API before a response is submitted.'),
      '#options' => $response_options,
      '#default_value' => $this->configuration['response_plugin'],
      '#ajax' => [
        'callback' => [$this, 'responsePluginChange'],
        'event' => 'change',
        'wrapper' => $ajax_wrapper,
      ],
    ];

    $form['webform_api_handler']['response'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_wrapper,
      ],
    ];

    $plugin = !empty($form_state->getUserInput()['settings']['response_plugin']) ? $form_state->getUserInput()['settings']['response_plugin'] : $this->configuration['response_plugin'];
    if (!empty($plugin)) {
      $responsePlugin = $this->responseManager->createInstance($plugin);
      $responsePlugin->settingsForm($form, $form_state, $webform->id());
    }

    $this->elementTokenValidate($form);
    return $this->setSettingsParents($form);
  }

  /**
   * AJAX callback for process plugins.
   */
  public function requestPluginChange(array $form, FormStateInterface $form_state) {
    return $form['settings']['webform_api_handler']['request'];
  }

  /**
   * AJAX callback for process plugins.
   */
  public function responsePluginChange(array $form, FormStateInterface $form_state) {
    return $form['settings']['webform_api_handler']['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
    if ($this->configuration['method'] === 'GET') {
      $this->configuration['type'] = '';
    }

    // Submit request plugin settings.
    if ($plugin = $form_state->getValue('request_plugin')) {
      $requestPlugin = $this->requestManager->createInstance($plugin);
      $requestPlugin->settingsSubmit($form, $form_state, $this->webform->id());
    }

    // Submit request plugin settings.
    if ($plugin = $form_state->getValue('response_plugin')) {
      $responsePlugin = $this->responseManager->createInstance($plugin);
      $responsePlugin->settingsSubmit($form, $form_state, $this->webform->id());
    }

    // Cast debug.
    $this->configuration['debug'] = (bool) $this->configuration['debug'];
  }

  /**
   * {@inheritdoc}
   */
  protected function remotePost($state, WebformSubmissionInterface $webform_submission) {
    $state_url = $state . '_url';
    if (empty($this->configuration[$state_url])) {
      return;
    }

    $this->messageManager->setWebformSubmission($webform_submission);

    $request_url = $this->configuration[$state_url];
    $request_url = $this->replaceTokens($request_url, $webform_submission);
    $request_method = (!empty($this->configuration['method'])) ? $this->configuration['method'] : 'POST';
    $request_type = ($request_method !== 'GET') ? $this->configuration['type'] : NULL;

    // Get request options with tokens replaced.
    $request_options = (!empty($this->configuration['custom_options'])) ? Yaml::decode($this->configuration['custom_options']) : [];
    $request_options = $this->replaceTokens($request_options, $webform_submission);

    try {
      if ($request_method === 'GET') {
        // Append data as query string to the request URL.
        $query = $this->getRequestData($state, $webform_submission);
        $request_url = Url::fromUri($request_url, ['query' => $query])->toString();
        $response = $this->httpClient->get($request_url, $request_options);
      }
      else {
        $method = strtolower($request_method);
        if ($request_type == 'json') {
          if (!isset($request_options['headers']) && !isset($request_options['headers']['Content-Type'])) {
            $request_options['headers']['Content-Type'] = "application/json";
          }
          $request_options['body'] = $this->getRequestData($state, $webform_submission)['body'];
        } else {
          $request_options['form_params'] = $this->getRequestData($state, $webform_submission);
        }

        $response = $this->httpClient->$method($request_url, $request_options);
      }
    }
    catch (RequestException $request_exception) {
      $response = $request_exception->getResponse();

      // Encode HTML entities to prevent broken markup from breaking the page.
      $message = $request_exception->getMessage();
      $message = nl2br(htmlentities($message));

      $this->handleError($state, $message, $request_url, $request_method, $request_type, $request_options, $response);
      return;
    }

    // Display submission exception if response code is not 2xx.
    $status_code = $response->getStatusCode();
    if ($status_code < 200 || $status_code >= 300) {
      $message = $this->t('Remote post request return @status_code status code.', ['@status_code' => $status_code]);
      $this->handleError($state, $message, $request_url, $request_method, $request_type, $request_options, $response);
      return;
    }

    // If debugging is enabled, display the request and response.
    $this->debug(t('Remote post successful!'), $state, $request_url, $request_method, $request_type, $request_options, $response, 'warning');

    // Replace [webform:handler] tokens in submission data.
    // Data structured for [webform:handler:remote_post:completed:key] tokens.
    $submission_data = $webform_submission->getData();
    $submission_has_token = (strpos(print_r($submission_data, TRUE), '[webform:handler:' . $this->getHandlerId() . ':') !== FALSE) ? TRUE : FALSE;
    if ($submission_has_token) {
      $response_data = $this->getResponseData($response);
      $token_data = ['webform_handler' => [$this->getHandlerId() => [$state => $response_data]]];
      $submission_data = $this->replaceTokens($submission_data, $webform_submission, $token_data);
      $webform_submission->setData($submission_data);
      // Resave changes to the submission data without invoking any hooks
      // or handlers.
      if ($this->isResultsEnabled()) {
        $webform_submission->resave();
      }
    }

    // Execute any configured response plugin.
    if ($plugin = $this->configuration['response_plugin'] && !empty($response)) {
      $responsePlugin = $this->responseManager->createInstance($plugin);
      $responsePlugin->processResponse($data, $response, $this->webform);
    }
  }

  /**
   * Get a webform submission's request data.
   *
   * @param string $state
   *   The state of the webform submission.
   *   Either STATE_NEW, STATE_DRAFT_CREATED, STATE_DRAFT_UPDATED,
   *   STATE_COMPLETED, STATE_UPDATED, or STATE_CONVERTED
   *   depending on the last save operation performed.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission to be posted.
   *
   * @return array
   *   A webform submission converted to an associative array.
   */
  protected function getRequestData($state, WebformSubmissionInterface $webform_submission) {
    $data = parent::getRequestData($state, $webform_submission);

    // Pass $webform_submission through request handler to manipulate the submission before sending to the endpoint.
    if ($plugin = $this->configuration['request_plugin']) {
      $requestPlugin = $this->requestManager->createInstance($plugin);
      $data = $requestPlugin->processRequest($data, $webform_submission, $this->webform);
    }

    return $data;
  }

  /**
   * Get response data.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response returned by the remote server.
   *
   * @return array|string
   *   An array of data, parse from JSON, or a string.
   */
  protected function getResponseData(ResponseInterface $response) {
    $body = (string) $response->getBody();
    $data = json_decode($body, TRUE);

    if ($plugin = $this->configuration['response_plugin']) {
      $responsePlugin = $this->responseManager->createInstance($plugin);
      $data = $responsePlugin->processResponse($data, $response, $this->webform);
    }
    else {
      $data = (json_last_error() === JSON_ERROR_NONE) ? $data : $body;
    }

    return $data;
  }

  /**
   * Handle error by logging and display debugging and/or exception message.
   *
   * @param string $state
   *   The state of the webform submission.
   *   Either STATE_NEW, STATE_DRAFT_CREATED, STATE_DRAFT_UPDATED,
   *   STATE_COMPLETED, STATE_UPDATED, or STATE_CONVERTED
   *   depending on the last save operation performed.
   * @param string $message
   *   Message to be displayed.
   * @param string $request_url
   *   The remote URL the request is being posted to.
   * @param string $request_method
   *   The method of remote post.
   * @param string $request_type
   *   The type of remote post.
   * @param string $request_options
   *   The requests options including the submission data..
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response returned by the remote server.
   */
  protected function handleError($state, $message, $request_url, $request_method, $request_type, $request_options, $response) {
    global $base_url, $base_path;

    // If debugging is enabled, display the error message on screen.
    $this->debug($message, $state, $request_url, $request_method, $request_type, $request_options, $response, 'error');

    // Log error message.
    $context = [
      '@form' => $this->getWebform()->label(),
      '@state' => $state,
      '@type' => $request_type,
      '@url' => $request_url,
      '@message' => $message,
      'webform_submission' => $this->getWebformSubmission(),
      'handler_id' => $this->getHandlerId(),
      'operation' => 'error',
      'link' => $this->getWebform()
        ->toLink($this->t('Edit'), 'handlers')
        ->toString(),
    ];
    $this->getLogger('webform_submission')
      ->error('@form webform remote @type post (@state) to @url failed. @message', $context);


    // Load up response plugin if one exists.
    if ($plugin = $this->configuration['response_plugin']) {
      $responsePlugin = $this->responseManager->createInstance($plugin);
    }

    // Display custom or default exception message.
    if (isset($responsePlugin) && method_exists($responsePlugin, 'handleError')) {
      $webform = $this->getWebform();
      $responsePlugin->handleError($webform, $state, $message, $request_url, $request_method, $request_type, $request_options, $response);
    }
    elseif ($custom_response_message = $this->getCustomResponseMessage($response)) {
      $token_data = [
        'webform_handler' => [
          $this->getHandlerId() => $this->getResponseData($response),
        ],
      ];
      $build_message = [
        '#markup' => $this->replaceTokens($custom_response_message, $this->getWebform(), $token_data),
      ];
      $this->messenger()->addError(\Drupal::service('renderer')->renderPlain($build_message));
    }
    else {
      $this->messageManager->display(WebformMessageManagerInterface::SUBMISSION_EXCEPTION_MESSAGE, 'error');
    }

    // Redirect the current request to the error url.
    $error_url = $this->configuration['error_url'];
    if ($error_url && PHP_SAPI !== 'cli') {
      // Convert error path to URL.
      if (strpos($error_url, '/') === 0) {
        $error_url = $base_url . preg_replace('#^' . $base_path . '#', '/', $error_url);
      }
      $response = new TrustedRedirectResponse($error_url);
      $response->send();
    }
  }

}

