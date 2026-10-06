<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Service;

use App\Configuration\LocaleService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Formats and reads the start and end times in the quick start bar, in the user's 12-hour or
 * 24-hour clock. Reading also accepts short forms such as 945 for 9:45 and 945p for 9:45 PM.
 */
final class TimeInput
{
  /**
   * Formats for a 24-hour clock: PHP, and the JavaScript form Kimai uses in data-format.
   *
   * @var array{php: string, js: string}
   */
  private const FORMAT_24 = [ 'php' => 'H:i', 'js' => 'HH:mm' ];

  /**
   * Formats for a 12-hour clock: PHP, and the JavaScript form Kimai uses in data-format.
   *
   * @var array{php: string, js: string}
   */
  private const FORMAT_12 = [ 'php' => 'g:i A', 'js' => 'h:mm A' ];

  /**
   * A typed time: hours, optional separator and minutes, optional am/pm.
   *
   * @var string
   */
  private const PATTERN = '/^(\d{1,2})(?:\s*[:.,h ]\s*(\d{2}))?\s*(?:([ap])\.?\s*m?\.?)?$/i';

  /**
   * A typed time as digits only, such as 945 or 1330.
   *
   * @var string
   */
  private const DIGITS = '/^(\d{1,2})(\d{2})\s*(?:([ap])\.?\s*m?\.?)?$/i';

  /**
   * @param LocaleService $localeService Tells whether a locale uses a 12-hour clock.
   */
  public function __construct( private readonly LocaleService $localeService )
  {
  }

  /**
   * Returns the time formats for a locale.
   *
   * @param string $locale The user's locale.
   * @return array{php: string, js: string}
   */
  public function getFormats( string $locale ) : array
  {
    return $this->localeService->is24Hour( $locale ) ? self::FORMAT_24 : self::FORMAT_12;
  }

  /**
   * Formats a time for a locale.
   *
   * @param DateTimeImmutable $time The time.
   * @param string $locale The user's locale.
   * @return string
   */
  public function format( DateTimeImmutable $time, string $locale ) : string
  {
    return $time->format( $this->getFormats( $locale )[ 'php' ] );
  }

  /**
   * Reads a typed time on a given day, or returns null when it is not a valid time.
   *
   * @param string $value The typed time.
   * @param DateTimeImmutable $day The day the time belongs to, in the user's time zone.
   * @return DateTimeImmutable|null
   */
  public function parse( string $value, DateTimeImmutable $day ) : ?DateTimeImmutable
  {
    $value = trim( $value );
    $matches = [];

    if ( preg_match( self::DIGITS, $value, $matches ) !== 1 && preg_match( self::PATTERN, $value, $matches ) !== 1 )
    {
      return null;
    }

    $hour = (int) $matches[ 1 ];
    $minute = (int) ( $matches[ 2 ] ?? 0 );
    $meridiem = strtolower( $matches[ 3 ] ?? '' );

    if ( $meridiem !== '' )
    {
      if ( $hour < 1 || $hour > 12 )
      {
        return null;
      }

      $hour = $hour % 12 + ( $meridiem === 'p' ? 12 : 0 );
    }

    if ( $hour > 23 || $minute > 59 )
    {
      return null;
    }

    return $day->setTime( $hour, $minute );
  }

  /**
   * Returns the start of a day in a time zone.
   *
   * @param string $date The day as Y-m-d, or an empty string for today.
   * @param DateTimeZone $timezone The user's time zone.
   * @return DateTimeImmutable|null
   */
  public function parseDay( string $date, DateTimeZone $timezone ) : ?DateTimeImmutable
  {
    if ( $date === '' )
    {
      return new DateTimeImmutable( 'today', $timezone );
    }

    $day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );

    return $day === false ? null : $day;
  }
}
