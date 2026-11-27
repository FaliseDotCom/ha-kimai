/**
 * Quick filters on the record lists. Kimai's time range and project fields sit in its filter
 * menu; this moves those same fields out of the menu into the toolbar, next to the search
 * field, and applies a change right away. They stay part of Kimai's search form, so the menu,
 * the search button and the saved default search all keep working with one set of values.
 *
 * It runs before Kimai sets up its pickers (on the page's load event), so Kimai turns the moved
 * fields into its date range picker and select box in their new place.
 */

/**
 * Selector of Kimai's search form in the toolbar.
 *
 * @type {string}
 */
const FORM_SELECTOR = 'form.searchform';

/**
 * Selector of the search field group, which the quick filters follow.
 *
 * @type {string}
 */
const SEARCH_SELECTOR = '.inline-search';

/**
 * IDs of the fields that move to the toolbar, in the order they are shown.
 *
 * @type {Array<string>}
 */
const FIELD_IDS = [ 'daterange', 'projects' ];

/**
 * Selector of the form row that holds a field in the filter menu.
 *
 * @type {string}
 */
const ROW_SELECTOR = '.row';

/**
 * ID of Kimai's hidden page number, which goes back to the first page on a new filter.
 *
 * @type {string}
 */
const PAGE_ID = 'page';

/**
 * Class of the bar that holds the quick filters.
 *
 * @type {string}
 */
const BAR_CLASS = 'timesheet-quick-filter';

/**
 * Class of the search form once it shows quick filters.
 *
 * @type {string}
 */
const FORM_CLASS = 'timesheet-quick-filter-form';

/**
 * Class of each quick filter in the bar.
 *
 * @type {string}
 */
const ITEM_CLASS = 'timesheet-quick-filter-item';

/**
 * Bootstrap's class that hides a label visually but keeps it for screen readers.
 *
 * @type {string}
 */
const HIDDEN_LABEL_CLASS = 'visually-hidden';

/**
 * Returns the part of a field that moves to the toolbar: the date range's input group, with
 * its button of preset periods, or the field itself.
 *
 * @param {HTMLElement} field The field.
 * @returns {HTMLElement}
 */
function getWidget( field )
{
  const group = field.closest( '.input-group' );

  return group && !group.matches( SEARCH_SELECTOR ) ? group : field;
}

/**
 * Moves one field with its label from the filter menu into the bar, and removes its now empty
 * row from the menu.
 *
 * @param {HTMLFormElement} form The search form.
 * @param {HTMLElement} field The field.
 * @param {HTMLElement} bar The bar of quick filters.
 * @returns {void}
 */
function moveField( form, field, bar )
{
  const row = field.closest( ROW_SELECTOR );
  const label = form.querySelector( 'label[for="' + field.id + '"]' );
  const item = document.createElement( 'div' );
  item.className = ITEM_CLASS + ' ' + BAR_CLASS + '-' + field.id;

  if ( label )
  {
    label.className = HIDDEN_LABEL_CLASS;
    item.append( label );

    // An empty select box shows its label as placeholder, so it is clear what it filters.
    if ( field instanceof HTMLSelectElement && !field.hasAttribute( 'placeholder' ) )
    {
      field.setAttribute( 'placeholder', label.textContent.trim() );
    }
  }

  item.append( getWidget( field ) );
  bar.append( item );
  row?.remove();
}

/**
 * Applies the filters by submitting the search form, as Kimai's search button does, starting
 * again on the first page. Several change events of one choice are submitted once.
 *
 * @param {HTMLFormElement} form The search form.
 * @returns {void}
 */
function submit( form )
{
  if ( form.dataset.quickFilterSubmitting )
  {
    return;
  }

  form.dataset.quickFilterSubmitting = '1';
  const page = form.querySelector( 'input#' + PAGE_ID );
  if ( page )
  {
    page.value = '1';
  }

  window.setTimeout( () => form.requestSubmit(), 0 );
}

/**
 * Moves the time range and project fields of the record list's search form into a bar next to
 * the search field.
 *
 * @returns {void}
 */
function init()
{
  const form = document.querySelector( FORM_SELECTOR );
  const search = form?.querySelector( SEARCH_SELECTOR );
  if ( !form || !search || form.querySelector( '.' + BAR_CLASS ) )
  {
    return;
  }

  const fields = FIELD_IDS
    .map( ( id ) => form.querySelector( '#' + id ) )
    .filter( ( field ) => field instanceof HTMLElement );
  if ( fields.length === 0 )
  {
    return;
  }

  const bar = document.createElement( 'div' );
  bar.className = BAR_CLASS;
  for ( const field of fields )
  {
    moveField( form, field, bar );
    field.addEventListener( 'change', () => submit( form ) );
  }

  search.after( bar );
  form.classList.add( FORM_CLASS );

  // A page restored with the browser's back button can be filtered again.
  window.addEventListener( 'pageshow', () => delete form.dataset.quickFilterSubmitting );
}

init();
