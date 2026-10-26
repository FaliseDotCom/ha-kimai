/**
 * Column order of export templates. Kimai's select box (Tom Select) shows the picked columns
 * in the order of its own list, not in the order they were saved, and saving the form again
 * stores what it shows. The server puts the saved order on the select box; this puts the
 * picked columns back in that order once Kimai has set the box up. Dragging a column, which
 * the plugin turns on, then changes the order.
 */

/**
 * Selector of the select boxes that carry a saved column order.
 *
 * @type {string}
 */
const SELECT_SELECTOR = 'select[data-column-order]';

/**
 * The Tom Select instances whose order was restored, so later changes in the page leave the
 * user's own dragging alone.
 *
 * @type {WeakSet<object>}
 */
const restored = new WeakSet();

/**
 * Reads the saved column order of a select box.
 *
 * @param {HTMLSelectElement} select The select box.
 * @returns {string[]}
 */
function getOrder( select )
{
  try
  {
    const order = JSON.parse( select.dataset.columnOrder ?? '[]' );

    return Array.isArray( order ) ? order.filter( ( value ) => typeof value === 'string' ) : [];
  }
  catch
  {
    return [];
  }
}

/**
 * Puts the picked columns of a select box in their saved order, once its Tom Select exists.
 * Tom Select also moves the options of the original select box into that order, so the form
 * sends the columns in it.
 *
 * @param {HTMLSelectElement} select The select box.
 * @returns {void}
 */
function restoreOrder( select )
{
  const tomSelect = select.tomselect;
  if ( !tomSelect || restored.has( tomSelect ) )
  {
    return;
  }

  restored.add( tomSelect );

  const order = getOrder( select ).filter( ( value ) => tomSelect.options[ value ] !== undefined );
  if ( order.length === 0 )
  {
    return;
  }

  // Silent, so the dialog does not count it as a change by the user.
  tomSelect.setValue( order, true );
}

/**
 * Restores the order of every column select box on the page.
 *
 * @returns {void}
 */
function init()
{
  document.querySelectorAll( SELECT_SELECTOR ).forEach( restoreOrder );
}

// Kimai opens the form in a dialog by inserting its markup and then setting up the select
// boxes, and sets them up on its own pages after loading. Setting up a box adds Tom Select's
// markup next to it, so every change in the page is a moment to look again.
new MutationObserver( init ).observe( document.body, { childList: true, subtree: true } );

init();
