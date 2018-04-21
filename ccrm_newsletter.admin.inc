<?php

/**
 * @file
 * TODO: Enter file description here.
 */

/**
 * Form builder.
 */
function ccrm_newsletter_settings_form($form, &$form_state) {
  $form['ccrm_newsletter_variable_foo'] = array(
    '#type' => 'textfield',
    '#title' => t('Foo'),
    '#default_value' => variable_get('ccrm_newsletter_variable_foo', 42),
    '#required' => TRUE,
  );

  return system_settings_form($form);
}
