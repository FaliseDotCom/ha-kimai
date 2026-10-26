# Short time entries for Kimai

Quicker time and duration entry in [Kimai](https://www.kimai.org/)'s forms. It ships with the
[Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md),
but works in any Kimai installation.

## Typing times and durations

Times and durations can be typed in short form. They are completed as soon as the field is
left, before Kimai reads the value, so Kimai's own calculation of the end time from the start
time and duration keeps working.

**Time fields** (start and end time):

| You type | Becomes                         |
| -------- | ------------------------------- |
| `9`      | 9:00                            |
| `945`    | 9:45                            |
| `1330`   | 13:30 (1:30 PM on a 12-hour clock) |
| `9.45`, `9,45`, `9h45` | 9:45              |
| `945p`, `9:45 pm` | 9:45 PM                |

The result follows the field's own format: 24-hour (`09:45`) or 12-hour (`9:45 AM`),
depending on the user's language. Values that are not a valid time, such as `2500`, are left
for Kimai to handle.

**Duration fields** (the record dialog and **Weekly hours**): one or two digits are minutes,
three or four digits are hours and minutes. When the last two digits are 60 or more, the
whole number is minutes (`175` becomes 2:55).

| You type | Becomes          |
| -------- | ---------------- |
| `10`     | 0:10 (minutes)   |
| `90`     | 1:30 (minutes)   |
| `130`    | 1:30             |
| `1045`   | 10:45            |

Every other duration format Kimai supports, such as `1:30`, `1.5` or `1h30m`, is passed on
unchanged. A plain number used to mean hours in Kimai; with this plugin it means minutes.

## How it works

One module script, added to every page for fully logged-in users through Kimai's
`ThemeEvent::JAVASCRIPT`; it is left out halfway through a two-factor login, where its own
route would refuse it. The plugin serves the script itself, and browsers keep it for a day.
It listens for `change` events in the capture phase, so it also works in forms that Kimai loads into dialogs later, and runs before Kimai's own handlers.
Fields are recognised by Kimai's own markup: `input[data-timepicker="on"]` for times and
`input.duration-input` for durations. Before a form is submitted, all its time and duration
fields are completed too.

The parsing lives in `input-parsing.js`, which the script imports with its own version query,
so a release never mixes old and new files from the browser cache. The inline timesheet
editing plugin carries an identical copy; change both together.

## Requirements and installation

Kimai 2.67.0 or later.

1. Download the zip of the latest
   [release](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle/releases) and
   unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/ShortTimeEntriesBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## Other plugins

This plugin is one of seven Kimai plugins made to work together: the quick start bar starts
and stops records, short time entries and inline timesheet editing make correcting them
quick, timesheet extras keep the record list tidy, reporting extras show where the time
went, and ODT templates and export extras turn the records into your own documents.
Each one also works on its own. The other six:

- [Quick start bar](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle): start and stop time recording from one bar below the top bar, and start a record again with its play button.
- [Reporting extras](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle): quarterly reports, charts on the user reports, a summary report and dashboard widgets.
- [Inline timesheet editing](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle): edit your own records right in the record list.
- [Timesheet extras](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle): collapsible days in the record list and closable search filters.
- [ODT templates](https://github.com/FaliseDotCom/kimai-odt-templates-bundle): exports and invoices from your own LibreOffice Writer templates.
- [Export extras](https://github.com/FaliseDotCom/kimai-export-extras-bundle): export templates that keep their column order, with columns you drag into place.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/ShortTimeEntriesBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
