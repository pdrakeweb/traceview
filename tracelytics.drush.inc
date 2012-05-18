<?php

/**
 * @file
 * Implements drush hooks for the tracelytics module.
 */

/**
 * Implements hook_drush_exit().
 *
 * Record tracelytics traffic partitioning and controller within drush.
 */
function tracelytics_drush_exit() {
  // Partition drush commands.
  if (variable_get('tracelytics_partition_drush_traffic', FALSE)) {
    oboe_log('info', array('Partition' => 'Drush'));
  }

  // Track drush comamnds.
  if (variable_get('tracelytics_track_drush_commands', FALSE)) {
    $command = drush_get_command();
    oboe_log('info',
      array(
        'Controller' => $command['command'],
        'Action' => (!empty($command['arguments'][0]) ? tracelytics_get_action($command['arguments'][0]) : NULL),
      )
    );
  }
}