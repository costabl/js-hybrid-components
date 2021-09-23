<?php

namespace Drupal\site_studio_webform\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class DemoEventDispatchForm.
 *
 * @package Drupal\cwe_site_studio_webform\Form
 */
class WebformNodeBatchForm extends FormBase {

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WebformNodeBatchForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_studio_webform_node_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['confirm_webform_creation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create all Webform Nodes'),
      '#default_value' => FALSE,
      '#required' => TRUE,
      '#prefix' => '</br></br></br>',
      '#suffix' => '</br>',
    ];

    $form['webform_batch'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Webform Nodes'),
      '#prefix' => '</br>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $checkbox_val = $form_state->getValue('confirm_webform_creation');
    if ($checkbox_val) {
      $webform_query = $this->entityTypeManager->getStorage('webform')->getQuery();
      $webform_ids = $webform_query->execute();

      $batch = [
        'title' => $this->t('Webform Node Creation...'),
        'operations' => [],
        'init_message'     => $this->t('Creating'),
        'progress_message' => $this->t('Processed @current out of @total.'),
        'error_message'    => $this->t('An error occurred during processing'),
        'finished' => '\Drupal\site_studio_webform\WebformCreateNode::webformNodesCreateFinishedCallback',
      ];

      foreach ($webform_ids as $id) {
        $batch['operations'][] = [
          '\Drupal\site_studio_webform\WebformCreateNode::webformNodesCreate',
          [$id],
        ];
      }

      batch_set($batch);
    }
  }

}
