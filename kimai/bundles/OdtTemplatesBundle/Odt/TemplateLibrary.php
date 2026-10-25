<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Odt;

use App\Repository\InvoiceDocumentRepository;
use App\Repository\InvoiceTemplateRepository;
use KimaiPlugin\OdtTemplatesBundle\Export\ExportTemplateFinder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Lists, stores and removes the ODT templates in Kimai's export and invoice folders.
 */
final class TemplateLibrary
{
  /**
   * The longest file name, without extension, that an upload keeps.
   */
  private const MAX_NAME_LENGTH = 50;

  /**
   * Translation keys, in the `odt_templates` domain, of the reasons an upload is refused.
   */
  public const PROBLEM_TYPE = 'odt.error.type';
  public const PROBLEM_NAME = 'odt.error.name';
  public const PROBLEM_EXISTS = 'odt.error.exists';
  public const PROBLEM_TAKEN = 'odt.error.taken';

  /**
   * Creates the library.
   *
   * @param ExportTemplateFinder $exportFinder Finds the export templates.
   * @param InvoiceDocumentRepository $invoiceDocuments Kimai's invoice documents.
   * @param InvoiceTemplateRepository $invoiceTemplates Kimai's invoice templates, which use documents.
   * @param string $projectDirectory Kimai's folder.
   */
  public function __construct(
    private readonly ExportTemplateFinder $exportFinder,
    private readonly InvoiceDocumentRepository $invoiceDocuments,
    private readonly InvoiceTemplateRepository $invoiceTemplates,
    private readonly string $projectDirectory,
  )
  {
  }

  /**
   * Returns all ODT templates: the export templates, then the invoice documents.
   *
   * @return array<int, OdtTemplate>
   */
  public function findAll() : array
  {
    $templates = [];
    foreach ( $this->exportFinder->findAll() as $path )
    {
      $templates[] = new OdtTemplate( TemplateKind::Export, $path, '' );
    }

    $usedBy = $this->invoiceTemplateNames();
    foreach ( $this->invoiceDocuments->findCustom() as $document )
    {
      if ( $this->hasOdtExtension( $document->getName() ) )
      {
        $templates[] = new OdtTemplate( TemplateKind::Invoice, $document->getFilename(), $usedBy[ $document->getId() ] ?? '' );
      }
    }

    return $templates;
  }

  /**
   * Finds a template by kind and file name.
   *
   * @param TemplateKind $kind Whether it is an export template or an invoice document.
   * @param string $fileName The file name, such as `Timesheet.odt`.
   * @return OdtTemplate|null
   */
  public function find( TemplateKind $kind, string $fileName ) : ?OdtTemplate
  {
    foreach ( $this->findAll() as $template )
    {
      if ( $template->getKind() === $kind && $template->getFileName() === $fileName )
      {
        return $template;
      }
    }

    return null;
  }

  /**
   * Turns the name of an uploaded file into a safe file name: ASCII letters, digits, dashes
   * and underscores, with spaces and dots as dashes, ending in `.odt`. Returns an empty
   * string when nothing usable is left.
   *
   * @param string $clientName The file name as uploaded.
   * @return string
   */
  public function fileNameFor( string $clientName ) : string
  {
    $name = (string) preg_replace( '/\.odt$/i', '', basename( $clientName ) );
    $ascii = transliterator_transliterate( 'Any-Latin; Latin-ASCII', $name );
    $name = is_string( $ascii ) ? $ascii : $name;
    $name = (string) preg_replace( '/[\s.]+/', '-', $name );
    $name = (string) preg_replace( '/[^A-Za-z0-9_-]+/', '', $name );
    $name = trim( (string) preg_replace( '/-{2,}/', '-', $name ), '-_' );
    $name = substr( $name, 0, self::MAX_NAME_LENGTH );

    return $name === '' ? '' : $name . '.' . OdtDocument::EXTENSION;
  }

  /**
   * Checks an upload before it is stored, and returns the reason it is refused as a
   * translation key, or an empty string when it may be stored.
   *
   * @param UploadedFile $file The uploaded file.
   * @param TemplateKind $kind Where it goes.
   * @param string $fileName The file name it will get, from fileNameFor().
   * @param bool $replace Whether a template with the same name may be replaced.
   * @return string
   */
  public function findProblem( UploadedFile $file, TemplateKind $kind, string $fileName, bool $replace ) : string
  {
    if ( !$this->hasOdtExtension( $file->getClientOriginalName() ) || !( new OdtDocument( $file->getPathname() ) )->isTextDocument() )
    {
      return self::PROBLEM_TYPE;
    }

    if ( $fileName === '' )
    {
      return self::PROBLEM_NAME;
    }

    if ( !$replace && is_file( $this->getDirectory( $kind ) . '/' . $fileName ) )
    {
      return self::PROBLEM_EXISTS;
    }

    if ( $kind === TemplateKind::Invoice && $this->isTakenByOtherDocument( $fileName ) )
    {
      return self::PROBLEM_TAKEN;
    }

    return '';
  }

  /**
   * Stores an uploaded template, replacing a file with the same name.
   *
   * @param UploadedFile $file The uploaded file, checked with findProblem().
   * @param TemplateKind $kind Where it goes.
   * @param string $fileName The file name it gets.
   * @return void
   */
  public function save( UploadedFile $file, TemplateKind $kind, string $fileName ) : void
  {
    $directory = $this->getDirectory( $kind );
    if ( $directory === '' )
    {
      throw new \RuntimeException( 'Kimai has no folder for these templates.' );
    }

    if ( !is_dir( $directory ) && !mkdir( $directory, 0o777, true ) && !is_dir( $directory ) )
    {
      throw new \RuntimeException( sprintf( 'The folder %s could not be created.', $directory ) );
    }

    $file->move( $directory, $fileName );
  }

  /**
   * Deletes a template. An invoice document that a template uses is kept, as Kimai does.
   *
   * @param OdtTemplate $template The template to delete.
   * @return void
   */
  public function delete( OdtTemplate $template ) : void
  {
    if ( $template->isUsed() )
    {
      throw new \RuntimeException( sprintf( 'The invoice template %s uses this document.', $template->getUsedBy() ) );
    }

    if ( !unlink( $template->getPath() ) )
    {
      throw new \RuntimeException( sprintf( 'The file %s could not be deleted.', $template->getFileName() ) );
    }
  }

  /**
   * Returns the absolute path of the folder that new templates of a kind go into, or an
   * empty string when Kimai has none.
   *
   * @param TemplateKind $kind Whether it is for export templates or invoice documents.
   * @return string
   */
  public function getDirectory( TemplateKind $kind ) : string
  {
    if ( $kind === TemplateKind::Export )
    {
      return $this->exportFinder->getUploadDirectory();
    }

    try
    {
      $directory = rtrim( $this->invoiceDocuments->getUploadDirectory(), '/' );
    }
    catch ( \Exception )
    {
      return '';
    }

    return str_starts_with( $directory, '/' ) ? $directory : $this->projectDirectory . '/' . $directory;
  }

  /**
   * Checks whether another invoice document has the ID a file name would get. Kimai uses
   * the first document with an ID, so a new one would replace, for example, its own
   * `invoice.html.twig` for `invoice.odt`.
   *
   * @param string $fileName The file name.
   * @return bool
   */
  private function isTakenByOtherDocument( string $fileName ) : bool
  {
    $id = substr( $fileName, 0, (int) strpos( $fileName, '.' ) );
    foreach ( $this->invoiceDocuments->findAll() as $document )
    {
      if ( $document->getId() === $id && $document->getName() !== $fileName )
      {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns the names of the invoice templates by the ID of the document they use.
   *
   * @return array<string, string>
   */
  private function invoiceTemplateNames() : array
  {
    $names = [];
    foreach ( $this->invoiceTemplates->findAll() as $template )
    {
      $names[ (string) $template->getRenderer() ] = (string) $template->getName();
    }

    return $names;
  }

  /**
   * Checks whether a file name ends in `.odt`.
   *
   * @param string $fileName The file name.
   * @return bool
   */
  private function hasOdtExtension( string $fileName ) : bool
  {
    return strtolower( pathinfo( $fileName, PATHINFO_EXTENSION ) ) === OdtDocument::EXTENSION;
  }
}
