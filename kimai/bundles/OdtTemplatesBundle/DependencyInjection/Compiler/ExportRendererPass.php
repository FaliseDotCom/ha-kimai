<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\DependencyInjection\Compiler;

use App\Export\ServiceExport;
use KimaiPlugin\OdtTemplatesBundle\Export\ExportRendererRegistrar;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Makes the ExportRendererRegistrar the configurator of Kimai's export service, so the ODT
 * templates are added whenever that service is created.
 */
final class ExportRendererPass implements CompilerPassInterface
{
  /**
   * Sets the configurator, unless the export service is missing or already has one.
   *
   * @param ContainerBuilder $container The container being built.
   * @return void
   */
  public function process( ContainerBuilder $container ) : void
  {
    if ( !$container->has( ServiceExport::class ) )
    {
      return;
    }

    $definition = $container->findDefinition( ServiceExport::class );
    if ( $definition->getConfigurator() !== null )
    {
      return;
    }

    $definition->setConfigurator( [ new Reference( ExportRendererRegistrar::class ), 'configure' ] );
  }
}
