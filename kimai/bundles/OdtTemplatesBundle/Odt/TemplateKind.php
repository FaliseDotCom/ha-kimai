<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Odt;

/**
 * What an ODT template is used for.
 */
enum TemplateKind: string
{
  case Export = 'export';
  case Invoice = 'invoice';

  /**
   * Returns the translation key of the kind's name, in the `odt_templates` domain.
   *
   * @return string
   */
  public function getLabel() : string
  {
    return 'odt.kind.' . $this->value;
  }
}
