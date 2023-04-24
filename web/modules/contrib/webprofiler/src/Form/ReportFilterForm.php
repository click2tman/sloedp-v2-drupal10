<?php

namespace Drupal\webprofiler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form to filter the list of profiles.
 */
class ReportFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'webprofiler_report_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP'),
      '#size' => 30,
      '#default_value' => $this->getRequest()->query->get('ip'),
      '#prefix' => '<div class="form--inline clearfix">',
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Url'),
      '#size' => 30,
      '#default_value' => $this->getRequest()->query->get('url'),
    ];

    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => ['- any -' => $this->t('All'), 'GET' => 'GET', 'POST' => 'POST'],
      '#default_value' => $this->getRequest()->query->get('method'),
    ];

    $limits = [10, 50, 100];
    $form['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit'),
      '#options' => array_combine($limits, $limits),
      '#default_value' => $this->getRequest()->query->get('limit'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear'),
      '#attributes' => ['class' => ['button--secondary']],
      '#suffix' => '</div>',
      '#submit' => ['::clear'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ip = $form_state->getValue('ip');
    $url = $form_state->getValue('url');
    $method = $form_state->getValue('method');
    $limit = $form_state->getValue('limit');

    $url = new Url('webprofiler.admin_list', [], [
      'query' => [
        'ip' => $ip,
        'url' => $url,
        'method' => $method,
        'limit' => $limit,
      ],
    ]);

    $form_state->setRedirectUrl($url);
  }

  /**
   * Clear the filters.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function clear(array &$form, FormStateInterface $form_state): void {
    $url = new Url('webprofiler.admin_list');
    $form_state->setRedirectUrl($url);
  }

}
