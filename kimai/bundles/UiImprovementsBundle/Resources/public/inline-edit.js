/**
 * Edit the user's own records directly in Kimai's record lists: click a cell to change its
 * value, press Enter or leave the field to save, and Escape to cancel. The billable cell
 * toggles on click. After a save Kimai reloads the list, so totals stay right.
 */

// Loaded with this script's version query, so a release never mixes cached files.
const { parseTime, formatTime, parseDuration, formatDuration } = await import( new URL( 'input-parsing.js' + new URL( import.meta.url ).search, import.meta.url ).href );

/**
 * The script tag, which carries the endpoint addresses, the token and the messages.
 *
 * @type {?HTMLScriptElement}
 */
const SCRIPT = [ ...document.scripts ].find( ( script ) => script.src === import.meta.url ) ?? null;

/**
 * Selector of the record rows; Kimai links each row the user may edit to its edit page.
 *
 * @type {string}
 */
const ROW_SELECTOR = 'tr[data-href]';

/**
 * Extracts the record ID from a row's edit link, on "My times" and on "All times".
 *
 * @type {RegExp}
 */
const ENTRY_ID_PATTERN = /\/timesheet\/(\d+)\/edit/;

/**
 * The field each list column edits, by the column class Kimai gives its cells. The customer
 * column picks the project, which decides the customer.
 *
 * @type {Object<string, string>}
 */
const COLUMN_FIELDS = {
  col_date: 'date',
  col_starttime: 'begin',
  col_endtime: 'end',
  col_duration: 'duration',
  col_break: 'break',
  col_customer: 'project',
  col_project: 'project',
  col_activity: 'activity',
  col_description: 'description',
  col_tags: 'tags',
  col_billable: 'billable',
};

/**
 * Class of cells that can be edited.
 *
 * @type {string}
 */
const EDITABLE_CLASS = 'inline-edit-cell';

/**
 * Class of the cell being edited.
 *
 * @type {string}
 */
const EDITING_CLASS = 'inline-edit-editing';

/**
 * Class of a cell whose change is being saved.
 *
 * @type {string}
 */
const SAVING_CLASS = 'inline-edit-saving';

/**
 * Class of the editor fields.
 *
 * @type {string}
 */
const FIELD_CLASS = 'form-control form-control-sm inline-edit-field';

/**
 * Class of the editor pickers.
 *
 * @type {string}
 */
const SELECT_CLASS = 'form-select form-select-sm inline-edit-field';

/**
 * Bootstrap class that marks a field with an invalid value.
 *
 * @type {string}
 */
const INVALID_CLASS = 'is-invalid';

/**
 * Event Kimai dispatches after it reloaded the list.
 *
 * @type {string}
 */
const KIMAI_RELOADED_EVENT = 'kimai.reloadedContent';

/**
 * Event that makes Kimai reload the list after a record changed.
 *
 * @type {string}
 */
const KIMAI_UPDATE_EVENT = 'kimai.timesheetUpdate';

/**
 * Detects a 12-hour clock in a cell's text.
 *
 * @type {RegExp}
 */
const TWELVE_HOUR_PATTERN = /[ap]\.?m\.?/i;

/**
 * Separator of tag names in the tag field.
 *
 * @type {string}
 */
const TAG_SEPARATOR = ', ';

/**
 * Activity project ID that marks a global activity.
 *
 * @type {number}
 */
const GLOBAL_ACTIVITY = 0;

/**
 * Settings from the script tag.
 *
 * @type {{entriesUrl: string, optionsUrl: string, saveUrl: string, token: string, messages: Object<string, string>}}
 */
const CONFIG = {
  entriesUrl: SCRIPT?.dataset.entriesUrl ?? '',
  optionsUrl: SCRIPT?.dataset.optionsUrl ?? '',
  saveUrl: SCRIPT?.dataset.saveUrl ?? '',
  token: SCRIPT?.dataset.token ?? '',
  messages: JSON.parse( SCRIPT?.dataset.messages ?? '{}' ),
};

/**
 * The editable records in the list, by ID, with their raw values and editable fields.
 *
 * @type {Object<string, Object>}
 */
let entries = {};

/**
 * The projects, activities and tags to pick from, loaded when first needed.
 *
 * @type {?Promise<Object>}
 */
let optionsRequest = null;

/**
 * Fetches JSON from the plugin.
 *
 * @param {string} url The address.
 * @param {RequestInit} init Request settings.
 * @returns {Promise<Object>}
 */
async function fetchJson( url, init = {} )
{
  const response = await fetch( url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, ...init } );
  const data = await response.json().catch( () => ( {} ) );

  if ( !response.ok )
  {
    throw new Error( data.message ?? CONFIG.messages.saveFailed );
  }

  return data;
}

/**
 * Returns the projects, activities and tags, loading them once.
 *
 * @returns {Promise<Object>}
 */
function loadOptions()
{
  optionsRequest ??= fetchJson( CONFIG.optionsUrl ).catch( ( error ) =>
  {
    optionsRequest = null;
    throw error;
  } );

  return optionsRequest;
}

/**
 * Shows an error the way Kimai shows its own.
 *
 * @param {string} message The message.
 * @returns {void}
 */
function showError( message )
{
  const alert = window.kimai?.getPlugin( 'alert' );
  if ( alert )
  {
    alert.error( message );
    return;
  }

  window.alert( message );
}

/**
 * Returns the record ID of a row, or an empty string.
 *
 * @param {HTMLTableRowElement} row The row.
 * @returns {string}
 */
function getEntryId( row )
{
  return ENTRY_ID_PATTERN.exec( row.dataset.href ?? '' )?.[ 1 ] ?? '';
}

/**
 * Returns the field a cell edits, or an empty string.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @returns {string}
 */
function getCellField( cell )
{
  const column = [ ...cell.classList ].find( ( name ) => Object.hasOwn( COLUMN_FIELDS, name ) );

  return column === undefined ? '' : COLUMN_FIELDS[ column ];
}

/**
 * Loads the editable records in the list and marks the cells that can be edited.
 *
 * @returns {Promise<void>}
 */
async function markRows()
{
  const rows = [ ...document.querySelectorAll( ROW_SELECTOR ) ].filter( ( row ) => getEntryId( row ) !== '' );
  if ( rows.length === 0 )
  {
    return;
  }

  const ids = rows.map( getEntryId );
  try
  {
    entries = ( await fetchJson( CONFIG.entriesUrl + '?ids=' + ids.join( ',' ) ) ).entries ?? {};
  }
  catch
  {
    return;
  }

  rows.forEach( ( row ) =>
  {
    const id = getEntryId( row );
    const entry = entries[ id ];
    if ( entry === undefined )
    {
      return;
    }

    row.dataset.inlineEntry = id;
    row.querySelectorAll( 'td' ).forEach( ( cell ) =>
    {
      const field = getCellField( cell );
      if ( field === '' || !entry.fields.includes( field ) )
      {
        return;
      }

      cell.dataset.inlineField = field;
      cell.classList.add( EDITABLE_CLASS );
      cell.title = CONFIG.messages.hint;
      // Kimai opens the edit dialog on clicks that reach the page; editing keeps them here.
      cell.addEventListener( 'click', ( event ) => event.stopPropagation() );
    } );
  } );
}

/**
 * Saves one change and lets Kimai reload the list.
 *
 * @param {HTMLTableCellElement} cell The edited cell.
 * @param {Object<string, string>} change The field, value and optional activity.
 * @returns {Promise<boolean>} Whether the change was saved.
 */
async function save( cell, change )
{
  const body = new FormData();
  body.append( '_token', CONFIG.token );
  body.append( 'timesheet', cell.closest( 'tr' ).dataset.inlineEntry );
  Object.entries( change ).forEach( ( [ name, value ] ) => body.append( name, value ) );

  cell.classList.add( SAVING_CLASS );
  try
  {
    await fetchJson( CONFIG.saveUrl, { method: 'POST', body } );
  }
  catch ( error )
  {
    cell.classList.remove( SAVING_CLASS );
    showError( error.message );

    return false;
  }

  document.dispatchEvent( new Event( KIMAI_UPDATE_EVENT ) );

  return true;
}

/**
 * Creates a text field.
 *
 * @param {string} value The current value.
 * @param {string} placeholder The placeholder.
 * @returns {HTMLInputElement}
 */
function createInput( value, placeholder = '' )
{
  const input = document.createElement( 'input' );
  input.type = 'text';
  input.className = FIELD_CLASS;
  input.value = value;
  input.placeholder = placeholder;
  input.autocomplete = 'off';

  return input;
}

/**
 * Creates a picker with the given options, grouped when groups are given.
 *
 * @param {Array<{label: string, options: Array<{value: string, label: string}>}>} groups Option groups; an empty label means no group.
 * @param {string} selected The selected value.
 * @param {string} placeholder Text of an empty first option, or an empty string for none.
 * @returns {HTMLSelectElement}
 */
function createSelect( groups, selected, placeholder = '' )
{
  const select = document.createElement( 'select' );
  select.className = SELECT_CLASS;

  if ( placeholder !== '' )
  {
    select.append( new Option( placeholder, '' ) );
  }

  groups.forEach( ( group ) =>
  {
    const parent = group.label === '' ? select : document.createElement( 'optgroup' );
    parent.label = group.label;
    group.options.forEach( ( option ) => parent.append( new Option( option.label, option.value ) ) );

    if ( parent !== select )
    {
      select.append( parent );
    }
  } );

  select.value = selected;

  return select;
}

/**
 * Returns the activities that can be booked on a project.
 *
 * @param {Object} options The loaded options.
 * @param {number} projectId The project ID.
 * @returns {Array<Object>}
 */
function getProjectActivities( options, projectId )
{
  const project = options.customers.flatMap( ( customer ) => customer.projects ).find( ( item ) => item.id === projectId );

  return options.activities.filter( ( activity ) => activity.projectId === projectId || ( activity.projectId === GLOBAL_ACTIVITY && project?.globalActivities ) );
}

/**
 * Creates the activity picker for a project.
 *
 * @param {Object} options The loaded options.
 * @param {number} projectId The project ID.
 * @param {Object} entry The record.
 * @param {string} placeholder Text of an empty first option, or an empty string for none.
 * @returns {HTMLSelectElement}
 */
function createActivitySelect( options, projectId, entry, placeholder )
{
  const activities = getProjectActivities( options, projectId ).map( ( activity ) => ( { value: String( activity.id ), label: activity.name } ) );
  if ( placeholder === '' && !activities.some( ( activity ) => activity.value === String( entry.activityId ) ) )
  {
    activities.unshift( { value: String( entry.activityId ), label: entry.activityName } );
  }

  return createSelect( [ { label: '', options: activities } ], placeholder === '' ? String( entry.activityId ) : '', placeholder );
}

/**
 * Creates the project picker, grouped by customer.
 *
 * @param {Object} options The loaded options.
 * @param {Object} entry The record.
 * @returns {HTMLSelectElement}
 */
function createProjectSelect( options, entry )
{
  const groups = options.customers.map( ( customer ) => ( {
    label: customer.name,
    options: customer.projects.map( ( project ) => ( { value: String( project.id ), label: project.name } ) ),
  } ) );

  if ( !groups.some( ( group ) => group.options.some( ( option ) => option.value === String( entry.projectId ) ) ) )
  {
    groups.unshift( { label: '', options: [ { value: String( entry.projectId ), label: entry.projectName } ] } );
  }

  return createSelect( groups, String( entry.projectId ) );
}

/**
 * Reads the value to save from a text editor, or returns null when it is not valid.
 *
 * @param {string} field The field.
 * @param {string} text The typed text.
 * @returns {?string}
 */
function readTypedValue( field, text )
{
  if ( field === 'begin' || field === 'end' )
  {
    const time = parseTime( text );

    return time === null ? null : formatTime( time, false );
  }

  if ( field === 'duration' || field === 'break' )
  {
    const minutes = text.trim() === '' && field === 'break' ? 0 : parseDuration( text );

    return minutes === null ? null : String( minutes );
  }

  return field === 'tags' ? text.split( ',' ).map( ( name ) => name.trim() ).filter( Boolean ).join( ',' ) : text.trim();
}

/**
 * Returns the current value of a field in the form the server expects.
 *
 * @param {string} field The field.
 * @param {Object} entry The record.
 * @returns {string}
 */
function getCurrentValue( field, entry )
{
  if ( field === 'duration' || field === 'break' )
  {
    return String( entry[ field ] );
  }

  return field === 'tags' ? entry.tags.join( ',' ) : String( entry[ field ] ?? '' );
}

/**
 * Creates the editor for a text-like field, filled with the current value.
 *
 * @param {string} field The field.
 * @param {Object} entry The record.
 * @param {HTMLTableCellElement} cell The cell, whose text tells the clock format.
 * @returns {HTMLInputElement|HTMLTextAreaElement}
 */
function createTextEditor( field, entry, cell )
{
  if ( field === 'date' )
  {
    const input = createInput( entry.date );
    input.type = 'date';

    return input;
  }

  if ( field === 'begin' || field === 'end' )
  {
    const time = parseTime( entry[ field ] );

    return createInput( time === null ? '' : formatTime( time, TWELVE_HOUR_PATTERN.test( cell.textContent ) ) );
  }

  if ( field === 'duration' || field === 'break' )
  {
    return createInput( formatDuration( entry[ field ] ) );
  }

  if ( field === 'tags' )
  {
    return createInput( entry.tags.join( TAG_SEPARATOR ), CONFIG.messages.tagsPlaceholder );
  }

  const textarea = document.createElement( 'textarea' );
  textarea.className = FIELD_CLASS;
  textarea.rows = 2;
  textarea.value = entry.description;

  return textarea;
}

/**
 * Puts an editor into a cell and returns a function that restores the cell.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @param {HTMLElement} editor The editor.
 * @returns {function(): void}
 */
function openEditor( cell, editor )
{
  const original = [ ...cell.childNodes ];
  cell.classList.add( EDITING_CLASS );
  cell.replaceChildren( editor );
  editor.focus();

  return () =>
  {
    cell.classList.remove( EDITING_CLASS );
    cell.replaceChildren( ...original );
  };
}

/**
 * Edits a text-like field: Enter or leaving the field saves, Escape cancels.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @param {string} field The field.
 * @param {Object} entry The record.
 * @returns {void}
 */
function editText( cell, field, entry )
{
  const editor = createTextEditor( field, entry, cell );
  const close = openEditor( cell, editor );
  let finished = false;

  const finish = async ( commit, leaving = false ) =>
  {
    if ( finished )
    {
      return;
    }

    const value = commit ? readTypedValue( field, editor.value ) : '';
    if ( commit && value === null && leaving )
    {
      finished = true;
      close();
      return;
    }

    if ( commit && value === null )
    {
      // A typo is marked on the field rather than in a dialog, so typing can go on.
      editor.classList.add( INVALID_CLASS );
      editor.title = field === 'begin' || field === 'end' ? CONFIG.messages.invalidTime : CONFIG.messages.invalidDuration;
      editor.focus();
      return;
    }

    finished = true;
    if ( !commit || value === getCurrentValue( field, entry ) || !( await save( cell, { field, value } ) ) )
    {
      close();
    }
  };

  editor.addEventListener( 'keydown', ( event ) =>
  {
    if ( event.key === 'Escape' )
    {
      event.preventDefault();
      finish( false );
    }
    else if ( event.key === 'Enter' && !event.shiftKey )
    {
      event.preventDefault();
      finish( true );
    }
  } );
  // Leaving the field saves, but drops a value that cannot be read.
  editor.addEventListener( 'blur', () => finish( true, true ) );
  editor.addEventListener( 'input', () => editor.classList.remove( INVALID_CLASS ) );

  if ( editor instanceof HTMLInputElement && editor.type === 'text' )
  {
    editor.select();
  }
}

/**
 * What a pick in a picker leads to: a change to save, a next picker to show, or null to cancel.
 *
 * @typedef {Object<string, string>|{select: HTMLSelectElement, onPick: function(string): PickResult}|null} PickResult
 */

/**
 * Edits a field with a picker: picking saves, Escape or leaving it unchanged cancels.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @param {HTMLSelectElement} select The picker.
 * @param {function(string): PickResult} onPick Tells what a pick leads to.
 * @returns {void}
 */
function editWithSelect( cell, select, onPick )
{
  const close = openEditor( cell, select );
  let current = select;
  let finished = false;

  const finish = async ( change ) =>
  {
    if ( finished )
    {
      return;
    }

    finished = true;
    if ( change === null || !( await save( cell, change ) ) )
    {
      close();
    }
  };

  const watch = ( picker, handlePick ) =>
  {
    picker.addEventListener( 'keydown', ( event ) =>
    {
      if ( event.key === 'Escape' )
      {
        event.preventDefault();
        finish( null );
      }
    } );
    picker.addEventListener( 'blur', () =>
    {
      // Replacing the picker also blurs it; only a blur of the shown picker cancels.
      if ( picker === current )
      {
        finish( null );
      }
    } );
    picker.addEventListener( 'change', () =>
    {
      const next = handlePick( picker.value );
      if ( next !== null && next.select instanceof HTMLSelectElement )
      {
        current = next.select;
        watch( next.select, next.onPick );
        cell.replaceChildren( next.select );
        next.select.focus();
        return;
      }

      finish( next );
    } );
  };

  watch( select, onPick );
}

/**
 * Edits the project. When the record's activity does not fit the new project, an activity
 * picker follows, and both are saved together.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @param {Object} entry The record.
 * @param {Object} options The loaded options.
 * @returns {void}
 */
function editProject( cell, entry, options )
{
  editWithSelect( cell, createProjectSelect( options, entry ), ( value ) =>
  {
    const projectId = Number( value );
    if ( projectId === entry.projectId )
    {
      return null;
    }

    const activities = getProjectActivities( options, projectId );
    if ( activities.some( ( activity ) => activity.id === entry.activityId ) )
    {
      return { field: 'project', value };
    }

    if ( activities.length === 0 )
    {
      showError( CONFIG.messages.chooseActivity );
      return null;
    }

    return {
      select: createActivitySelect( options, projectId, entry, CONFIG.messages.chooseActivity ),
      onPick: ( activity ) => activity === '' ? null : { field: 'project', value, activity },
    };
  } );
}

/**
 * Edits the activity, offering the activities of the record's project.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @param {Object} entry The record.
 * @param {Object} options The loaded options.
 * @returns {void}
 */
function editActivity( cell, entry, options )
{
  editWithSelect( cell, createActivitySelect( options, entry.projectId, entry, '' ), ( value ) =>
  {
    return value === '' || Number( value ) === entry.activityId ? null : { field: 'activity', value };
  } );
}

/**
 * Starts editing a cell.
 *
 * @param {HTMLTableCellElement} cell The cell.
 * @returns {Promise<void>}
 */
async function startEditing( cell )
{
  const entry = entries[ cell.closest( 'tr' ).dataset.inlineEntry ];
  const field = cell.dataset.inlineField;

  if ( field === 'billable' )
  {
    await save( cell, { field, value: entry.billable ? '0' : '1' } );
    return;
  }

  if ( field !== 'project' && field !== 'activity' )
  {
    editText( cell, field, entry );
    return;
  }

  cell.classList.add( SAVING_CLASS );
  let options;
  try
  {
    options = await loadOptions();
  }
  catch ( error )
  {
    showError( error.message );
    return;
  }
  finally
  {
    cell.classList.remove( SAVING_CLASS );
  }

  if ( field === 'project' )
  {
    editProject( cell, entry, options );
  }
  else
  {
    editActivity( cell, entry, options );
  }
}

// Capture phase: runs before Kimai's own click handling, so links in a cell do not navigate.
document.addEventListener( 'click', ( event ) =>
{
  const cell = event.target instanceof Element ? event.target.closest( 'td.' + EDITABLE_CLASS ) : null;
  if ( cell === null || cell.classList.contains( EDITING_CLASS ) || cell.classList.contains( SAVING_CLASS ) )
  {
    return;
  }

  if ( event.ctrlKey || event.metaKey || event.shiftKey || window.getSelection()?.toString() !== '' )
  {
    return;
  }

  event.preventDefault();
  startEditing( cell );
}, true );

document.addEventListener( KIMAI_RELOADED_EVENT, markRows );

if ( document.readyState === 'loading' )
{
  document.addEventListener( 'DOMContentLoaded', markRows );
}
else
{
  markRows();
}
