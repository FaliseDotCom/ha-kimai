# Timesheet extras for Kimai

Improvements to [Kimai](https://www.kimai.org/)'s record lists. It ships with the
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
  times**. **Collapse all** and **Expand all** also apply to days on other pages and days
  still to come: after **Collapse all**, a new day starts collapsed.

Without the daily stats preference there are no day rows, and the plugin adds nothing.

Selecting all records with the checkbox in the table header also selects the records of
collapsed days, as Kimai's batch actions work on the whole page. Kimai's confirmation shows
how many records are selected.

## How it works

On the `timesheet` and `admin_timesheet` routes (and their paginated variants), and only
when the user has `daily_stats` turned on, `timesheet-days.js` and `timesheet-days.css` are
added through Kimai's `ThemeEvent::JAVASCRIPT` and `ThemeEvent::STYLESHEET`. The script
finds Kimai's day rows (`tr.summary`), moves each above the first record of its day, and
hides the rows between one day row and the next when a day is collapsed. The day is
identified by the text of its `col_date` cell; when the date column is hidden, days can still
be collapsed but are not remembered. When the list is not sorted by date, the same day can
appear more than once; those parts collapse together.

The state lives in the browser's `localStorage`, under
`timesheet-extras.collapsed-days.own` and `timesheet-extras.collapsed-days.all`: whether days
are collapsed by default, and the days that differ from that default (at most 500, oldest
dropped first). Kimai reloads the list after changes by replacing the page content and
dispatching `kimai.reloadedContent`; the script then prepares the new rows. The collapse all
button sits in the page header, which a reload keeps.

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

## Source

This plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai)
repository, in `kimai/bundles/TimesheetExtrasBundle/`. The
[kimai-timesheet-extras-bundle](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle)
repository is a read-only mirror of that folder for releases: report issues and send changes
to the app repository.

## License

MIT, see [LICENSE](LICENSE).
