<?php

/**
 * @file
 * Contains \Drupal\traceview\Form\SettingsForm.
 */

namespace Drupal\traceview\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure settings for TraceView.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'traceview_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('traceview.settings');

    if (!(extension_loaded('oboe'))) {
      drupal_set_message(t('Required Oboe PHP extension is not installed.'), 'error');
    }

    if (isset($conf['traceview_fail_silently']) && $conf['traceview_fail_silently'] === FALSE) {
      drupal_set_message(t('The TraceView module is not configured to fail silently. Removal of the php-oboe extension may cause fatal errors.'), 'warning');
    }

    if (!($traceview_layers_modules = module_exists('traceview_early') && module_exists('traceview_late'))) {
      drupal_set_message(t('traceview_early and traceview_late must be installed in order to track layers.'), 'warning');
    }

    if ($config->get('drush.track_partition', FALSE) || $config->get('drush.track_command')) {
      drupal_set_message(t('oboe.tracing must be configured to "always" for PHP CLI in order to trace drush commands.'), 'notice');
    }

    $form['partition'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Partition traffic'),
      '#description' => t('Enable partitioning of traffic into drush, cron, anonymous and authenticated.'),
      '#default_value' => $config->get('partition.track'),
      '#disabled' => !(function_exists('oboe_log')),
    );
    $form['rum'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track RUM'),
      '#description' => t('Enable tracking of Real User Monitoring data via javascript.'),
      '#default_value' => $config->get('rum.track'),
      '#disabled' => !(function_exists('oboe_get_rum_header')),
    );
    $form['controller'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track active menu items'),
      '#description' => t('Enable tracking of the active menu item and first argument as Controller/Action.'),
      '#default_value' => $config->get('controller.track'),
      '#disabled' => !(function_exists('oboe_log')),
    );
    $form['watchdog'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track watchdog'),
      '#description' => t('Enable tracking of watchdog entries of WATCHDOG_WARNING or greater severity as errors.'),
      '#default_value' => $config->get('watchdog.track'),
      '#disabled' => !(function_exists('oboe_log')),
    );
    $form['watchdog_options'] = array(
      '#type' => 'fieldset',
      '#title' => 'Watchdog options',
      '#collapsible' => TRUE,
      '#collapsed' => !$config->get('watchdog.track'),
      '#states' => array(
        'expanded' => array(
          ':input[name="watchdog"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['watchdog_options']['watchdog_track_404'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track 404s'),
      '#description' => t('Enable tracking of watchdog entries for MENU_NOT_FOUND (404s) as errors.'),
      '#default_value' => $config->get('watchdog.404'),
      '#disabled' => !(function_exists('oboe_log')),
      '#states' => array(
        'enabled' => array(
          ':input[name="watchdog"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['watchdog_options']['watchdog_track_403'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track 403s'),
      '#description' => t('Enable tracking of watchdog entries for MENU_ACCESS_DENIED (403s) as errors.'),
      '#default_value' => $config->get('watchdog.403'),
      '#disabled' => !(function_exists('oboe_log')),
      '#states' => array(
        'enabled' => array(
          ':input[name="watchdog"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['hooks'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track layers'),
      '#description' => t('Enable tracking of Drupal layers via hooks.'),
      '#default_value' => $config->get('hooks.track'),
      '#disabled' => !$traceview_hooks_modules,
    );
    $form['profile'] = array(
      '#type' => 'fieldset',
      '#title' => 'Detailed layer profiling',
      '#collapsible' => TRUE,
      '#collapsed' => !$config->get('hooks.track'),
      '#states' => array(
        'expanded' => array(
          ':input[name="track_layers"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['profile']['hooks_profile_views'] = array(
      '#type' => 'checkbox',
      '#title' => t('Views'),
      '#description' => t('Enable additional profiling information about Views.'),
      '#default_value' => $config->get('hooks.profile.views', FALSE),
      '#disabled' => !($traceview_hooks_modules && $config->get('hooks.track') && module_exists('views')),
    );
    $form['profile']['hooks_profile_panels'] = array(
      '#type' => 'checkbox',
      '#title' => t('Panels'),
      '#description' => t('Enable additional profiling information about Panels.'),
      '#default_value' => $config->get('hooks.profile.panels', FALSE),
      '#disabled' => !($traceview_hooks_modules && $config->get('hooks.track') && module_exists('panels')),
    );
    $form['api'] = array(
      '#type' => 'fieldset',
      '#title' => 'TraceView API',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['api']['api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('TraceView client key'),
      '#description' => t("To enable calls to the TraceView API, provide your client key (e.g., 'abcd1234-1234-1234-aaaa-123412341234'). It can be found on the 'Install Host Agent' tab of the 'Get Started' page."),
      '#default_value' => $config->get('api.key'),
    );
    $form['api']['annotations'] = array(
      '#type' => 'fieldset',
      '#title' => 'Annotations',
      '#description' => t('If a client key has been provided, the `traceview_add_annotation` function can be used to add annotations in TraceView directly from your Drupal site. This function requires a string argument to be associated with the annotation, but it will also accept a second array argument using the key-value pairs detailed in the !link.', array('!link' => l(t('TraceView API documentation'), 'http://support.tv.appneta.com/kb/how-to/recording-system-events-with-tlog#api'))),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['api']['annotations']['api_annotation_appname'] = array(
      '#type' => 'textfield',
      '#title' => t('Default application'),
      '#description' => t("Provide the application that annotations should be associated with (typically, the application you've placed this Drupal installation in). This can be overriden on each call to `traceview_add_annotation`. If not provided, annotations will not be limited by application."),
      '#default_value' => $config->get('api.annotations.appname', ''),
    );
    $form['api']['annotations']['api_annotation_username'] = array(
      '#type' => 'textfield',
      '#title' => t('Default username'),
      '#description' => t("Provide the username that annotations should be associated with. This can be overriden on each call to `traceview_add_annotation`. If not provided, this will default to the site name, or 'Drupal' if no site name is set."),
      '#default_value' => $config->get('api.annotations.username', ''),
    );
    $form['api']['annotations']['api_annotation_modules'] = array(
      '#type' => 'checkbox',
      '#title' => t('Annotate module changes'),
      '#description' => t('Record an annotation when modules are enabled/disabled or module updates are run.'),
      '#default_value' => $config->get('api.annotations.modules'),
    );
    $form['drush'] = array(
      '#type' => 'fieldset',
      '#title' => 'Drush integration',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['drush']['drush_partition'] = array(
      '#type' => 'checkbox',
      '#title' => t('Partition traffic'),
      '#description' => t('Enable partitioning of traffic into drush, cron, anonymous and authenticated.'),
      '#default_value' => $config->get('drush.partition'),
      '#disabled' => !(function_exists('oboe_log')),
    );
    $form['drush']['drush_command'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track active menu items'),
      '#description' => t('Enable tracking of the active menu item and first argument as Controller/Action.'),
      '#default_value' => $config->get('drush.command'),
      '#disabled' => !(function_exists('oboe_log')),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->config('traceview.settings')
      ->set('controller.track', $form_state['values']['controller'])
      ->set('partition.track', $form_state['values']['partition'])
      ->set('rum.track', $form_state['values']['rum'])
      ->set('watchdog.track', $form_state['values']['watchdog'])
      ->set('watchdog.404', $form_state['values']['watchdog_track_404'])
      ->set('watchdog.403', $form_state['values']['watchdog_track_403'])
      ->set('hooks.track', $form_state['values']['hooks'])
      ->set('hooks.profile.views', $form_state['values']['hooks_profile_views'])
      ->set('hooks.profile.panels', $form_state['values']['hooks_profile_panels'])
      ->set('api.key', $form_state['values']['api_key'])
      ->set('api.annotations.appname', $form_state['values']['api_annotations_appname'])
      ->set('api.annotations.username', $form_state['values']['api_annotations_username'])
      ->set('api.annotations.modules', $form_state['values']['api_annotations_modules'])
      ->set('drush.partition', $form_state['values']['drush_partition'])
      ->set('drush.command', $form_state['values']['drush_command'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}
