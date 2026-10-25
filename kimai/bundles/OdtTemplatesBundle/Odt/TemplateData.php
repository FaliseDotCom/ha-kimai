<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Odt;

/**
 * The values that fill one ODT template: the document-wide values, and one set of values per
 * record for the parts of the document that repeat.
 */
final class TemplateData
{
  /**
   * Creates the data for one rendered document.
   *
   * @param array<string, mixed> $values The document-wide values, by placeholder name.
   * @param array<int, array<string, mixed>> $rows The values of each record, by placeholder name.
   */
  public function __construct(
    private readonly array $values,
    private readonly array $rows,
  )
  {
  }

  /**
   * Returns the document-wide values, by placeholder name.
   *
   * @return array<string, mixed>
   */
  public function getValues() : array
  {
    return $this->values;
  }

  /**
   * Returns the values of each record, by placeholder name.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getRows() : array
  {
    return $this->rows;
  }
}
