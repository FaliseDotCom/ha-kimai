<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Small improvements to Kimai's forms and lists, such as quicker time and duration entry and
 * editing records directly in the list.
 */
final class UiImprovementsBundle extends Bundle implements PluginInterface
{
  public const ASSET_DIRECTORY = __DIR__ . '/Resources/public';

  /**
   * The files that may be served as assets, with their content type.
   *
   * @var array<string, string>
   */
  public const ASSETS = [
    'ui-improvements.js' => 'text/javascript',
    'input-parsing.js' => 'text/javascript',
    'inline-edit.js' => 'text/javascript',
    'inline-edit.css' => 'text/css',
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
