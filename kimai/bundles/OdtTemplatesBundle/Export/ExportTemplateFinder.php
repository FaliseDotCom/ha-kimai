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
