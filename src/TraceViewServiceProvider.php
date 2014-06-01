<?php

/**
 * @file
 * Contains \Drupal\traceview\TraceviewServiceProvider.
 */

namespace Drupal\traceview;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\traceview\DependencyInjection\Compiler\RegisterTraceViewTwigPass;
/**
 * Replace the default Twig base template with a TraceView-enhanced version.
 */
class TraceviewServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $twig = $container->getDefinition('twig');
    $twig_config = $twig->getArgument(1);
    $twig_config['base_template_class'] = 'Drupal\traceview\Template\TraceViewTwigTemplate';
    $twig->replaceArgument(1, $twig_config);
  }
}
