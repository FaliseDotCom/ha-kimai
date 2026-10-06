/**
 * Behaviour of the Kimai quick start bar: places it below the top bar, fills in project and
 * activity from recent entries, offers only the activities that fit the chosen project, shows
 * whether the button starts a timer or adds a finished record, saves changes to the running
 * entry as they are made, and runs the clock of the running entry.
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
   * Milliseconds to wait after a change before saving the running entry, so quick changes
   * are saved together.
   *
   * @type {number}
   */
  const SAVE_DELAY = 400;

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
   * Keeps the clock of the running entry up to date. Kimai itself keeps the duration in the
   * browser tab title.
   *
   * @param {HTMLElement} clock The element that shows the elapsed time.
   * @returns {void}
   */
  function startClock( clock )
  {
    const update = () =>
    {
      const begin = Date.parse( clock.dataset.timerBarBegin );
      if ( !Number.isNaN( begin ) )
      {
        clock.textContent = formatClock( ( Date.now() - begin ) / 1000 );
      }
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
    }
    else
    {
      initSubmitLabel( form );
    }
  }

  /**
   * Shows on the main button what it will do: start a timer while no end time is entered, or
   * add a finished record once there is one.
   *
   * @param {HTMLFormElement} form The start form.
   * @returns {void}
   */
  function initSubmitLabel( form )
  {
    const endTime = form.querySelector( '[data-timer-bar-end-time]' );
    const submit = form.querySelector( '[data-timer-bar-submit]' );

    if ( endTime === null || submit === null )
    {
      return;
    }

    const update = () =>
    {
      const adds = endTime.value.trim() !== '';
      const title = adds ? submit.dataset.titleAdd : submit.dataset.titleStart;

      submit.querySelectorAll( '[data-timer-bar-icon]' ).forEach( ( icon ) =>
      {
        icon.hidden = icon.dataset.timerBarIcon !== ( adds ? 'add' : 'start' );
      } );
      submit.title = title;
      submit.setAttribute( 'aria-label', title );
    };

    endTime.addEventListener( 'input', update );
    endTime.addEventListener( 'change', update );
    update();
  }

  /**
   * Shows or clears the error message of the running bar.
   *
   * @param {HTMLFormElement} form The running bar form.
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
   * saves too. Only the stop button submits the form.
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

    form.addEventListener( 'change', schedule );
    form.addEventListener( 'timer-bar:selection', schedule );

    // Enter in a field would submit the form with its first button, the stop button. Leave
    // the field instead: that saves the change, after other scripts completed short times.
    form.addEventListener( 'keydown', ( event ) =>
    {
      if ( event.key === 'Enter' && event.target instanceof HTMLInputElement )
      {
        event.preventDefault();
        event.target.blur();
        schedule();
      }
    } );
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
    document.querySelectorAll( CLOCK_SELECTOR ).forEach( startClock );
    document.querySelectorAll( CONTINUE_FORM_SELECTOR ).forEach( initContinue );
  } );
} )();
