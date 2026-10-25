<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Export;

use KimaiPlugin\OdtTemplatesBundle\Odt\OdtDocument;

/**
 * Finds the ODT export templates in Kimai's export template folders, such as `var/export/`.
 */
final class ExportTemplateFinder
{
  /**
   * Kimai's own export template folder, which takes no uploads.
   */
  private const BUILT_IN_DIRECTORY = 'templates/export/renderer/';

  /**
   * Creates the finder.
   *
   * @param array<int, string> $directories Kimai's export template folders, absolute or
   *                                        relative to Kimai's folder.
   * @param string $projectDirectory Kimai's folder.
   */
  public function __construct(
    private readonly array $directories,
    private readonly string $projectDirectory,
  )
  {
  }

  /**
   * Returns the absolute paths of the templates, sorted by file name. When two folders hold
   * a template with the same name, the first folder wins, as with Kimai's own templates.
   *
   * @return array<int, string>
   */
  public function findAll() : array
  {
    $templates = [];
    foreach ( $this->directories as $directory )
    {
      $files = glob( $this->resolve( $directory ) . '/*.' . OdtDocument::EXTENSION );
      if ( !is_array( $files ) )
      {
        continue;
      }

      foreach ( $files as $file )
      {
        $templates[ basename( $file ) ] ??= $file;
      }
    }

    ksort( $templates );

    return array_values( $templates );
  }

  /**
   * Returns the absolute path of the folder for new templates: `var/export/` unless configured
   * otherwise. Kimai lists its default folders last, so the last folder that is not Kimai's
   * own is used, as Kimai does for invoice documents.
   *
   * @return string
   */
  public function getUploadDirectory() : string
  {
    foreach ( array_reverse( $this->directories ) as $directory )
    {
      if ( $directory !== self::BUILT_IN_DIRECTORY )
      {
        return $this->resolve( $directory );
      }
    }

    return '';
  }

  /**
   * Turns a folder relative to Kimai's folder into an absolute path.
   *
   * @param string $directory The folder as configured.
   * @return string
   */
  private function resolve( string $directory ) : string
  {
    $directory = rtrim( $directory, '/' );
    if ( str_starts_with( $directory, '/' ) )
    {
      return $directory;
    }

    return $this->projectDirectory . '/' . $directory;
  }
}
