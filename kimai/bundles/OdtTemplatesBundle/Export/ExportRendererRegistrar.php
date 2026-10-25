<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Export;

use App\Export\ServiceExport;

/**
 * Adds an export for every ODT template to Kimai's export service.
 *
 * Kimai registers exports while it builds its service container, one service each. The
 * templates are files that come and go, so this runs as the configurator of the export
 * service instead: every time the service is created, the folders are read again. A new
 * template shows up without rebuilding Kimai's cache.
 */
final class ExportRendererRegistrar
{
  /**
   * Creates the registrar.
   *
   * @param ExportTemplateFinder $finder Finds the template files.
   * @param ExportDataBuilder $dataBuilder Turns the records into template values.
   */
  public function __construct(
    private readonly ExportTemplateFinder $finder,
    private readonly ExportDataBuilder $dataBuilder,
  )
  {
  }

  /**
   * Adds the templates to the export page and to the export menu of the record lists.
   *
   * @param ServiceExport $serviceExport Kimai's export service, just created.
   * @return void
   */
  public function configure( ServiceExport $serviceExport ) : void
  {
    foreach ( $this->finder->findAll() as $templatePath )
    {
      $renderer = new OdtExportRenderer( $templatePath, $this->dataBuilder );
      $serviceExport->addRenderer( $renderer );
      $serviceExport->addTimesheetExporter( $renderer );
    }
  }
}
