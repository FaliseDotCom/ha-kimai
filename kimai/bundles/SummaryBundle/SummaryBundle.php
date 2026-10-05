<?php

declare( strict_types=1 );

namespace KimaiPlugin\SummaryBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Charts for Kimai: a summary report, and charts on the weekly, monthly and yearly user reports.
 */
final class SummaryBundle extends Bundle implements PluginInterface
{
  public const TRANSLATION_DOMAIN = 'summary';
  public const ASSET_DIRECTORY = __DIR__ . '/Resources/public';

  /**
   * The files that may be served as assets, with their content type.
   *
   * @var array<string, string>
   */
  public const ASSETS = [
    'summary.js' => 'text/javascript',
    'summary.css' => 'text/css',
  ];

  /**
   * Returns a value that changes whenever an asset changes, for cache busting.
   *
   * @return string
   */
  public static function getAssetVersion() : string
  {
    $version = 0;
    foreach ( array_keys( self::ASSETS ) as $name )
    {
      $version = max( $version, (int) filemtime( self::ASSET_DIRECTORY . '/' . $name ) );
    }

    return (string) $version;
  }
}
