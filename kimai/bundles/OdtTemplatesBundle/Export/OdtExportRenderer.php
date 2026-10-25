<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Export;

use App\Entity\ExportableItem;
use App\Export\ExportFilename;
use App\Export\ExportRendererInterface;
use App\Repository\Query\TimesheetQuery;
use KimaiPlugin\OdtTemplatesBundle\Odt\OdtDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * One ODT export template, offered on Kimai's export page and in the export menu of the
 * record lists. Made per template file by the ExportRendererRegistrar, not as a service.
 */
final class OdtExportRenderer implements ExportRendererInterface
{
  /**
   * The prefix of the IDs, which keeps them apart from Kimai's own exports.
   */
  private const ID_PREFIX = 'odt-';

  /**
   * Creates the export for one template file.
   *
   * @param string $templatePath The absolute path of the .odt template.
   * @param ExportDataBuilder $dataBuilder Turns the records into template values.
   */
  public function __construct(
    private readonly string $templatePath,
    private readonly ExportDataBuilder $dataBuilder,
  )
  {
  }

  /**
   * Renders the records into the template and returns it as a download.
   *
   * @param array<int, ExportableItem> $exportItems The records.
   * @param TimesheetQuery $query The filter they were selected with.
   * @return Response
   */
  public function render( array $exportItems, TimesheetQuery $query ) : Response
  {
    $data = $this->dataBuilder->build( $exportItems, $query );

    return ( new OdtDocument( $this->templatePath ) )->download( $data, ( new ExportFilename( $query ) )->getFilename() );
  }

  /**
   * Returns the export's ID, made from the file name, such as `odt-timesheet` for
   * `timesheet.odt`.
   *
   * @return string
   */
  public function getId() : string
  {
    $name = strtolower( basename( $this->templatePath, '.' . OdtDocument::EXTENSION ) );

    return self::ID_PREFIX . trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', $name ), '-' );
  }

  /**
   * Returns the name shown in the menus: the file name without extension, with dashes and
   * underscores as spaces, as Kimai does for its own template files.
   *
   * @return string
   */
  public function getTitle() : string
  {
    return str_replace( [ '-', '_' ], ' ', basename( $this->templatePath, '.' . OdtDocument::EXTENSION ) );
  }

  /**
   * Returns the export type, which groups the ODT templates under one button on the export
   * page.
   *
   * @return string
   */
  public function getType() : string
  {
    return OdtDocument::EXTENSION;
  }

  /**
   * Tells Kimai the export is not an export template from its database, so it offers no edit
   * and delete buttons for it.
   *
   * @return bool
   */
  public function isInternal() : bool
  {
    return false;
  }
}
