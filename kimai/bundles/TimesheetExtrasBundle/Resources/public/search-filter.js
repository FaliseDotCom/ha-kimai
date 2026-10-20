/**
 * Closable search filters. Kimai's filter dropdown on its lists only closes with its own
 * filter button, because pickers in it open outside the dropdown. This closes it on a press
 * anywhere else on the page, and adds a close button next to its search button.
 */

/**
 * The script tag, which carries the close button's label.
 *
 * @type {?HTMLScriptElement}
 */
const SCRIPT = [ ...document.scripts ].find( ( script ) => script.src === import.meta.url ) ?? null;

/**
 * Label of the close button, translated by the server.
 *
 * @type {string}
 */
const CLOSE_LABEL = SCRIPT?.dataset.close ?? '';

/**
 * Selector of Kimai's filter dropdown.
 *
 * @type {string}
 */
const MENU_SELECTOR = '.search-dropdown';

/**
 * Class Bootstrap gives an open dropdown.
 *
 * @type {string}
 */
const SHOWN_CLASS = 'show';

/**
 * Selector of the pickers that belong to the filters but open at the end of the page: Kimai's
 * select boxes (Tom Select) and date pickers (Litepicker, with its backdrop on phones).
 *
 * @type {string}
 */
const POPUP_SELECTOR = '.ts-dropdown, .litepicker, .litepicker-backdrop';

/**
 * Class of the close button.
 *
 * @type {string}
 */
const CLOSE_CLASS = 'search-filter-close';

/**
 * Font Awesome icon of the close button, as Kimai uses it.
 *
 * @type {string}
 */
const CLOSE_ICON = 'fas fa-times';

/**
 * Returns the button that opens and closes a filter dropdown.
 *
 * @param {HTMLElement} menu The filter dropdown.
 * @returns {?HTMLElement}
 */
function getToggle( menu )
{
  const id = menu.getAttribute( 'aria-labelledby' );

  return id ? document.getElementById( id ) : null;
}

/**
 * Closes a filter dropdown through its own button, so Bootstrap keeps track of it.
 *
 * @param {HTMLElement} menu The filter dropdown.
 * @returns {?HTMLElement} The dropdown's button, if it has one.
 */
function closeMenu( menu )
{
  const toggle = getToggle( menu );
  if ( toggle && menu.classList.contains( SHOWN_CLASS ) )
  {
    toggle.click();
  }

  return toggle;
}

/**
 * Tells whether a press happened outside a filter dropdown, its button and its pickers. The
 * event's path is used because a press can remove its target, such as a selected item's
 * remove button.
 *
 * @param {Event} event The press.
 * @param {HTMLElement} menu The filter dropdown.
 * @returns {boolean}
 */
function isOutside( event, menu )
{
  const toggle = getToggle( menu );

  return !event.composedPath().some( ( element ) => element === menu
    || element === toggle
    || ( element instanceof Element && element.matches( POPUP_SELECTOR ) ) );
}

/**
 * Creates the close button.
 *
 * @param {HTMLElement} menu The filter dropdown it closes.
 * @returns {HTMLButtonElement}
 */
function createCloseButton( menu )
{
  const icon = document.createElement( 'i' );
  icon.className = CLOSE_ICON;
  icon.setAttribute( 'aria-hidden', 'true' );

  const button = document.createElement( 'button' );
  button.type = 'button';
  button.className = 'btn me-2 ' + CLOSE_CLASS;
  button.append( icon, ' ', CLOSE_LABEL );
  button.addEventListener( 'click', () => closeMenu( menu )?.focus() );

  return button;
}

/**
 * Adds a close button before the search button of each filter dropdown that has none yet.
 *
 * @returns {void}
 */
function init()
{
  for ( const menu of document.querySelectorAll( MENU_SELECTOR ) )
  {
    const submit = menu.querySelector( 'button[type="submit"]' );
    if ( submit && !menu.querySelector( '.' + CLOSE_CLASS ) )
    {
      submit.before( createCloseButton( menu ) );
    }
  }
}

// A press rather than a click, so selecting text in a field and letting go outside the
// dropdown keeps it open.
document.addEventListener( 'pointerdown', ( event ) =>
{
  for ( const menu of document.querySelectorAll( MENU_SELECTOR + '.' + SHOWN_CLASS ) )
  {
    if ( isOutside( event, menu ) )
    {
      closeMenu( menu );
    }
  }
} );

// Kimai reloads lists by swapping in a fresh copy of the page content.
document.addEventListener( 'kimai.reloadedContent', init );

init();
