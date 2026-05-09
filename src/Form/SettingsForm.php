<?php

namespace Drupal\profile_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Profile Registration settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_registration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['profile_registration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('profile_registration.settings');

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('This email is sent only when a new account is created via the member-join path (<code>?profile=main</code>). Event registrants and instructor applicants do not receive it.') . '</p>',
    ];

    $form['member_onboarding_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send the member onboarding email'),
      '#description' => $this->t('Uncheck to disable without removing the configured copy.'),
      '#default_value' => (bool) $config->get('member_onboarding_enabled'),
    ];

    $form['member_onboarding_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $config->get('member_onboarding_subject'),
      '#maxlength' => 255,
      '#states' => [
        'required' => [':input[name="member_onboarding_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['member_onboarding_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $config->get('member_onboarding_body'),
      '#rows' => 18,
      '#description' => $this->t('Plain text. Tokens like <code>[user:field_first_name]</code>, <code>[user:uid]</code>, and <code>[site:url]</code> are replaced before sending.'),
      '#states' => [
        'required' => [':input[name="member_onboarding_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['user', 'site'],
        '#show_restricted' => FALSE,
        '#weight' => 90,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('profile_registration.settings')
      ->set('member_onboarding_enabled', (bool) $form_state->getValue('member_onboarding_enabled'))
      ->set('member_onboarding_subject', $form_state->getValue('member_onboarding_subject'))
      ->set('member_onboarding_body', $form_state->getValue('member_onboarding_body'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
