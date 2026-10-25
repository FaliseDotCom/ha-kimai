<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Invoice;

use App\Invoice\InvoiceFilename;
use App\Invoice\InvoiceModel;
use App\Invoice\RendererInterface;
use App\Model\InvoiceDocument;
use KimaiPlugin\OdtTemplatesBundle\Odt\OdtDocument;
use KimaiPlugin\OdtTemplatesBundle\Odt\TemplateData;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders invoices from ODT documents in Kimai's invoice document folders, with the same
 * placeholders as Kimai's own Word (.docx) documents.
 */
final class OdtInvoiceRenderer implements RendererInterface
{
  /**
   * Checks whether a document is an ODT file.
   *
   * @param InvoiceDocument $document The invoice document.
   * @return bool
   */
  public function supports( InvoiceDocument $document ) : bool
  {
    return strtolower( $document->getFileExtension() ) === OdtDocument::EXTENSION;
  }

  /**
   * Renders the invoice into the document and returns it as a download.
   *
   * @param InvoiceDocument $document The invoice document.
   * @param InvoiceModel $model The invoice with its records.
   * @return Response
   */
  public function render( InvoiceDocument $document, InvoiceModel $model ) : Response
  {
    $rows = [];
    foreach ( $model->getCalculator()?->getEntries() ?? [] as $item )
    {
      $rows[] = $model->itemToArray( $item );
    }

    $data = new TemplateData( $model->toArray(), $rows );

    return ( new OdtDocument( $document->getFilename() ) )->download( $data, ( new InvoiceFilename( $model ) )->getFilename() );
  }
}
