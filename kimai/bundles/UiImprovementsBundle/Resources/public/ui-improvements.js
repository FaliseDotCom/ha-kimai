/**
 * Quicker time and duration entry in Kimai's forms. Short forms are expanded as soon as a
 * field is left, before Kimai itself reads the value:
 *
 * - times: 9 → 9:00, 945 → 9:45, 1330 → 13:30, 9.45 → 9:45, 945p → 9:45 PM
 * - durations: 10 → 0:10, 90 → 1:30, 130 → 1:30, 1045 → 10:45
 *
 * Anything else is left for Kimai to read as before.
 */
( function ()
{
  'use strict';

  /**
   * Selector of Kimai's time fields, such as the start and end time of a record.
   *
   * @type {string}
   */
  const TIME_SELECTOR = 'input[data-timepicker="on"]';

  /**
   * Selector of Kimai's duration fields, in the record form and on "Weekly hours".
   *
   * @type {string}
   */
  const DURATION_SELECTOR = 'input.duration-input';

  /**
   * A time typed with a separator: 9:45, 9.45, 9,45, 9h45 or 9 45, optionally with am/pm.
   *
   * @type {RegExp}
   */
  const SEPARATED_TIME = /^(\d{1,2})\s*[:.,h ]\s*(\d{2})$/;

  /**
   * A time typed as digits only: 9, 09, 945 or 1330.
   *
   * @type {RegExp}
   */
  const DIGITS = /^\d{1,4}$/;

  /**
   * An am/pm suffix: a, am, a.m., p, pm or p.m.
   *
   * @type {RegExp}
   */
  const MERIDIEM = /\s*([ap])\.?\s*m?\.?$/i;

  /**
   * Durations of up to this many digits are minutes; longer ones are hours and minutes.
   *
   * @type {number}
   */
  const MINUTE_DIGITS = 2;

  /**
   * Pads a number to two digits.
   *
   * @param {number} value The number.
   * @returns {string}
   */
  function pad( value )
  {
    return String( value ).padStart( 2, '0' );
  }

  /**
   * Reads a typed time as hours and minutes, or returns null when it is not a short form.
   *
   * @param {string} input The typed value.
   * @returns {?{hour: number, minute: number}}
   */
  function parseTime( input )
  {
    let text = input.trim();
    let meridiem = '';

    const suffix = MERIDIEM.exec( text );
    if ( suffix !== null && /\d/.test( text.slice( 0, suffix.index ) ) )
    {
      meridiem = suffix[ 1 ].toLowerCase();
      text = text.slice( 0, suffix.index ).trim();
    }

    let hour;
    let minute;
    const separated = SEPARATED_TIME.exec( text );

    if ( separated !== null )
    {
      hour = Number( separated[ 1 ] );
      minute = Number( separated[ 2 ] );
    }
    else if ( DIGITS.test( text ) )
    {
      const hourDigits = text.length <= 2 ? text.length : text.length - 2;
      hour = Number( text.slice( 0, hourDigits ) );
      minute = Number( text.slice( hourDigits ) || '0' );
    }
    else
    {
      return null;
    }

    if ( meridiem !== '' )
    {
      if ( hour < 1 || hour > 12 )
      {
        return null;
      }

      hour = hour % 12 + ( meridiem === 'p' ? 12 : 0 );
    }

    return hour <= 23 && minute <= 59 ? { hour, minute } : null;
  }

  /**
   * Formats a time the way the field expects it: 13:30 or 1:30 PM.
   *
   * @param {{hour: number, minute: number}} time The time.
   * @param {boolean} twelveHour Whether the field uses a 12-hour clock.
   * @returns {string}
   */
  function formatTime( time, twelveHour )
  {
    if ( !twelveHour )
    {
      return pad( time.hour ) + ':' + pad( time.minute );
    }

    const hour = time.hour % 12 === 0 ? 12 : time.hour % 12;

    return hour + ':' + pad( time.minute ) + ' ' + ( time.hour < 12 ? 'AM' : 'PM' );
  }

  /**
   * Expands a short duration to hours and minutes, or returns null when it is not one.
   *
   * @param {string} input The typed value.
   * @returns {?string}
   */
  function expandDuration( input )
  {
    const text = input.trim();
    if ( !DIGITS.test( text ) )
    {
      return null;
    }

    let minutes = Number( text );
    if ( text.length > MINUTE_DIGITS )
    {
      const hours = Number( text.slice( 0, -2 ) );
      const rest = Number( text.slice( -2 ) );
      minutes = rest < 60 ? hours * 60 + rest : minutes;
    }

    return Math.floor( minutes / 60 ) + ':' + pad( minutes % 60 );
  }

  /**
   * Expands the value of a time or duration field in place.
   *
   * @param {HTMLInputElement} field The field.
   * @returns {void}
   */
  function expandField( field )
  {
    let expanded = null;

    if ( field.matches( TIME_SELECTOR ) )
    {
      const time = parseTime( field.value );
      expanded = time === null ? null : formatTime( time, ( field.dataset.format ?? '' ).includes( 'A' ) );
    }
    else if ( field.matches( DURATION_SELECTOR ) )
    {
      expanded = expandDuration( field.value );
    }

    if ( expanded !== null && expanded !== field.value )
    {
      field.value = expanded;
    }
  }

  // Capture phase: the value is expanded before Kimai's own change handlers read it, also in
  // forms that Kimai loads into dialogs later.
  document.addEventListener( 'change', ( event ) =>
  {
    if ( event.target instanceof HTMLInputElement )
    {
      expandField( event.target );
    }
  }, true );

  document.addEventListener( 'submit', ( event ) =>
  {
    if ( event.target instanceof HTMLFormElement )
    {
      event.target.querySelectorAll( TIME_SELECTOR + ', ' + DURATION_SELECTOR ).forEach( expandField );
    }
  }, true );
} )();
