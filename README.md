# Kimai for Home Assistant

[![Add repository to Home Assistant][repository-badge]][repository-url]
![Kimai 2.67.0][kimai-shield]
![Supports aarch64 Architecture][aarch64-shield]
![Supports amd64 Architecture][amd64-shield]
[![License: MIT][license-shield]](LICENSE)

Run [Kimai](https://www.kimai.org/), the free and open-source time tracker, as a Home
Assistant app. Kimai and its MariaDB database run together in one app, so there is nothing
else to set up.

## Features

- Kimai with a bundled database; no separate database app needed.
- The first administrator is created from the app options.
- All data is included in Home Assistant backups.
- Seven extra [plugins](#plugins): a **quick start bar** for starting and stopping time
  recording, **reporting extras** with charts on Kimai's reports, **short time entries** such
  as typing `945` for 9:45, **inline timesheet editing** right in the record list,
  **timesheet extras** such as collapsible days and closable filters, **ODT templates**
  for exports and invoices laid out in LibreOffice Writer, and **export improvements** such
  as export templates that keep their column order.
- Kimai plugins and `local.yaml` customisation through the app configuration folder.
- Uses the Home Assistant time zone.
- Opens from the app page, the Home Assistant Companion app, or a sidebar dashboard.
- Runs on `amd64` and `aarch64` (Raspberry Pi 4 and 5).

## Installation

Select the button above, or add the repository by hand:

1. In Home Assistant, go to **Settings** > **Apps** > **Install app**.
2. Open the three-dots menu in the top-right corner and select **Repositories**.
3. Add `https://github.com/FaliseDotCom/ha-kimai` and select **Add**.
4. Find **Kimai** in the app store and select **Install**.

Then follow the [app documentation](kimai/DOCS.md) to create your administrator account and
open Kimai. The same documentation is shown on the app's **Documentation** tab in Home
Assistant.

## Apps in this repository

| App                | Description                                                     |
| ------------------ | --------------------------------------------------------------- |
| [Kimai](kimai/)    | Self-hosted time tracking for freelancers and teams.            |

## Plugins

The app ships with seven Kimai plugins, written for this app. All are optional: each can be
turned off without affecting the rest of Kimai.

### Quick start bar

Start and stop time recording from one bar instead of a form. Type what you are working on,
pick a project and activity, optionally add tags and mark the record billable, and press
start. Switch to manual mode, as in Toggl Track, to fill in a date, start and end time for
something you already did. While a record runs, the bar shows it with a live clock and a stop button, and everything about it,
including its start time, can be changed right there. Recent
descriptions are suggested as you type, and every record on **My times** gets a play button
that starts the same work again. The running record stands out in the record lists, and its
time ticks along wherever it is shown. Records started or stopped in another tab show up in
the bar too.

It replaces Kimai's own start button and sits on its own row directly below the top bar of
every page.

[How it works](kimai/bundles/QuickTimerBarBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle)

### Reporting extras

Additions to Kimai's **Reporting** section:

- **Quarterly views** for one user and for all users, in the style of Kimai's yearly views,
  with a quarter picker that steps a quarter back or forward. On the reporting overview they
  sit between the monthly and yearly views, and every period view's icon carries its period
  letter.
- The **weekly, monthly, quarterly and yearly report for one user** get a bar chart of the
  hours per day (or month), stacked by project, and a doughnut chart with a legend and the
  total time in its centre, above Kimai's table. In the table, projects are indented under
  their customer, customer and project names link to their detail pages, and the table sorts
  by customer or total. The hours in the legend open the records, like the table's totals.
  Buttons next to the period picker switch between the four views.
- A **Summary** report shows any period on one page: totals, a bar chart over time, a
  doughnut chart, and a breakdown per project, customer, activity or user down to each
  description.
- Two **dashboard widgets**: your working hours by project per week, in the same chart style,
  and an hours overview that separates your own hours from everyone's.

[How it works](kimai/bundles/ReportingGraphsBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle)

### Short time entries

Times and durations can be typed in short form and are completed as soon as you leave the
field: `945` becomes 9:45 as a start or end time, and `10` becomes ten minutes as a
duration. This works in the record dialog and on **Weekly hours**.

[How it works](kimai/bundles/ShortTimeEntriesBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle)

### Inline timesheet editing

Your own records on **My times** and **All times** can be edited right in the list: click a
record, Tab between its fields, and press Enter to save. Each user can turn this off in their
preferences.

[How it works](kimai/bundles/InlineTimesheetEditBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle)

### Timesheet extras

Improvements to **My times** and **All times**. With Kimai's daily stats turned on, each day's
total row moves to the top of the day and can be clicked to collapse the day to just that
row. One button collapses or expands all days, and the browser remembers which days are
collapsed. On all of Kimai's lists, the filter menu closes on a click outside it and gets a
close button.

[How it works](kimai/bundles/TimesheetExtrasBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle)

### ODT templates

Exports and invoices from your own LibreOffice Writer documents (`.odt`). Put placeholders
such as `${entry.date}` in a table row, and the row repeats for every record. Each template
in the app's `export` folder shows up in the **Export** menu of **My times** and **All
times**, so a filtered list exports straight into your own layout. Templates in the
`invoices` folder can be chosen for invoices. Templates are uploaded on their own page next
to Kimai's invoice templates, as Kimai's upload does not take ODT files. Examples for both
are included.

[How it works](kimai/bundles/OdtTemplatesBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-odt-templates-bundle)

### Export improvements

Improvements to Kimai's exports. Kimai exports the columns of an export template in the order
they were picked, but its form lists them in its own order, and saving the form again stores
that order. With this plugin the form shows the saved order, and columns can be dragged into
a new one.

[How it works](kimai/bundles/ExportImprovementsBundle/README.md) ·
[Repository and issues](https://github.com/FaliseDotCom/kimai-export-improvements-bundle)

The [app documentation](kimai/DOCS.md#extras-in-this-app) explains how to use the plugins,
and how to turn them off.

## Contributing

Bug reports and pull requests are welcome. Problems with one of the plugins can also be
reported in that plugin's own repository, linked from its section under
[Plugins](#plugins). [Development](.docs/development.md) explains how
the app and the plugins work and how to test changes, and [Releasing](.docs/releasing.md)
covers updating to a new Kimai version.

The app itself is shell scripts, YAML and a Dockerfile. The plugins in `kimai/bundles/` are
PHP, Twig, JavaScript and CSS. Pull requests should follow these conventions:

- **Formatting:** two spaces per indentation level, opening braces on their own line, and
  spaces inside parentheses and brackets, as in the existing code. `.editorconfig` sets the
  basics for most editors.
- **Documentation:** every function and class property has a short comment describing what
  it does, in PHPDoc or JSDoc form.
- **Checks:** shell scripts pass [ShellCheck](https://www.shellcheck.net/), and the plugins'
  PHP passes [PHPStan](https://phpstan.org/) at level 6.
  [Development](.docs/development.md#1-lint) shows how to run both with Docker.
- **User-facing changes:** update [`kimai/DOCS.md`](kimai/DOCS.md) and add an entry to
  [`kimai/CHANGELOG.md`](kimai/CHANGELOG.md).

## AI coding guidelines

AI coding assistants working on this repository should apply the `php`, `phpstan`,
`javascript` and `css` skills: PHP for the plugins' bundles, PHPStan at level 6 after PHP
changes, JavaScript and CSS for the plugins' scripts and stylesheets. The plugins' Twig
templates follow the same formatting rules: two-space indentation and spaces inside braces.

Every change to a plugin in `kimai/bundles/` raises that plugin's `version` in its
`composer.json` in the same commit (the last number for fixes, the middle one for features),
next to the app version and changelog entry described in [Releasing](.docs/releasing.md).
The **Publish plugins** workflow then releases it to the plugin's own repository on push.

## License

The files in this repository are released under the [MIT License](LICENSE), except for
`kimai/icon.png` and `kimai/logo.png`. Kimai itself is licensed under the
[AGPL-3.0](https://github.com/kimai/kimai/blob/main/LICENSE) and is not affiliated with this
project.

The Kimai name and the icon images (`kimai/icon.png` and `kimai/logo.png`, resized from
Kimai's own icon) belong to the Kimai project. They are used only to identify the app, and
remain under the Kimai project's terms.

[repository-badge]: https://my.home-assistant.io/badges/supervisor_add_addon_repository.svg
[repository-url]: https://my.home-assistant.io/redirect/supervisor_add_addon_repository/?repository_url=https%3A%2F%2Fgithub.com%2FFaliseDotCom%2Fha-kimai
[kimai-shield]: https://img.shields.io/badge/Kimai-2.67.0-blue.svg
[aarch64-shield]: https://img.shields.io/badge/aarch64-yes-green.svg
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg
[license-shield]: https://img.shields.io/badge/license-MIT-green.svg
