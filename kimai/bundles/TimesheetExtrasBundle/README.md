# Timesheet extras for Kimai

Improvements to [Kimai](https://www.kimai.org/)'s record lists: collapsible days and
closable search filters. It ships with the
[Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md),
but works in any Kimai installation.

## Collapsible days

With Kimai's **Show daily stats in timesheet** preference on, **My times** and **All times**
show a row per day with the day's total duration (and amounts). Kimai puts that row below
the day's records; this plugin moves it to the top, so it reads as the day's heading.

- Click a day row, or the arrow before its date, to collapse the day to just that row; click
  again to expand it.
- A button next to the list's own buttons (before **Customize display**) collapses all days,
  or expands them all when none is expanded.
- The browser remembers which days are collapsed, separately for **My times** and **All
  times** and for each user. **Collapse all** and **Expand all** also apply to days on other pages and days
  still to come: after **Collapse all**, a new day starts collapsed.

Without the daily stats preference there are no day rows, and the plugin adds nothing.

Selecting all records with the checkbox in the table header also selects the records of
collapsed days, as Kimai's batch actions work on the whole page. Kimai's confirmation shows
how many records are selected.

## Closable filters

Kimai's filter menu next to the search field on its lists (records, customers, projects and
so on) only closes with its own filter button. This plugin closes it on a click or tap
anywhere outside it, and adds a **Close** button next to its **Search** button.

The select boxes and date pickers in the menu open at the end of the page, outside the menu;
pressing in them keeps the menu open. The menu closes on the press rather than the click, so
selecting text in a field and letting go outside the menu keeps it open too.

## How it works

On the `timesheet` and `admin_timesheet` routes (and their paginated variants), and only
when the user has `daily_stats` turned on, `timesheet-days.js` and `timesheet-days.css` are
added through Kimai's `ThemeEvent::JAVASCRIPT` and `ThemeEvent::STYLESHEET`. The script
finds Kimai's day rows (`tr.summary`), moves each above the first record of its day, and
hides the rows between one day row and the next when a day is collapsed. The day is
identified by the text of its `col_date` cell; when the date column is hidden, days can still
be collapsed but are not remembered. When the list is not sorted by date, the same day can
appear more than once; those parts collapse together.

The filters script, `search-filter.js`, is added to every page for logged-in users, because
most of Kimai's lists have a filter menu; it does nothing on pages without one. It finds
Kimai's menu (`.search-dropdown`) and closes it through its own Bootstrap toggle button, and
labels the close button with Kimai's own `action.close` translation. Presses in Tom Select's
dropdowns (`.ts-dropdown`) and Litepicker (`.litepicker` and its backdrop) count as inside.

The state lives in the browser's `localStorage`, under
`timesheet-extras.collapsed-days.own.<user ID>` and `timesheet-extras.collapsed-days.all.<user ID>`: whether days
are collapsed by default, and the days that differ from that default (at most 500, oldest
dropped first). Kimai reloads the list after changes by replacing the page content and
dispatching `kimai.reloadedContent`; the script then prepares the new rows. The collapse all
button sits in the page header, which a reload keeps.

The plugin serves its own script and stylesheet; browsers keep them for a day, and a release
changes the version in their address.

## Requirements and installation

Kimai 2.67.0 or later.

1. Download the zip of the latest
   [release](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle/releases) and
   unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/TimesheetExtrasBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## Translations

English and Dutch, in `Resources/translations/timesheet_extras.*.xlf`.

## Other plugins

This plugin is one of five Kimai plugins made to work together: the quick start bar starts
and stops records, short time entries and inline timesheet editing make correcting them
quick, timesheet extras keep the record list tidy, and reporting extras show where the time
went. Each one also works on its own. The other four:

- [Quick start bar](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle): start and stop time recording from one bar below the top bar, and start a record again with its play button.
- [Reporting extras](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle): quarterly reports, charts on the user reports, a summary report and dashboard widgets.
- [Short time entries](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle): type `945` for 9:45 or `10` for ten minutes in Kimai's forms.
- [Inline timesheet editing](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle): edit your own records right in the record list.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/TimesheetExtrasBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
