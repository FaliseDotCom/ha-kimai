/**
 * Draws the charts of the Kimai summary report. The page provides the chart data as JSON in
 * a script element, and Kimai provides Chart.js as the global `Chart`.
 */
( function ()
{
  'use strict';

  /**
   * ID of the script element that holds the chart data.
   *
   * @type {string}
   */
  const DATA_ELEMENT_ID = 'summary-chart-data';

  /**
   * ID of the canvas for the time-per-period bar chart.
   *
   * @type {string}
   */
  const BAR_CHART_ID = 'summary-bar-chart';

  /**
   * ID of the canvas for the share-per-group doughnut chart.
   *
   * @type {string}
   */
  const DOUGHNUT_CHART_ID = 'summary-doughnut-chart';

  /**
   * Selector for the colour swatches in the breakdown table.
   *
   * @type {string}
   */
  const SWATCH_SELECTOR = '.summary-swatch[data-color]';

  /**
   * Event Kimai dispatches once its own scripts, including the chart defaults, are ready.
   *
   * @type {string}
   */
  const READY_EVENT = 'kimai.initialized';

  /**
   * Attribute naming the element that charts added to another report should move into.
   *
   * @type {string}
   */
  const MOVE_ATTRIBUTE = 'data-summary-move-to';

  /**
   * Number of seconds in an hour.
   *
   * @type {number}
   */
  const SECONDS_PER_HOUR = 3600;

  /**
   * Formats a number of seconds as hours and minutes, for example 1:05.
   *
   * @param {number} seconds The duration in seconds.
   * @returns {string}
   */
  function formatDuration( seconds )
  {
    const minutes = Math.round( seconds / 60 );
    const hours = Math.floor( minutes / 60 );
    const rest = minutes % 60;

    return hours + ':' + String( rest ).padStart( 2, '0' );
  }

  /**
   * Reads the chart data that the page embeds as JSON.
   *
   * @returns {?{buckets: string[], series: {label: string, color: string, data: number[]}[], gridColor: string, labels: {total: string}}}
   */
  function readChartData()
  {
    const element = document.getElementById( DATA_ELEMENT_ID );
    if ( element === null )
    {
      return null;
    }

    try
    {
      return JSON.parse( element.textContent );
    }
    catch ( error )
    {
      console.error( 'Summary report: invalid chart data', error );
      return null;
    }
  }

  /**
   * Returns the total number of seconds in one series.
   *
   * @param {number[]} values Seconds per bucket.
   * @returns {number}
   */
  function sum( values )
  {
    return values.reduce( ( total, value ) => total + value, 0 );
  }

  /**
   * Gives every colour swatch in the breakdown table its group colour.
   *
   * @returns {void}
   */
  function paintSwatches()
  {
    document.querySelectorAll( SWATCH_SELECTOR ).forEach( ( swatch ) =>
    {
      swatch.style.backgroundColor = swatch.dataset.color;
    } );
  }

  /**
   * Draws the stacked bar chart with the time per day or month.
   *
   * @param {{buckets: string[], series: {label: string, color: string, data: number[]}[], gridColor: string, labels: {total: string}}} data The chart data.
   * @returns {void}
   */
  function renderBarChart( data )
  {
    const canvas = document.getElementById( BAR_CHART_ID );
    if ( canvas === null )
    {
      return;
    }

    const totals = data.buckets.map( ( label, index ) => sum( data.series.map( ( series ) => series.data[ index ] ) ) );

    new Chart( canvas, {
      type: 'bar',
      data: {
        labels: data.buckets,
        datasets: data.series.map( ( series ) => ( {
          label: series.label,
          backgroundColor: series.color,
          data: series.data.map( ( seconds ) => seconds / SECONDS_PER_HOUR ),
          seconds: series.data,
        } ) ),
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
          x: {
            stacked: true,
            grid: { display: false },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            grid: { color: data.gridColor },
            ticks: { callback: ( value ) => value + 'h' },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            filter: ( item ) => item.dataset.seconds[ item.dataIndex ] > 0,
            callbacks: {
              label: ( item ) => ' ' + item.dataset.label + ': ' + formatDuration( item.dataset.seconds[ item.dataIndex ] ),
              footer: ( items ) => data.labels.total + ': ' + formatDuration( totals[ items[ 0 ].dataIndex ] ),
            },
          },
        },
      },
    } );
  }

  /**
   * Draws the doughnut chart with each group's share of the total time.
   *
   * @param {{series: {label: string, color: string, data: number[]}[]}} data The chart data.
   * @returns {void}
   */
  function renderDoughnutChart( data )
  {
    const canvas = document.getElementById( DOUGHNUT_CHART_ID );
    if ( canvas === null )
    {
      return;
    }

    const durations = data.series.map( ( series ) => sum( series.data ) );
    const total = sum( durations );

    new Chart( canvas, {
      type: 'doughnut',
      data: {
        labels: data.series.map( ( series ) => series.label ),
        datasets: [ {
          backgroundColor: data.series.map( ( series ) => series.color ),
          data: durations,
        } ],
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        cutout: '60%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ( item ) =>
              {
                const percent = total > 0 ? ( item.raw / total * 100 ).toFixed( 1 ) : '0.0';

                return ' ' + formatDuration( item.raw ) + ' (' + percent + '%)';
              },
            },
          },
        },
      },
    } );
  }

  /**
   * Moves charts that were added to another report to the top of that report's content,
   * below its filters.
   *
   * @returns {void}
   */
  function placeCharts()
  {
    document.querySelectorAll( '[' + MOVE_ATTRIBUTE + ']' ).forEach( ( charts ) =>
    {
      const target = document.getElementById( charts.getAttribute( MOVE_ATTRIBUTE ) );
      if ( target !== null )
      {
        target.prepend( charts );
      }
    } );
  }

  /**
   * Draws everything once Kimai is ready.
   *
   * @returns {void}
   */
  function init()
  {
    placeCharts();
    paintSwatches();

    const data = readChartData();
    if ( data === null || typeof Chart === 'undefined' )
    {
      return;
    }

    renderBarChart( data );
    renderDoughnutChart( data );
  }

  document.addEventListener( READY_EVENT, init );
} )();
