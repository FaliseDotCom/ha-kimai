<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle;

use App\Plugin\PluginInterface;
use KimaiPlugin\OdtTemplatesBundle\DependencyInjection\Compiler\ExportRendererPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Exports and invoices from OpenDocument text templates (.odt), made in LibreOffice Writer.
 */
final class OdtTemplatesBundle extends Bundle implements PluginInterface
{
  /**
   * Registers the compiler pass that adds the ODT exports to Kimai's export service.
   *
   * @param ContainerBuilder $container The container being built.
   * @return void
   */
  public function build( ContainerBuilder $container ) : void
  {
    parent::build( $container );

    $container->addCompilerPass( new ExportRendererPass() );
  }
}
