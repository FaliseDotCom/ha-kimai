<?php

declare( strict_types=1 );

namespace KimaiPlugin\ExportExtrasBundle;

use App\Constants;
use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Improvements to Kimai's exports, such as export templates that keep their column order.
 */
final class ExportExtrasBundle extends Bundle implements PluginInterface
{
  public const ASSET_DIRECTORY = __DIR__ . '/Resources/public';

  /**
   * The first Kimai version that keeps the column order of export templates itself, as
   * Kimai's `Constants::VERSION_ID`: 2.69.0.
   *
   * @var int
   */
  public const NATIVE_COLUMN_ORDER_VERSION_ID = 26900;

  /**
   * The files that may be served as assets, with their content type.
   *
   * @var array<string, string>
   */
  public const ASSETS = [
    'export-columns.js' => 'text/javascript',
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

  /**
   * Tells whether this Kimai keeps the column order of export templates itself, in which
   * case the plugin's column order is turned off.
   *
   * @return bool
   */
  public static function hasNativeColumnOrder() : bool
  {
    return self::isKimaiVersion( Constants::VERSION_ID, self::NATIVE_COLUMN_ORDER_VERSION_ID );
  }

  /**
   * Tells whether a Kimai version is the given one or later. A separate method because Kimai's
   * version is a constant, so comparing it in place makes PHPStan judge the comparison by the
   * Kimai it analyses against alone, and report it as always true or always false.
   *
   * @param int $versionId The Kimai version, as `Constants::VERSION_ID`.
   * @param int $minimum The version to compare with, in the same form.
   * @return bool
   */
  private static function isKimaiVersion( int $versionId, int $minimum ) : bool
  {
    return $versionId >= $minimum;
  }
}
