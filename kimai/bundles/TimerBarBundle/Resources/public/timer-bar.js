/**
 * Behaviour of the Kimai timer bar: fills in project and activity from recent entries,
 * offers only the activities that fit the chosen project, and runs the clock of the
 * running entry.
 */
( function ()
{
  'use strict';

  /**
   * Selector of the start form.
   *
   * @type {string}
   */
  const FORM_SELECTOR = '[data-timer-bar-form]';

  /**
   * Selector of the clock of a running entry.
   *
   * @type {string}
   */
  const CLOCK_SELECTOR = '[data-timer-bar-begin]';

  /**
   * Activity option attribute value that marks a global activity.
   *
   * @type {string}
   */
  const GLOBAL_ACTIVITY = '0';

  /**
   * Milliseconds between clock updates.
   *
   * @type {number}
   */
  const TICK_INTERVAL = 1000;

  /**
   * Formats a number of seconds as hours, minutes and seconds, for example 1:05:09.
   *
   * @param {number} totalSeconds The duration in seconds.
   * @returns {string}
   */
  function formatClock( totalSeconds )
  {
    const seconds = Math.max( 0, Math.floor( totalSeconds ) );
    const hours = Math.floor( seconds / 3600 );
    const minutes = Math.floor( seconds % 3600 / 60 );

    return hours + ':' + String( minutes ).padStart( 2, '0' ) + ':' + String( seconds % 60 ).padStart( 2, '0' );
  }

  /**
   * Keeps the clock of the running entry, and the browser tab title, up to date.
   *
   * @param {HTMLElement} clock The element that shows the elapsed time.
   * @returns {void}
   */
  function startClock( clock )
  {
    const begin = Date.parse( clock.dataset.timerBarBegin );
    const title = document.title;

    if ( Number.isNaN( begin ) )
    {
      return;
    }

    const update = () =>
    {
      const elapsed = formatClock( ( Date.now() - begin ) / 1000 );
      clock.textContent = elapsed;
      document.title = elapsed + ' · ' + title;
    };

    update();
    window.setInterval( update, TICK_INTERVAL );
  }

  /**
   * Reads the recent entries that the form embeds as JSON.
   *
   * @param {HTMLFormElement} form The start form.
   * @returns {{description: string, projectId: number, activityId: number}[]}
   */
  function readSuggestions( form )
  {
    const element = form.querySelector( '[data-timer-bar-suggestions]' );

    try
    {
      return element === null ? [] : JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Timer bar: invalid suggestions', error );
      return [];
    }
  }

  /**
   * Shows only the activities that can be booked on the selected project, and selects a
   * fitting one when the current choice no longer fits.
   *
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {HTMLSelectElement} activitySelect The activity picker.
   * @param {{projectId: number, activityId: number}[]} suggestions Recent entries, most recent first.
   * @returns {void}
   */
  function filterActivities( projectSelect, activitySelect, suggestions )
  {
    const projectOption = projectSelect.selectedOptions[ 0 ];
    const projectId = projectSelect.value;
    const allowsGlobal = projectOption !== undefined && projectOption.dataset.globalActivities === '1';

    Array.from( activitySelect.options ).forEach( ( option ) =>
    {
      if ( option.value === '' )
      {
        return;
      }

      const owner = option.dataset.project;
      const fits = projectId !== '' && ( owner === projectId || ( owner === GLOBAL_ACTIVITY && allowsGlobal ) );
      option.hidden = !fits;
      option.disabled = !fits;
    } );

    const selected = activitySelect.selectedOptions[ 0 ];
    if ( selected !== undefined && selected.value !== '' && !selected.disabled )
    {
      return;
    }

    const recent = suggestions.find( ( suggestion ) => String( suggestion.projectId ) === projectId );
    const recentOption = recent === undefined ? null : activitySelect.querySelector( 'option[value="' + recent.activityId + '"]:not([disabled])' );
    const firstOption = Array.from( activitySelect.options ).find( ( option ) => option.value !== '' && !option.disabled );

    activitySelect.value = ( recentOption ?? firstOption ?? { value: '' } ).value;
  }

  /**
   * Wires up the start form: suggestions fill in project and activity, and the activity
   * picker follows the project picker.
   *
   * @param {HTMLFormElement} form The start form.
   * @returns {void}
   */
  function initForm( form )
  {
    const description = form.querySelector( '[data-timer-bar-description]' );
    const projectSelect = form.querySelector( '[data-timer-bar-project]' );
    const activitySelect = form.querySelector( '[data-timer-bar-activity]' );
    const suggestions = readSuggestions( form );

    if ( description === null || projectSelect === null || activitySelect === null )
    {
      return;
    }

    description.addEventListener( 'input', () =>
    {
      const match = suggestions.find( ( suggestion ) => suggestion.description === description.value );
      if ( match === undefined )
      {
        return;
      }

      projectSelect.value = String( match.projectId );
      activitySelect.value = String( match.activityId );
      filterActivities( projectSelect, activitySelect, suggestions );
    } );

    projectSelect.addEventListener( 'change', () => filterActivities( projectSelect, activitySelect, suggestions ) );
    filterActivities( projectSelect, activitySelect, suggestions );
  }

  document.addEventListener( 'DOMContentLoaded', () =>
  {
    document.querySelectorAll( FORM_SELECTOR ).forEach( initForm );
    document.querySelectorAll( CLOCK_SELECTOR ).forEach( startClock );
  } );
} )();
