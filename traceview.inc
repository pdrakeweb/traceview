<?php

/**
 * @file
 * Defines constants and functions needed by traceview.module, traceview.install
 * and traceview.drush.inc.
 */

// These constants are defined here because traceview.module is not included
// during install until after hook_requirements passes and they are used in
// traceview_requirements().

// The latest tested and confirmed working version of the oboe PHP extension.
define('TRACEVIEW_LATEST_CONFIRMED_VERSION', '1.4.5');

// Versions that are known to have problems and are not recommended.
define('TRACEVIEW_BAD_VERSIONS', serialize(array('1.4.2', '1.4.4')));

/**
 * Set the controller to be reported to traceview.
 *
 * @param array $controller
 *   The controller array to be reported to traceview.
 * @param bool $override
 *   Flag to indicate whether the passed controller should override previously
 *   set controller array.
 * @param bool $alter
 *   Flag to indicate whether to call hook_alter on the controller array.
 *
 * @return array
 *   The controller array to be reported to traceview.
 */
function traceview_set_controller($controller = NULL, $override = FALSE, $alter = FALSE) {
  static $traceview_controller = array();
  if (!empty($controller) && ($override || empty($traceview_controller))) {
    $traceview_controller = $controller;
  }
  if (function_exists('drupal_alter') && $alter) {
    drupal_alter('traceview_controller', $traceview_controller);
  }
  return $traceview_controller;
}

/**
 * Set the traceview partition.
 *
 * @param string $partition
 *   Traffic partition name.
 *
 * @return string
 *   Traffic partition name.
 */
function traceview_set_partition($partition = NULL) {
  static $traceview_partition;
  if (!empty($partition)) {
    $traceview_partition = $partition;
  }
  return $traceview_partition;
}

/**
 * Wrapper function for oboe_log to support different library versions, which
 * have different function signatures.
 *
 * @param string $layer
 * @param mixed $label
 * @param mixed $info
 * @param boolean $backtrace
 * @param mixed $edge
 */
function traceview_oboe_log($layer, $label, $info = NULL, $backtrace = NULL, $edge = NULL) {
  // The backtrace option was added in version 1.1.2 and will not be used in
  // earlier versions.
  if ($backtrace === NULL) {
    $backtrace = ($label === 'entry' || $label === 'profile_entry') ? TRUE : FALSE;
  }

  // @see https://support.tv.appneta.com/support/articles/86405-php-instrumentation-release
  switch (TRUE) {
    // Versions ? - 1.1.1
    case version_compare(phpversion('oboe'), '1.1.2', '<'):
      if ($info === NULL) {
        oboe_log($layer, $label);
      }
      elseif ($layer == NULL) {
        oboe_log($label, $info);
      }
      else {
        oboe_log($layer, $label, $info);
      }
      break;

      // Versions 1.1.2 - 1.4.1
      // Added the backtrace parameter.
    case version_compare(phpversion('oboe'), '1.4.2', '<'):
      if ($info === NULL) {
        oboe_log($layer, $label, $backtrace);
      }
      elseif ($layer == NULL) {
        oboe_log($label, $info, $backtrace);
      }
      else {
        oboe_log($layer, $label, $info, $backtrace);
      }
      break;

      // Versions 1.4.2 - 1.4.4
      // These versions broke backwards compatibility such that some calls result
      // in deprecation warnings.  Using @ to suppress warnings is a bad idea, but
      // it's the best option for these versions.
    case version_compare(phpversion('oboe'), '1.4.5', '<'):
      @oboe_log($layer, $label, $info, $backtrace, $edge);
      break;

      // Version 1.4.5+
    default:
      oboe_log($layer, $label, $info, $backtrace, $edge);
      break;
  }
}

/**
 * Add an annotation using the API described here: http://support.tv.appneta.com/kb/how-to/recording-system-events-with-tlog#api
 *
 * @param string $message
 *   Annotation message.
 *
 * @param array $params
 *   Additional annotation parameters.
 *
 * @return boolean
 *   Indicates whether the API call was successful.
 */
function traceview_add_annotation($message, $params = array()) {
  $url = 'https://api.tv.appneta.com/api-v1/log_message';
  $config = \Drupal::config('traceview.settings');

  $default_params = array(
      'key' => $config->get('api.key', FALSE),
      'time' => REQUEST_TIME,
      'appname' => $config->get('api.annotations.appname', FALSE),
      'hostname' => gethostname(),
      'username' => $config->get('api.annotations.username', \Drupal::config('system.site')->get('name', 'Drupal')),
      'layer' => 'PHP',
  );

  $params = array_merge($default_params, $params);
  $params['message'] = $message;

  if (empty($params['key'])) {
    watchdog('traceview', "Failed to add TraceView annotation '%annotation': no TraceView client key has been set.", array('%annotation' => $message), WATCHDOG_ERROR);
    return FALSE;
  }

  $http_options = array(
      'headers' => array(
          'Content-Type' => 'application/x-www-form-urlencoded',
      ),
      'method' => 'POST',
      'data' => http_build_query($params),
  );
  $result = drupal_http_request($url, $http_options);

  if (!empty($result->error)) {
    watchdog('traceview', "Failed to add TraceView annotation '%annotation': error message '%error'.", array('%annotation' => $message, '%error' => $result->error), WATCHDOG_ERROR);
    return FALSE;
  }

  return TRUE;
}

/**
 * In order to support configurations where the php-oboe extension may or may
 * not be available during a given application execution, such as during the
 * execution of a drush command, on a singler server in a cluster or on a non-
 * production environment, the following code will optionally define the
 * missing functions as empty functions provided the traceview_fail_silently
 * configuration setting is not empty.
 */
global $conf;
if (!isset($conf['traceview_fail_silently']) || $conf['traceview_fail_silently'] !== FALSE) {
  if (!function_exists('oboe_log')) {
    // These functions intentionally left empty.
    function oboe_log() {}
    function oboe_is_tracing() {}
    function oboe_get_context() {}
    function oboe_get_rum_header() {}
    function oboe_get_rum_footer() {}
  }
}
