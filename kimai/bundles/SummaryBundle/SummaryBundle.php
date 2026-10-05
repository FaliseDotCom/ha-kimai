<?php

declare( strict_types=1 );

namespace KimaiPlugin\SummaryBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Toggl-style summary report with charts for Kimai.
 */
final class SummaryBundle extends Bundle implements PluginInterface
{
  public const TRANSLATION_DOMAIN = 'summary';
}
