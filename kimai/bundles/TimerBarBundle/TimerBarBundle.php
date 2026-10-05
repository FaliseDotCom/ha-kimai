<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Toggl-style timer bar for Kimai.
 */
final class TimerBarBundle extends Bundle implements PluginInterface
{
  public const TRANSLATION_DOMAIN = 'timer_bar';
  public const ASSET_DIRECTORY = __DIR__ . '/Resources/public';

  /**
   * The files that may be served as assets, with their content type.
   *
   * @var array<string, string>
   */
  public const ASSETS = [
    'timer-bar.js' => 'text/javascript',
    'timer-bar.css' => 'text/css',
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
