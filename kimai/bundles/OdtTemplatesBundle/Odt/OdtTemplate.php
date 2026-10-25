<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Odt;

/**
 * One ODT template file in an export or invoice folder.
 */
final class OdtTemplate
{
  /**
   * Describes a template file.
   *
   * @param TemplateKind $kind Whether it is an export template or an invoice document.
   * @param string $path The absolute path of the file.
   * @param string $usedBy The name of the invoice template that uses it, or an empty string.
   */
  public function __construct(
    private readonly TemplateKind $kind,
    private readonly string $path,
    private readonly string $usedBy,
  )
  {
  }

  /**
   * Returns whether it is an export template or an invoice document.
   *
   * @return TemplateKind
   */
  public function getKind() : TemplateKind
  {
    return $this->kind;
  }

  /**
   * Returns the absolute path of the file.
   *
   * @return string
   */
  public function getPath() : string
  {
    return $this->path;
  }

  /**
   * Returns the file name, such as `Timesheet.odt`.
   *
   * @return string
   */
  public function getFileName() : string
  {
    return basename( $this->path );
  }

  /**
   * Returns when the file was last changed, as a Unix timestamp.
   *
   * @return int
   */
  public function getLastChange() : int
  {
    return (int) filemtime( $this->path );
  }

  /**
   * Returns the name of the invoice template that uses the document, or an empty string.
   *
   * @return string
   */
  public function getUsedBy() : string
  {
    return $this->usedBy;
  }

  /**
   * Checks whether an invoice template uses the document, which then cannot be deleted.
   *
   * @return bool
   */
  public function isUsed() : bool
  {
    return $this->usedBy !== '';
  }
}
