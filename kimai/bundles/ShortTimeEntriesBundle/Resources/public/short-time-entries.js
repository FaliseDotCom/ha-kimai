/**
 * Quicker time and duration entry in Kimai's forms. Short forms are expanded as soon as a
 * field is left, before Kimai itself reads the value:
 *
 * - times: 9 → 9:00, 945 → 9:45, 1330 → 13:30, 9.45 → 9:45, 945p → 9:45 PM
 * - durations: 10 → 0:10, 90 → 1:30, 130 → 1:30, 1045 → 10:45
 *
 * Anything else is left for Kimai to read as before.
 */

// Loaded with this script's version query, so a release never mixes cached files.
const { parseTime, formatTime, expandDuration } = await import( new URL( 'input-parsing.js' + new URL( import.meta.url ).search, import.meta.url ).href );

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
