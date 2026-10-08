/**
 * Collapsible days in Kimai's record lists. Kimai's day rows (the daily stats, shown below
 * each day's records) move to the top of their day and fold the day's records away when
 * clicked. A button next to the list's own buttons collapses or expands all days. The browser
 * remembers which days are collapsed, per list.
 */

/**
 * The script tag, which carries the list name and the messages.
 *
 * @type {?HTMLScriptElement}
 */
const SCRIPT = [ ...document.scripts ].find( ( script ) => script.src === import.meta.url ) ?? null;

/**
 * Messages, translated by the server.
 *
 * @type {Object<string, string>}
 */
const MESSAGES = JSON.parse( SCRIPT?.dataset.messages ?? '{}' );

/**
 * Key under which the browser remembers the collapsed days of this list.
 *
 * @type {string}
 */
const STORAGE_KEY = 'timesheet-extras.collapsed-days.' + ( SCRIPT?.dataset.list ?? 'own' );

/**
 * At most this many days are remembered as differing from the default; the oldest go first.
 *
 * @type {number}
 */
const MAX_REMEMBERED_DAYS = 500;

/**
 * Selector of Kimai's record table.
 *
 * @type {string}
 */
const TABLE_SELECTOR = 'table.dataTable';

/**
 * Selector of Kimai's day rows, which follow the records of their day.
 *
 * @type {string}
 */
const SUMMARY_SELECTOR = 'tr.summary';

/**
 * Selector of the cell that holds the date in a day row.
 *
 * @type {string}
 */
const DATE_CELL_SELECTOR = 'td.col_date';

/**
 * Selector of the list's own buttons in the page header, which the toggle joins.
 *
 * @type {string}
 */
const TABLE_ACTIONS_SELECTOR = '.page-header .btn-list:has( > [data-bs-target^="#modal_"], > .searchform )';

/**
 * Class of day rows that have been moved to the top of their day.
 *
 * @type {string}
 */
const DAY_CLASS = 'timesheet-days-day';

/**
 * Class of collapsed day rows.
 *
 * @type {string}
 */
const COLLAPSED_CLASS = 'timesheet-days-collapsed';

/**
 * Class of record rows hidden in a collapsed day.
 *
 * @type {string}
 */
const HIDDEN_CLASS = 'timesheet-days-hidden';

/**
 * Class of the button in each day row.
 *
 * @type {string}
 */
const DAY_TOGGLE_CLASS = 'timesheet-days-toggle';

/**
 * Class of the collapse all button.
 *
 * @type {string}
 */
const ALL_TOGGLE_CLASS = 'timesheet-days-toggle-all';

/**
 * Font Awesome icons, as Kimai uses them.
 *
 * @type {Object<string, string>}
 */
const ICONS = {
  day: 'fas fa-chevron-down',
  collapseAll: 'fas fa-angle-double-up',
  expandAll: 'fas fa-angle-double-down',
};

/**
 * The remembered collapsed days: whether days are collapsed by default, and the days that
 * differ from that default. "Collapse all" and "Expand all" set the default and forget the
 * rest, so they also apply to days on other pages and days still to come.
 *
 * @typedef {Object} DayState
 * @property {boolean} collapsed Whether days are collapsed by default.
 * @property {Array<string>} exceptions The days that differ from the default, oldest first.
 */

/**
 * Reads the remembered collapsed days.
 *
 * @returns {DayState}
 */
function readState()
{
  try
  {
    const state = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) ?? 'null' );
    if ( state && typeof state.collapsed === 'boolean' && Array.isArray( state.exceptions ) )
    {
      return state;
    }
  }
  catch ( error )
  {
    // Unreadable or blocked storage: start with all days expanded.
  }

  return { collapsed: false, exceptions: [] };
}

/**
 * Remembers the collapsed days, when the browser allows it.
 *
 * @param {DayState} state The state to remember.
 * @returns {void}
 */
function writeState( state )
{
  try
  {
    window.localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
  }
  catch ( error )
  {
    // Without storage the days simply start expanded on the next page.
  }
}

/**
 * Tells whether a day is collapsed according to the remembered state.
 *
 * @param {string} day The day, as Kimai shows it in the day row.
 * @returns {boolean}
 */
function isRememberedCollapsed( day )
{
  const state = readState();

  return state.collapsed !== state.exceptions.includes( day );
}

/**
 * Remembers whether one day is collapsed.
 *
 * @param {string} day The day, as Kimai shows it in the day row.
 * @param {boolean} collapsed Whether the day is collapsed.
 * @returns {void}
 */
function rememberDay( day, collapsed )
{
  const state = readState();
  const exceptions = state.exceptions.filter( ( exception ) => exception !== day );
  if ( collapsed !== state.collapsed )
  {
    exceptions.push( day );
  }

  writeState( { collapsed: state.collapsed, exceptions: exceptions.slice( -MAX_REMEMBERED_DAYS ) } );
}

/**
 * Returns the day a day row stands for: the date Kimai shows in it, or an empty string when
 * the date column is hidden. Such days can be collapsed, but are not remembered.
 *
 * @param {HTMLTableRowElement} row A day row.
 * @returns {string}
 */
function getDay( row )
{
  return row.querySelector( DATE_CELL_SELECTOR )?.textContent.trim() ?? '';
}

/**
 * Returns the record rows of a day: the rows below its day row, up to the next day row.
 *
 * @param {HTMLTableRowElement} row A day row.
 * @returns {Array<HTMLTableRowElement>}
 */
function getRecordRows( row )
{
  const rows = [];
  for ( let next = row.nextElementSibling; next && !next.classList.contains( DAY_CLASS ); next = next.nextElementSibling )
  {
    rows.push( next );
  }

  return rows;
}

/**
 * Returns the day rows of the record table on the page.
 *
 * @returns {Array<HTMLTableRowElement>}
 */
function getDayRows()
{
  return [ ...document.querySelectorAll( TABLE_SELECTOR + ' tr.' + DAY_CLASS ) ];
}

/**
 * Creates an icon element.
 *
 * @param {string} className The icon classes.
 * @returns {HTMLElement}
 */
function createIcon( className )
{
  const icon = document.createElement( 'i' );
  icon.className = className;
  icon.setAttribute( 'aria-hidden', 'true' );

  return icon;
}

/**
 * Moves each day row above the records of its day, adds its toggle button and applies the
 * remembered state. Kimai puts a day row after the records it totals, whichever way the list
 * is sorted. All rows move before any day collapses, because a day's records are found by
 * the position of the next day row.
 *
 * @param {HTMLTableElement} table The record table.
 * @returns {void}
 */
function prepareDays( table )
{
  const prepared = [];
  for ( const body of table.tBodies )
  {
    let firstRecord = null;
    for ( const row of [ ...body.rows ] )
    {
      if ( !row.matches( SUMMARY_SELECTOR ) )
      {
        firstRecord ??= row;
        continue;
      }

      if ( !row.classList.contains( DAY_CLASS ) )
      {
        prepareDay( row, firstRecord );
        prepared.push( row );
      }
      firstRecord = null;
    }
  }

  prepared.forEach( ( row ) => setCollapsed( row, isRememberedCollapsed( getDay( row ) ) ) );
}

/**
 * Moves one day row above its records and adds its toggle button.
 *
 * @param {HTMLTableRowElement} row The day row.
 * @param {?HTMLTableRowElement} firstRecord The day's first record row, if any.
 * @returns {void}
 */
function prepareDay( row, firstRecord )
{
  if ( firstRecord )
  {
    firstRecord.before( row );
  }
  row.classList.add( DAY_CLASS );

  const button = document.createElement( 'button' );
  button.type = 'button';
  button.className = 'btn btn-sm btn-icon btn-ghost-secondary ' + DAY_TOGGLE_CLASS;
  button.append( createIcon( ICONS.day ) );

  const cell = row.querySelector( DATE_CELL_SELECTOR ) ?? row.cells[ 0 ];
  cell?.prepend( button );
}

/**
 * Collapses or expands one day on the page.
 *
 * @param {HTMLTableRowElement} row The day row.
 * @param {boolean} collapsed Whether to collapse the day.
 * @returns {void}
 */
function setCollapsed( row, collapsed )
{
  row.classList.toggle( COLLAPSED_CLASS, collapsed );
  for ( const record of getRecordRows( row ) )
  {
    record.classList.toggle( HIDDEN_CLASS, collapsed );
  }

  const button = row.querySelector( '.' + DAY_TOGGLE_CLASS );
  if ( button )
  {
    const label = collapsed ? MESSAGES.expandDay : MESSAGES.collapseDay;
    button.setAttribute( 'aria-expanded', String( !collapsed ) );
    button.setAttribute( 'aria-label', label ?? '' );
    button.title = label ?? '';
  }
}

/**
 * Collapses or expands a day, and every other part of the list that shows the same day,
 * which happens when the list is not sorted by date.
 *
 * @param {HTMLTableRowElement} row The clicked day row.
 * @returns {void}
 */
function toggleDay( row )
{
  const collapsed = !row.classList.contains( COLLAPSED_CLASS );
  const day = getDay( row );
  if ( day === '' )
  {
    setCollapsed( row, collapsed );
  }
  else
  {
    getDayRows().filter( ( other ) => getDay( other ) === day ).forEach( ( other ) => setCollapsed( other, collapsed ) );
    rememberDay( day, collapsed );
  }

  updateAllToggle();
}

/**
 * Collapses or expands all days, also on other pages and days still to come.
 *
 * @param {boolean} collapsed Whether to collapse the days.
 * @returns {void}
 */
function setAllCollapsed( collapsed )
{
  writeState( { collapsed, exceptions: [] } );
  getDayRows().forEach( ( row ) => setCollapsed( row, collapsed ) );
  updateAllToggle();
}

/**
 * Tells whether any day on the page is expanded.
 *
 * @returns {boolean}
 */
function hasExpandedDay()
{
  return getDayRows().some( ( row ) => !row.classList.contains( COLLAPSED_CLASS ) );
}

/**
 * Returns the collapse all button, adding it next to the list's own buttons first. The page
 * header stays when Kimai reloads the list, so the button is added only once.
 *
 * @returns {?HTMLButtonElement}
 */
function getAllToggle()
{
  const existing = document.querySelector( '.' + ALL_TOGGLE_CLASS );
  if ( existing )
  {
    return existing;
  }

  const actions = document.querySelector( TABLE_ACTIONS_SELECTOR );
  if ( !actions )
  {
    return null;
  }

  const button = document.createElement( 'button' );
  button.type = 'button';
  button.className = 'btn btn-icon ' + ALL_TOGGLE_CLASS;
  button.addEventListener( 'click', () => setAllCollapsed( hasExpandedDay() ) );
  actions.prepend( button );

  return button;
}

/**
 * Shows the collapse all button as "Collapse all" while any day is expanded, and as "Expand
 * all" otherwise. It is hidden when the list has no day rows.
 *
 * @returns {void}
 */
function updateAllToggle()
{
  const button = getAllToggle();
  if ( !button )
  {
    return;
  }

  const collapse = hasExpandedDay();
  const label = ( collapse ? MESSAGES.collapseAll : MESSAGES.expandAll ) ?? '';
  button.hidden = getDayRows().length === 0;
  button.title = label;
  button.setAttribute( 'aria-label', label );
  button.replaceChildren( createIcon( collapse ? ICONS.collapseAll : ICONS.expandAll ) );
}

/**
 * Prepares the days of the record table on the page, if there is one.
 *
 * @returns {void}
 */
function init()
{
  const table = document.querySelector( TABLE_SELECTOR );
  if ( table )
  {
    prepareDays( table );
  }

  updateAllToggle();
}

// Day rows toggle on a click anywhere in them; the table is replaced on every reload, so
// the listener sits on the document.
document.addEventListener( 'click', ( event ) =>
{
  const row = event.target.closest( TABLE_SELECTOR + ' tr.' + DAY_CLASS );
  if ( row )
  {
    toggleDay( row );
  }
} );

// Kimai reloads the list after changes by swapping in a fresh copy of the page content.
document.addEventListener( 'kimai.reloadedContent', init );

init();
