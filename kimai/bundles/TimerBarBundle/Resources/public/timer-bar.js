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
   * Selector of the hidden form that continues a past entry.
   *
   * @type {string}
   */
  const CONTINUE_FORM_SELECTOR = '[data-timer-bar-continue]';

  /**
   * Selector of the entry rows on "My times"; Kimai links each row to its edit page.
   *
   * @type {string}
   */
  const ENTRY_ROW_SELECTOR = 'tr[data-href]';

  /**
   * Extracts the entry ID from a row's edit link.
   *
   * @type {RegExp}
   */
  const ENTRY_ID_PATTERN = /\/timesheet\/(\d+)\/edit/;

  /**
   * Attribute that marks rows that already have a continue button.
   *
   * @type {string}
   */
  const CONTINUE_MARKER = 'data-timer-bar-continue-added';

  /**
   * Values of the billable field: automatic until the user presses the toggle.
   *
   * @type {{automatic: string, yes: string, no: string}}
   */
  const BILLABLE = { automatic: 'auto', yes: 'yes', no: 'no' };

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
   * Returns whether Kimai would make a record billable: only when the project (including its
   * customer) and the activity are all billable.
   *
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {HTMLSelectElement} activitySelect The activity picker.
   * @returns {boolean}
   */
  function isBillableByDefault( projectSelect, activitySelect )
  {
    const project = projectSelect.selectedOptions[ 0 ];
    const activity = activitySelect.selectedOptions[ 0 ];

    return [ project, activity ].every( ( option ) => option === undefined || option.value === '' || option.dataset.billable !== '0' );
  }

  /**
   * Shows the billable toggle as on or off.
   *
   * @param {HTMLButtonElement} toggle The billable toggle.
   * @param {boolean} billable Whether the record will be billable.
   * @returns {void}
   */
  function showBillable( toggle, billable )
  {
    toggle.classList.toggle( 'active', billable );
    toggle.setAttribute( 'aria-pressed', String( billable ) );
  }

  /**
   * Wires up the billable toggle. Until it is pressed it follows Kimai's automatic rule and
   * leaves the decision to Kimai; once pressed it sends an explicit choice.
   *
   * @param {HTMLFormElement} form The start form.
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {HTMLSelectElement} activitySelect The activity picker.
   * @returns {void}
   */
  function initBillable( form, projectSelect, activitySelect )
  {
    const toggle = form.querySelector( '[data-timer-bar-billable]' );
    const value = form.querySelector( '[data-timer-bar-billable-value]' );

    if ( toggle === null || value === null )
    {
      return;
    }

    const follow = () =>
    {
      if ( value.value === BILLABLE.automatic )
      {
        showBillable( toggle, isBillableByDefault( projectSelect, activitySelect ) );
      }
    };

    toggle.addEventListener( 'click', () =>
    {
      const billable = toggle.getAttribute( 'aria-pressed' ) !== 'true';
      value.value = billable ? BILLABLE.yes : BILLABLE.no;
      showBillable( toggle, billable );
    } );

    projectSelect.addEventListener( 'change', follow );
    activitySelect.addEventListener( 'change', follow );
    form.addEventListener( 'timer-bar:selection', follow );
    follow();
  }

  /**
   * Shows how many tags are chosen on the tag button.
   *
   * @param {HTMLFormElement} form The start form.
   * @returns {void}
   */
  function initTags( form )
  {
    const count = form.querySelector( '[data-timer-bar-tag-count]' );
    const newTags = form.querySelector( '[data-timer-bar-new-tags]' );

    if ( count === null )
    {
      return;
    }

    const update = () =>
    {
      const checked = form.querySelectorAll( '[data-timer-bar-tag]:checked' ).length;
      const typed = newTags === null ? 0 : newTags.value.split( ',' ).filter( ( name ) => name.trim().length > 1 ).length;
      const total = checked + typed;

      count.textContent = String( total );
      count.hidden = total === 0;
    };

    form.addEventListener( 'change', update );
    if ( newTags !== null )
    {
      newTags.addEventListener( 'input', update );
      newTags.addEventListener( 'keydown', ( event ) =>
      {
        // Enter in the tag field should not start the timer while the menu is open.
        if ( event.key === 'Enter' )
        {
          event.preventDefault();
        }
      } );
    }
  }

  /**
   * Wires up the start form: suggestions fill in project and activity, the activity picker
   * follows the project picker, and the billable and tag controls keep their state.
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
      form.dispatchEvent( new Event( 'timer-bar:selection' ) );
    } );

    projectSelect.addEventListener( 'change', () => filterActivities( projectSelect, activitySelect, suggestions ) );
    filterActivities( projectSelect, activitySelect, suggestions );
    initBillable( form, projectSelect, activitySelect );
    initTags( form );
  }

  /**
   * Adds a continue button to every entry row on "My times" that does not have one yet.
   *
   * @param {HTMLFormElement} form The hidden continue form.
   * @returns {void}
   */
  function addContinueButtons( form )
  {
    const idField = form.querySelector( '[data-timer-bar-continue-id]' );

    document.querySelectorAll( ENTRY_ROW_SELECTOR + ':not([' + CONTINUE_MARKER + '])' ).forEach( ( row ) =>
    {
      const match = ENTRY_ID_PATTERN.exec( row.dataset.href ?? '' );
      const cell = row.querySelector( 'td.col_actions' ) ?? row.lastElementChild;

      row.setAttribute( CONTINUE_MARKER, '' );
      if ( match === null || cell === null || idField === null )
      {
        return;
      }

      const button = document.createElement( 'button' );
      button.type = 'button';
      button.className = 'btn btn-sm btn-ghost-success btn-icon timer-bar-continue';
      button.title = form.dataset.label;
      button.setAttribute( 'aria-label', form.dataset.label );
      button.innerHTML = '<i class="fas fa-play"></i>';
      button.addEventListener( 'click', ( event ) =>
      {
        // The row itself opens the edit dialog; the button must not.
        event.preventDefault();
        event.stopPropagation();
        idField.value = match[ 1 ];
        form.submit();
      } );

      cell.prepend( button );
    } );
  }

  /**
   * Adds continue buttons now and whenever Kimai reloads the entry table.
   *
   * @param {HTMLFormElement} form The hidden continue form.
   * @returns {void}
   */
  function initContinue( form )
  {
    addContinueButtons( form );
    new MutationObserver( () => addContinueButtons( form ) ).observe( document.body, { childList: true, subtree: true } );
  }

  document.addEventListener( 'DOMContentLoaded', () =>
  {
    document.querySelectorAll( FORM_SELECTOR ).forEach( initForm );
    document.querySelectorAll( CLOCK_SELECTOR ).forEach( startClock );
    document.querySelectorAll( CONTINUE_FORM_SELECTOR ).forEach( initContinue );
  } );
} )();
