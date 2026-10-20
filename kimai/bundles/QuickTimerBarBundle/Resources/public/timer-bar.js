/**
 * Behaviour of the Kimai quick start bar: places it below the top bar, fills in project and
 * activity from recent entries, offers only the activities that fit the chosen project,
 * switches between timer mode (start now) and manual mode (add a finished record), saves
 * changes to the running entry as they are made, runs the clocks of the running entry here
 * and wherever Kimai shows it, marks the running entry in the record lists, and reloads the
 * page when another tab or window starts or stops a record.
 */
( function ()
{
  'use strict';

  /**
   * Selector of the quick start bar.
   *
   * @type {string}
   */
  const BAR_SELECTOR = '[data-timer-bar]';

  /**
   * Selector of Kimai's own start button and running clock in the top navigation.
   *
   * @type {string}
   */
  const NAVBAR_TIMER_SELECTOR = '.ticktac';

  /**
   * Selector of the page area below the top bar, which holds the page title and content.
   *
   * @type {string}
   */
  const PAGE_WRAPPER_SELECTOR = '.page-wrapper';

  /**
   * Classes of the row that holds the bar on narrow screens; container-fluid gives it the
   * same side margins as the page.
   *
   * @type {string}
   */
  const ROW_CLASS = 'container-fluid timer-bar-row';

  /**
   * Selector of the row that holds the placed bar.
   *
   * @type {string}
   */
  const ROW_SELECTOR = '.timer-bar-row';

  /**
   * Event Kimai dispatches after it replaced the page content with a freshly fetched copy,
   * for example when the entry table is filtered or reloaded.
   *
   * @type {string}
   */
  const KIMAI_RELOADED_EVENT = 'kimai.reloadedContent';

  /**
   * Spacing utility class the bar is rendered with, for pages without the usual layout.
   *
   * @type {string}
   */
  const CONTENT_SPACING_CLASS = 'mb-3';

  /**
   * Class that hides Kimai's own start button while the bar replaces it.
   *
   * @type {string}
   */
  const REPLACED_CLASS = 'timer-bar-replaced';

  /**
   * Events Kimai dispatches when it starts or stops a record without reloading the page.
   *
   * @type {string[]}
   */
  const KIMAI_RECORD_EVENTS = [ 'kimai.timesheetStart', 'kimai.timesheetStop' ];

  /**
   * Event Kimai listens to for reloading its lists after a record changed.
   *
   * @type {string}
   */
  const KIMAI_UPDATE_EVENT = 'kimai.timesheetUpdate';

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
   * Type of the "+" form that creates a project; the other one creates an activity.
   *
   * @type {string}
   */
  const QUICK_CREATE_PROJECT = 'project';

  /**
   * Milliseconds between clock updates.
   *
   * @type {number}
   */
  const TICK_INTERVAL = 1000;

  /**
   * Selector of the durations Kimai shows for running records, for example in the record
   * lists and dashboard widgets. Kimai's own script rewrites them every ten seconds.
   *
   * @type {string}
   */
  const KIMAI_SINCE_SELECTOR = '[data-since]:not([data-since=""])';

  /**
   * Attribute that takes over the start time of a running duration from Kimai's data-since,
   * so this script ticks it instead of Kimai's.
   *
   * @type {string}
   */
  const SINCE_ATTRIBUTE = 'data-timer-bar-since';

  /**
   * Class of the digits between the colons of a ticking clock.
   *
   * @type {string}
   */
  const CLOCK_PART_CLASS = 'timer-bar-clock-part';

  /**
   * Class of the ticking colons of a running clock.
   *
   * @type {string}
   */
  const COLON_CLASS = 'timer-bar-colon';

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
   * Value of the form's data-state while a record runs.
   *
   * @type {string}
   */
  const STATE_RUNNING = 'running';

  /**
   * Value of the form's data-state for the form that starts or adds a record.
   *
   * @type {string}
   */
  const STATE_IDLE = 'idle';

  /**
   * The bar's modes: timer mode starts a record now and shows the running one, manual mode
   * adds a finished record from a date, start and end time.
   *
   * @type {{timer: string, manual: string}}
   */
  const MODES = { timer: 'timer', manual: 'manual' };

  /**
   * Browser storage key that remembers the mode the user last chose.
   *
   * @type {string}
   */
  const MODE_STORAGE_KEY = 'kimai.quickTimerBar.mode';

  /**
   * Selector of the mode buttons; their data-timer-bar-mode holds the mode they choose.
   *
   * @type {string}
   */
  const MODE_BUTTON_SELECTOR = '[data-timer-bar-mode]';

  /**
   * Selector of the inputs that only manual mode uses: the end times. They are required there
   * and off in timer mode.
   *
   * @type {string}
   */
  const MANUAL_INPUT_SELECTOR = 'input[data-timer-bar-manual]';

  /**
   * Selector of the inputs that are optional in timer mode but required in manual mode: the
   * start time of the start form, which is empty to start now.
   *
   * @type {string}
   */
  const REQUIRED_MANUAL_SELECTOR = '[data-timer-bar-required-manual]';

  /**
   * Selector of the end time of the running record.
   *
   * @type {string}
   */
  const END_TIME_SELECTOR = '[data-timer-bar-end-time]';

  /**
   * Selector of the continue buttons added to the entry rows.
   *
   * @type {string}
   */
  const CONTINUE_BUTTON_SELECTOR = '.timer-bar-continue';

  /**
   * Class of the running entry's row in the record lists.
   *
   * @type {string}
   */
  const RUNNING_ROW_CLASS = 'timer-bar-running-row';

  /**
   * Class of the pulsing button that replaces the running entry's row buttons.
   *
   * @type {string}
   */
  const RUNNING_BUTTON_CLASS = 'timer-bar-now';

  /**
   * Selector of the parts of the running entry's row that keep their own click: the bulk
   * selection checkbox and links.
   *
   * @type {string}
   */
  const ROW_CONTROL_SELECTOR = 'td.multiCheckbox, a[href]';

  /**
   * Class that briefly highlights the bar when the running entry's row sends the user there.
   *
   * @type {string}
   */
  const ATTENTION_CLASS = 'timer-bar-attention';

  /**
   * Media query for users who prefer less motion.
   *
   * @type {string}
   */
  const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

  /**
   * Milliseconds to wait after a change before saving the running entry, so quick changes
   * are saved together.
   *
   * @type {number}
   */
  const SAVE_DELAY = 400;

  /**
   * Selector of the start form's date, which follows today while the page stays open. Its
   * data-timezone holds the user's time zone, in which the day changes.
   *
   * @type {string}
   */
  const TODAY_SELECTOR = '[data-timer-bar-today]';

  /**
   * Milliseconds between checks whether the day has changed.
   *
   * @type {number}
   */
  const TODAY_CHECK_INTERVAL = 60000;

  /**
   * Milliseconds between checks whether another tab or window started or stopped a record.
   *
   * @type {number}
   */
  const STATUS_CHECK_INTERVAL = 30000;

  /**
   * Selector of the places whose fields may change without the page counting as edited:
   * the running bar, which saves its changes itself (except its end time), Kimai's dialogs,
   * which keep the page from reloading while open, and Kimai's search filters, which only
   * apply when submitted.
   *
   * @type {string}
   */
  const UNTRACKED_EDIT_SELECTOR = '[data-timer-bar-form][data-state="running"], .modal, .searchform';

  /**
   * Selector of an open Kimai dialog.
   *
   * @type {string}
   */
  const OPEN_MODAL_SELECTOR = '.modal.show';

  /**
   * Selector of the fields a user may be typing in.
   *
   * @type {string}
   */
  const EDITABLE_SELECTOR = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])';

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
   * Creates one ticking colon.
   *
   * @returns {HTMLSpanElement}
   */
  function createColon()
  {
    const colon = document.createElement( 'span' );
    colon.className = COLON_CLASS;
    colon.textContent = ':';

    return colon;
  }

  /**
   * Lets every colon's animation start at the last full second, so all colons tick together,
   * when the digits change. Done on every tick, because the browser restarts animations, for
   * example when Kimai reloads the list along with this stylesheet.
   *
   * @returns {void}
   */
  function alignColons()
  {
    const start = document.timeline.currentTime - Date.now() % TICK_INTERVAL;

    document.querySelectorAll( '.' + COLON_CLASS ).forEach( ( colon ) =>
    {
      colon.getAnimations().forEach( ( animation ) => { animation.startTime = start; } );
    } );
  }

  /**
   * Shows a duration in a clock, with ticking colons. Only the digits that changed are
   * replaced, so the colons keep ticking.
   *
   * @param {HTMLElement} element The clock.
   * @param {string} text The duration, for example 1:05:09.
   * @returns {void}
   */
  function renderClock( element, text )
  {
    if ( element.dataset.timerBarText === text )
    {
      return;
    }

    const parts = text.split( ':' );
    const current = element.querySelectorAll( '.' + CLOCK_PART_CLASS );
    element.dataset.timerBarText = text;

    if ( current.length === parts.length )
    {
      parts.forEach( ( part, index ) => { current[ index ].textContent = part; } );
      return;
    }

    element.replaceChildren( ...parts.flatMap( ( part, index ) =>
    {
      const digits = document.createElement( 'span' );
      digits.className = CLOCK_PART_CLASS;
      digits.textContent = part;

      return index === 0 ? [ digits ] : [ createColon(), digits ];
    } ) );
  }

  /**
   * Formats a running duration the way Kimai does, by the user's duration format.
   *
   * @param {string} since The start of the record, as an ISO 8601 date and time.
   * @returns {string}
   */
  function formatKimaiDuration( since )
  {
    try
    {
      return window.kimai.getPlugin( 'date' ).formatDuration( since );
    }
    catch ( error )
    {
      // Without Kimai's date plugin: hours and minutes, Kimai's default format.
      return formatClock( ( Date.now() - Date.parse( since ) ) / 1000 ).replace( /:\d{2}$/, '' );
    }
  }

  /**
   * Takes the running durations Kimai shows over from Kimai's script. The one in Kimai's
   * hidden start button stays with Kimai, which keeps the browser tab title up to date with it.
   *
   * @returns {void}
   */
  function adoptKimaiDurations()
  {
    document.querySelectorAll( KIMAI_SINCE_SELECTOR ).forEach( ( element ) =>
    {
      if ( element.closest( NAVBAR_TIMER_SELECTOR ) !== null )
      {
        return;
      }

      element.setAttribute( SINCE_ATTRIBUTE, element.dataset.since );
      element.removeAttribute( 'data-since' );
    } );
  }

  /**
   * Updates every running clock on the page: the bar's, with seconds, and Kimai's durations,
   * in Kimai's format.
   *
   * @returns {void}
   */
  function tickClocks()
  {
    adoptKimaiDurations();

    document.querySelectorAll( CLOCK_SELECTOR ).forEach( ( clock ) =>
    {
      const begin = Date.parse( clock.dataset.timerBarBegin );
      if ( !Number.isNaN( begin ) )
      {
        renderClock( clock, formatClock( ( Date.now() - begin ) / 1000 ) );
      }
    } );

    document.querySelectorAll( '[' + SINCE_ATTRIBUTE + ']' ).forEach( ( element ) =>
    {
      renderClock( element, formatKimaiDuration( element.getAttribute( SINCE_ATTRIBUTE ) ) );
    } );

    alignColons();
  }

  /**
   * Keeps every running clock up to date, on the full seconds, so the digits change together
   * with the ticking colons. Kimai itself keeps the duration in the browser tab title.
   *
   * @returns {void}
   */
  function startTicking()
  {
    tickClocks();
    window.setTimeout( startTicking, TICK_INTERVAL - Date.now() % TICK_INTERVAL );
  }

  /**
   * Reads the recent entries that the bar embeds as JSON.
   *
   * @param {HTMLFormElement} form One of the bar's forms.
   * @returns {{description: string, projectId: number, activityId: number}[]}
   */
  function readSuggestions( form )
  {
    const element = ( form.closest( BAR_SELECTOR ) ?? form ).querySelector( '[data-timer-bar-suggestions]' );

    try
    {
      return element === null ? [] : JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Quick start bar: invalid suggestions', error );
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
      else
      {
        showBillable( toggle, value.value === BILLABLE.yes );
      }
    };

    toggle.addEventListener( 'click', () =>
    {
      const billable = toggle.getAttribute( 'aria-pressed' ) !== 'true';
      value.value = billable ? BILLABLE.yes : BILLABLE.no;
      showBillable( toggle, billable );
      form.dispatchEvent( new Event( 'timer-bar:selection' ) );
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

    update();
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
   * Returns the option group of a customer in the project picker, creating it when missing.
   *
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {string} customer The customer name.
   * @returns {HTMLOptGroupElement}
   */
  function findCustomerGroup( projectSelect, customer )
  {
    const existing = Array.from( projectSelect.querySelectorAll( 'optgroup' ) ).find( ( group ) => group.label === customer );
    if ( existing !== undefined )
    {
      return existing;
    }

    const group = document.createElement( 'optgroup' );
    group.label = customer;
    projectSelect.append( group );

    return group;
  }

  /**
   * Adds a created project or activity to its picker, unless it is there already, and selects it.
   *
   * @param {string} type Either "project" or "activity".
   * @param {Object} item The created item as the server describes it.
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {HTMLSelectElement} activitySelect The activity picker.
   * @param {{projectId: number, activityId: number}[]} suggestions Recent entries, most recent first.
   * @returns {void}
   */
  function selectCreated( type, item, projectSelect, activitySelect, suggestions )
  {
    const select = type === QUICK_CREATE_PROJECT ? projectSelect : activitySelect;
    const value = String( item.id );

    if ( select.querySelector( 'option[value="' + value + '"]' ) === null )
    {
      const option = new Option( item.name, value );
      option.dataset.billable = item.billable ? '1' : '0';

      if ( type === QUICK_CREATE_PROJECT )
      {
        option.dataset.globalActivities = item.globalActivities ? '1' : '0';
        findCustomerGroup( projectSelect, item.customer ).append( option );
      }
      else
      {
        option.dataset.project = String( item.projectId );
        activitySelect.append( option );
      }
    }

    select.value = value;
    filterActivities( projectSelect, activitySelect, suggestions );
    select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
  }

  /**
   * Wires up the "+" buttons next to the project and activity pickers: a small form that
   * creates a project (with a new or existing customer) or an activity, and selects it.
   *
   * @param {HTMLFormElement} form The bar form, which carries the token.
   * @param {HTMLSelectElement} projectSelect The project picker.
   * @param {HTMLSelectElement} activitySelect The activity picker.
   * @param {{projectId: number, activityId: number}[]} suggestions Recent entries, most recent first.
   * @returns {void}
   */
  function initQuickCreate( form, projectSelect, activitySelect, suggestions )
  {
    form.querySelectorAll( '[data-timer-bar-create]' ).forEach( ( menu ) =>
    {
      const dropdown = menu.closest( '.dropdown' );
      const toggle = dropdown.querySelector( '[data-bs-toggle="dropdown"]' );
      const name = menu.querySelector( '[data-timer-bar-create-name]' );
      const customer = menu.querySelector( '[data-timer-bar-create-customer]' );
      const error = menu.querySelector( '[data-timer-bar-create-error]' );
      const type = menu.dataset.timerBarCreate;

      const create = async () =>
      {
        const body = new FormData();
        body.append( '_token', form.querySelector( 'input[name="_token"]' ).value );
        body.append( 'name', name.value );
        body.append( 'customer', customer === null ? '' : customer.value );
        body.append( 'project', projectSelect.value );

        try
        {
          const response = await fetch( menu.dataset.url, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' } );
          const result = await response.json();

          error.textContent = response.ok ? '' : ( result.message ?? '' );
          error.hidden = response.ok;
          if ( !response.ok )
          {
            return;
          }

          selectCreated( type, result, projectSelect, activitySelect, suggestions );
          menu.querySelectorAll( 'input' ).forEach( ( input ) => { input.value = ''; } );
          toggle.click();
        }
        catch ( exception )
        {
          console.error( 'Quick start bar: creating failed', exception );
        }
      };

      // The small form lives inside the bar form: its typing must not save the running record,
      // and Enter must create instead of submitting the bar.
      [ 'change', 'input' ].forEach( ( eventName ) => menu.addEventListener( eventName, ( event ) => event.stopPropagation() ) );
      menu.addEventListener( 'keydown', ( event ) =>
      {
        if ( event.key === 'Enter' )
        {
          event.preventDefault();
          event.stopPropagation();
          create();
        }
      } );
      menu.querySelector( '[data-timer-bar-create-submit]' ).addEventListener( 'click', create );
      dropdown.addEventListener( 'shown.bs.dropdown', () => name.focus() );
    } );
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
    initQuickCreate( form, projectSelect, activitySelect, suggestions );

    if ( form.dataset.state === STATE_RUNNING )
    {
      initAutoSave( form );
      return;
    }

    followToday( form );
  }

  /**
   * Returns today's date as Y-m-d in a time zone, or in the browser's time zone when the
   * given one is unknown.
   *
   * @param {string} timezone The time zone, for example Europe/Amsterdam.
   * @returns {string}
   */
  function getToday( timezone )
  {
    const options = { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' };
    let parts;

    try
    {
      parts = new Intl.DateTimeFormat( 'en', options ).formatToParts( new Date() );
    }
    catch ( error )
    {
      delete options.timeZone;
      parts = new Intl.DateTimeFormat( 'en', options ).formatToParts( new Date() );
    }

    const part = ( type ) => parts.find( ( item ) => item.type === type ).value;

    return part( 'year' ) + '-' + part( 'month' ) + '-' + part( 'day' );
  }

  /**
   * Moves the start form's date on to the new day when the page stays open past midnight, for
   * example overnight. A date the user chose stays as it is.
   *
   * @param {HTMLFormElement} form The start form.
   * @returns {void}
   */
  function followToday( form )
  {
    const date = form.querySelector( TODAY_SELECTOR );
    if ( date === null )
    {
      return;
    }

    let today = date.value;
    const update = () =>
    {
      const now = getToday( date.dataset.timezone );
      if ( now === today )
      {
        return;
      }

      if ( date.value === today )
      {
        date.value = now;
      }

      today = now;
    };

    window.setInterval( update, TODAY_CHECK_INTERVAL );
    document.addEventListener( 'visibilitychange', update );
  }

  /**
   * Returns the mode the user last chose, or timer mode when none is remembered.
   *
   * @returns {string}
   */
  function readStoredMode()
  {
    try
    {
      const mode = window.localStorage.getItem( MODE_STORAGE_KEY );
      return Object.values( MODES ).includes( mode ) ? mode : MODES.timer;
    }
    catch ( error )
    {
      return MODES.timer;
    }
  }

  /**
   * Remembers the chosen mode for the next page, when the browser allows it.
   *
   * @param {string} mode The chosen mode.
   * @returns {void}
   */
  function storeMode( mode )
  {
    try
    {
      window.localStorage.setItem( MODE_STORAGE_KEY, mode );
    }
    catch ( error )
    {
      // Without storage the bar simply starts in timer mode on the next page.
    }
  }

  /**
   * Returns the label of the continue buttons for the bar's current mode.
   *
   * @param {HTMLFormElement} continueForm The hidden continue form, which carries both labels.
   * @returns {string}
   */
  function getContinueLabel( continueForm )
  {
    const bar = continueForm.closest( BAR_SELECTOR );
    const manual = bar !== null && bar.dataset.mode === MODES.manual;

    return manual ? continueForm.dataset.labelManual : continueForm.dataset.labelTimer;
  }

  /**
   * Shows a label on a continue button.
   *
   * @param {HTMLButtonElement} button The continue button.
   * @param {string} label What the button does in the current mode.
   * @returns {void}
   */
  function labelContinueButton( button, label )
  {
    button.title = label;
    button.setAttribute( 'aria-label', label );
  }

  /**
   * Switches the bar to a mode. The stylesheet shows the matching fields and buttons: manual
   * mode has end times, and the running record a save button instead of its clock and stop
   * button. This turns the end times on in manual mode, where they and the start time are
   * required, stops showing a copied record, and shows on the start form's main button
   * whether it starts or adds.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @param {string} mode One of MODES.
   * @returns {void}
   */
  function applyMode( bar, mode )
  {
    const manual = mode === MODES.manual;
    const submit = bar.querySelector( '[data-timer-bar-submit]' );
    const continueForm = bar.querySelector( CONTINUE_FORM_SELECTOR );

    bar.dataset.mode = mode;
    delete bar.dataset.copying;
    bar.querySelectorAll( MODE_BUTTON_SELECTOR ).forEach( ( button ) =>
    {
      button.setAttribute( 'aria-pressed', String( button.dataset.timerBarMode === mode ) );
    } );
    bar.querySelectorAll( MANUAL_INPUT_SELECTOR ).forEach( ( input ) =>
    {
      input.disabled = !manual;
      input.required = manual;
    } );
    bar.querySelectorAll( REQUIRED_MANUAL_SELECTOR ).forEach( ( input ) =>
    {
      input.required = manual;
    } );

    if ( submit !== null )
    {
      const title = manual ? submit.dataset.titleManual : submit.dataset.titleTimer;
      submit.querySelectorAll( '[data-timer-bar-icon]' ).forEach( ( icon ) =>
      {
        icon.hidden = icon.dataset.timerBarIcon !== mode;
      } );
      submit.title = title;
      submit.setAttribute( 'aria-label', title );
    }

    if ( continueForm !== null )
    {
      const label = getContinueLabel( continueForm );
      document.querySelectorAll( CONTINUE_BUTTON_SELECTOR ).forEach( ( button ) => labelContinueButton( button, label ) );
    }
  }

  /**
   * Starts the bar in the mode the user last chose, and wires up the mode buttons. Without
   * mode buttons, when Kimai's tracking mode lets users enter no times, it stays in timer mode.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @returns {void}
   */
  function initModes( bar )
  {
    applyMode( bar, bar.querySelector( MODE_BUTTON_SELECTOR ) === null ? MODES.timer : readStoredMode() );

    bar.querySelectorAll( MODE_BUTTON_SELECTOR ).forEach( ( button ) =>
    {
      button.addEventListener( 'click', () =>
      {
        applyMode( bar, button.dataset.timerBarMode );
        storeMode( button.dataset.timerBarMode );
      } );
    } );
  }

  /**
   * Fills the start form with a past record, so its date and times can be entered: the
   * description, project, activity, tags and billable setting are copied, the times cleared.
   *
   * @param {HTMLFormElement} form The start form.
   * @param {{description: string, projectId: number, activityId: number, tagIds: number[], billableMode: string}} entry The record.
   * @returns {void}
   */
  function fillForm( form, entry )
  {
    const projectSelect = form.querySelector( '[data-timer-bar-project]' );
    const billable = form.querySelector( '[data-timer-bar-billable-value]' );
    const newTags = form.querySelector( '[data-timer-bar-new-tags]' );
    const beginTime = form.querySelector( '[data-timer-bar-begin-time]' );

    form.querySelector( '[data-timer-bar-description]' ).value = entry.description;
    projectSelect.value = String( entry.projectId );
    form.querySelector( '[data-timer-bar-activity]' ).value = String( entry.activityId );
    form.querySelectorAll( '[data-timer-bar-tag]' ).forEach( ( checkbox ) =>
    {
      checkbox.checked = entry.tagIds.includes( Number( checkbox.value ) );
    } );
    form.querySelectorAll( '.timer-bar-times .timer-bar-time' ).forEach( ( input ) => { input.value = ''; } );

    if ( newTags !== null )
    {
      newTags.value = '';
    }

    if ( billable !== null )
    {
      billable.value = entry.billableMode;
    }

    // Lets the activity picker, tag count and billable toggle follow the new values.
    projectSelect.dispatchEvent( new Event( 'change', { bubbles: true } ) );
    form.dispatchEvent( new Event( 'timer-bar:selection' ) );
    showError( form, '' );

    if ( beginTime !== null )
    {
      beginTime.focus();
    }
  }

  /**
   * Copies a past record into the start form in manual mode, instead of starting it now. While
   * a record runs, the start form takes its place until the mode is switched.
   *
   * @param {HTMLFormElement} continueForm The hidden continue form, which knows where to ask.
   * @param {string} id The record ID.
   * @returns {Promise<void>}
   */
  async function copyEntry( continueForm, id )
  {
    const form = continueForm.closest( BAR_SELECTOR ).querySelector( FORM_SELECTOR + '[data-state="' + STATE_IDLE + '"]' );
    const url = new URL( continueForm.dataset.entryUrl, window.location.href );
    url.searchParams.set( 'timesheet', id );

    try
    {
      const response = await fetch( url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' } );
      const result = await response.json();

      if ( !response.ok )
      {
        showError( form, result.message ?? '' );
        return;
      }

      continueForm.closest( BAR_SELECTOR ).dataset.copying = 'true';
      fillForm( form, result );
    }
    catch ( error )
    {
      console.error( 'Quick start bar: reading the record failed', error );
    }
  }

  /**
   * Shows or clears the error message below a bar form.
   *
   * @param {HTMLFormElement} form The bar form.
   * @param {string} message The message, or an empty string to clear it.
   * @returns {void}
   */
  function showError( form, message )
  {
    const element = form.querySelector( '[data-timer-bar-error]' );
    if ( element !== null )
    {
      element.textContent = message;
      element.hidden = message === '';
    }
  }

  /**
   * Saves the running entry with the current field values, and moves the clock when the start
   * time changed.
   *
   * @param {HTMLFormElement} form The running bar form.
   * @returns {Promise<void>}
   */
  async function saveRunning( form )
  {
    const newTags = form.querySelector( '[data-timer-bar-new-tags]' );
    const hasNewTags = newTags !== null && newTags.value.trim() !== '';

    try
    {
      const response = await fetch( form.action, {
        method: 'POST',
        body: new FormData( form ),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      } );
      const result = await response.json();

      if ( !response.ok )
      {
        showError( form, result.message ?? '' );
        return;
      }

      showError( form, '' );
      // Kimai reloads its lists of records on this event, so "My times" shows the change.
      document.dispatchEvent( new CustomEvent( KIMAI_UPDATE_EVENT ) );
      const clock = form.querySelector( CLOCK_SELECTOR );
      if ( clock !== null && result.begin )
      {
        clock.dataset.timerBarBegin = result.begin;
        clock.setAttribute( 'datetime', result.begin );
      }

      // New tags now exist; reload so they show up as normal choices.
      if ( hasNewTags )
      {
        window.location.reload();
      }
    }
    catch ( error )
    {
      console.error( 'Quick start bar: saving the running entry failed', error );
    }
  }

  /**
   * Saves changes to the running entry as they are made: there is no save button, and Enter
   * saves too. Only the stop button submits the form, or in manual mode the button that saves
   * it with its end time; there Enter does the same.
   *
   * @param {HTMLFormElement} form The running bar form.
   * @returns {void}
   */
  function initAutoSave( form )
  {
    let timer = 0;
    const schedule = () =>
    {
      window.clearTimeout( timer );
      timer = window.setTimeout( () => saveRunning( form ), SAVE_DELAY );
    };

    // The end time only counts when the record is saved as finished.
    form.addEventListener( 'change', ( event ) =>
    {
      if ( !( event.target instanceof Element && event.target.matches( END_TIME_SELECTOR ) ) )
      {
        schedule();
      }
    } );
    form.addEventListener( 'timer-bar:selection', schedule );

    // Enter in a field would submit the form with its first button, the stop button. Leave
    // the field instead, so other scripts complete short times; then save the change, or in
    // manual mode save the record with its end time.
    form.addEventListener( 'keydown', ( event ) =>
    {
      if ( event.key !== 'Enter' || !( event.target instanceof HTMLInputElement ) )
      {
        return;
      }

      event.preventDefault();
      event.target.blur();

      const finish = form.querySelector( '[data-timer-bar-finish]' );
      if ( form.closest( BAR_SELECTOR )?.dataset.mode === MODES.manual && finish !== null )
      {
        window.clearTimeout( timer );
        window.setTimeout( () => form.requestSubmit( finish ) );
        return;
      }

      schedule();
    } );
  }

  /**
   * Adds a continue button to every entry row on "My times" that does not have one yet. In
   * timer mode it starts the record again now; in manual mode it copies the record into the
   * bar, so its date and times can be entered.
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
      if ( match === null || cell === null || idField === null || row.classList.contains( RUNNING_ROW_CLASS ) )
      {
        return;
      }

      const button = document.createElement( 'button' );
      button.type = 'button';
      button.className = 'btn btn-sm btn-icon timer-bar-continue';
      labelContinueButton( button, getContinueLabel( form ) );
      button.innerHTML = '<i class="fas fa-play"></i>';
      button.addEventListener( 'click', ( event ) =>
      {
        // The row itself opens the edit dialog; the button must not.
        event.preventDefault();
        event.stopPropagation();

        if ( form.closest( BAR_SELECTOR )?.dataset.mode === MODES.manual )
        {
          copyEntry( form, match[ 1 ] );
          return;
        }

        idField.value = match[ 1 ];
        form.submit();
      } );

      cell.prepend( button );
    } );
  }

  /**
   * Marks the running entry's row in the record lists, and replaces its buttons with a
   * pulsing one: continuing or changing a running entry from its row does not fit, the bar
   * does that.
   *
   * @param {HTMLElement} bar The quick start bar of a running entry.
   * @returns {void}
   */
  function markRunningRows( bar )
  {
    const label = bar.dataset.labelRunning ?? '';

    document.querySelectorAll( ENTRY_ROW_SELECTOR + ':not(.' + RUNNING_ROW_CLASS + ')' ).forEach( ( row ) =>
    {
      const match = ENTRY_ID_PATTERN.exec( row.dataset.href ?? '' );
      const cell = row.querySelector( 'td.col_actions' ) ?? row.lastElementChild;
      if ( match === null || match[ 1 ] !== bar.dataset.timerBarRunning || cell === null )
      {
        return;
      }

      const button = document.createElement( 'button' );
      button.type = 'button';
      button.className = 'btn btn-sm btn-success btn-icon ' + RUNNING_BUTTON_CLASS;
      button.title = label;
      button.setAttribute( 'aria-label', label );
      button.innerHTML = '<i class="fas fa-play"></i>';

      row.classList.add( RUNNING_ROW_CLASS );
      row.title = label;
      cell.prepend( button );
    } );
  }

  /**
   * Brings the running entry into view in the bar, with the focus on its description, and
   * briefly highlights the bar. A copied record in manual mode makes way for it.
   *
   * @param {HTMLElement} bar The quick start bar of a running entry.
   * @returns {void}
   */
  function showRunning( bar )
  {
    const description = bar.querySelector( FORM_SELECTOR + '[data-state="' + STATE_RUNNING + '"] [data-timer-bar-description]' );
    if ( description === null )
    {
      return;
    }

    delete bar.dataset.copying;
    bar.scrollIntoView( { behavior: window.matchMedia( REDUCED_MOTION_QUERY ).matches ? 'auto' : 'smooth', block: 'center' } );
    description.focus( { preventScroll: true } );

    // Restarts the highlight when it is still running from an earlier click.
    bar.classList.remove( ATTENTION_CLASS );
    void bar.offsetWidth;
    bar.classList.add( ATTENTION_CLASS );
  }

  /**
   * Sends clicks on the running entry's row to the bar, instead of Kimai's edit dialog or
   * inline editing.
   *
   * @param {HTMLElement} bar The quick start bar of a running entry.
   * @returns {void}
   */
  function initRunningRows( bar )
  {
    // Capture phase: decides about the click before Kimai's own row handling.
    document.addEventListener( 'click', ( event ) =>
    {
      const target = event.target instanceof Element ? event.target : null;
      if ( target === null || target.closest( 'tr.' + RUNNING_ROW_CLASS ) === null || target.closest( ROW_CONTROL_SELECTOR ) !== null )
      {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      showRunning( bar );
    }, true );

    bar.addEventListener( 'animationend', ( event ) =>
    {
      if ( event.target === bar )
      {
        bar.classList.remove( ATTENTION_CLASS );
      }
    } );
  }

  /**
   * Marks the running entry's row and adds continue buttons, now and whenever Kimai reloads
   * the entry table.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @returns {void}
   */
  function initRows( bar )
  {
    const running = ( bar.dataset.timerBarRunning ?? '' ) !== '';
    const continueForm = bar.querySelector( CONTINUE_FORM_SELECTOR );
    if ( !running && continueForm === null )
    {
      return;
    }

    // The running row first, so it does not get a continue button.
    const decorate = () =>
    {
      if ( running )
      {
        markRunningRows( bar );
      }

      if ( continueForm !== null )
      {
        addContinueButtons( continueForm );
      }
    };

    if ( running )
    {
      initRunningRows( bar );
    }

    decorate();
    new MutationObserver( decorate ).observe( document.body, { childList: true, subtree: true } );
  }

  /**
   * Creates the row that holds the bar: directly below the top bar, above the page title and
   * the page's own buttons. Falls back to where the bar was rendered.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @returns {HTMLElement}
   */
  function createRow( bar )
  {
    const row = document.createElement( 'div' );
    row.className = ROW_CLASS;

    const pageWrapper = document.querySelector( PAGE_WRAPPER_SELECTOR );
    if ( pageWrapper !== null )
    {
      pageWrapper.prepend( row );
    }
    else
    {
      bar.before( row );
    }

    return row;
  }

  /**
   * Shows the bar on its own row directly below the top bar, in place of Kimai's own start
   * button.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @returns {void}
   */
  function initPlacement( bar )
  {
    createRow( bar ).append( bar );
    bar.classList.remove( CONTENT_SPACING_CLASS );
    document.querySelectorAll( NAVBAR_TIMER_SELECTOR ).forEach( ( timer ) => timer.classList.add( REPLACED_CLASS ) );
  }

  /**
   * Reloads the page when Kimai starts or stops a record elsewhere on the page, for example
   * with its "repeat" action, so the bar shows the right state.
   *
   * @returns {void}
   */
  function followKimaiEvents()
  {
    KIMAI_RECORD_EVENTS.forEach( ( name ) => document.addEventListener( name, () => window.location.reload() ) );
  }

  /**
   * Fetches which record runs: its ID, or an empty string when nothing runs. Returns null when
   * the answer is unusable, for example after the login expired.
   *
   * @param {string} url The address of the status route.
   * @returns {Promise<?string>}
   */
  async function fetchRunningId( url )
  {
    try
    {
      const response = await fetch( url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' } );
      if ( !response.ok )
      {
        return null;
      }

      const result = await response.json();
      return typeof result.running === 'number' ? ( result.running > 0 ? String( result.running ) : '' ) : null;
    }
    catch ( error )
    {
      return null;
    }
  }

  /**
   * Tells whether the page can reload without losing the user's work: it is in view, no
   * dialog is open, the user is not typing, and no field outside the running bar was edited.
   *
   * @param {boolean} edited Whether a field outside the running bar was edited.
   * @returns {boolean}
   */
  function canReload( edited )
  {
    const active = document.activeElement;

    return document.visibilityState === 'visible'
      && !edited
      && document.querySelector( OPEN_MODAL_SELECTOR ) === null
      && !( active instanceof Element && active.matches( EDITABLE_SELECTOR ) );
  }

  /**
   * Reloads the page when another tab or window started or stopped a record, so the bar shows
   * the current state. Checks periodically while the page is in view, and when it comes back
   * into view or gets the focus. While the reload would lose the user's work, it waits for
   * the next check.
   *
   * @param {HTMLElement} bar The quick start bar.
   * @returns {void}
   */
  function followOtherTabs( bar )
  {
    const url = bar.dataset.statusUrl ?? '';
    if ( url === '' )
    {
      return;
    }

    const shown = bar.dataset.timerBarRunning ?? '';
    let edited = false;
    let checking = false;

    const markEdited = ( event ) =>
    {
      if ( event.target instanceof Element
        && ( event.target.closest( UNTRACKED_EDIT_SELECTOR ) === null || event.target.matches( END_TIME_SELECTOR ) ) )
      {
        edited = true;
      }
    };

    const check = async () =>
    {
      if ( checking || document.visibilityState !== 'visible' )
      {
        return;
      }

      checking = true;
      const running = await fetchRunningId( url );
      checking = false;
      if ( running !== null && running !== shown && canReload( edited ) )
      {
        window.location.reload();
      }
    };

    [ 'input', 'change' ].forEach( ( name ) => document.addEventListener( name, markEdited ) );
    window.setInterval( check, STATUS_CHECK_INTERVAL );
    window.addEventListener( 'focus', check );
    document.addEventListener( 'visibilitychange', check );
  }

  /**
   * Removes the copies of the bar that come with content Kimai reloads into the page; the bar
   * already placed below the top bar stays.
   *
   * @returns {void}
   */
  function removeReloadedBars()
  {
    document.querySelectorAll( BAR_SELECTOR ).forEach( ( bar ) =>
    {
      if ( bar.closest( ROW_SELECTOR ) === null )
      {
        bar.remove();
      }
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () =>
  {
    document.querySelectorAll( BAR_SELECTOR ).forEach( initPlacement );
    if ( document.querySelector( BAR_SELECTOR ) !== null )
    {
      followKimaiEvents();
      document.addEventListener( KIMAI_RELOADED_EVENT, removeReloadedBars );
    }

    document.querySelectorAll( FORM_SELECTOR ).forEach( initForm );
    document.querySelectorAll( BAR_SELECTOR ).forEach( initModes );
    startTicking();
    document.querySelectorAll( BAR_SELECTOR ).forEach( initRows );
    // Last, so changes the setup makes to the fields do not count as the user's edits.
    document.querySelectorAll( BAR_SELECTOR ).forEach( followOtherTabs );
  } );
} )();
