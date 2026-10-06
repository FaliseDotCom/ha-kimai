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
- Four extra [plugins](#plugins): a **quick start bar** for starting and stopping time
  recording, **reporting extras** with charts on Kimai's reports, **short time entries** such
  as typing `945` for 9:45, and **inline timesheet editing** right in the record list.
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

The app ships with four Kimai plugins, written for this app. All are optional: each can be
turned off without affecting the rest of Kimai.

### Quick start bar

Start and stop time recording from one bar instead of a form. Type what you are working on,
pick a project and activity, optionally add tags and mark the record billable, and press
start. Fill in a date, start and end time instead to add something you already did. While a record
runs, the bar shows it with a live clock and a stop button, and everything about it,
including its start time, can be changed right there. Recent
descriptions are suggested as you type, and every record on **My times** gets a play button
that starts the same work again.

It replaces Kimai's own start button and sits on its own row directly below the top bar of
every page.

[How it works](kimai/bundles/TimerBarBundle/README.md)

### Reporting extras

Additions to Kimai's **Reporting** section:

- The **weekly, monthly and yearly report for one user** get a bar chart of the hours per day
  (or month), stacked by project, and a doughnut chart with a legend, above Kimai's table. In
  the table, projects are indented under their customer, and customer and project names link
  to their detail pages. Buttons next to the period picker switch between the three views.
- A **Summary** report shows any period on one page: totals, a bar chart over time, a
  doughnut chart, and a breakdown per project, customer, activity or user down to each
  description.

[How it works](kimai/bundles/ReportingBundle/README.md)

### Short time entries

Times and durations can be typed in short form and are completed as soon as you leave the
field: `945` becomes 9:45 as a start or end time, and `10` becomes ten minutes as a
duration. This works in the record dialog and on **Weekly hours**.

[How it works](kimai/bundles/ShortTimeEntriesBundle/README.md)

### Inline timesheet editing

Your own records on **My times** and **All times** can be edited right in the list: click a
record, Tab between its fields, and press Enter to save. Each user can turn this off in their
preferences.

[How it works](kimai/bundles/InlineTimesheetEditBundle/README.md)

The [app documentation](kimai/DOCS.md#extras-in-this-app) explains how to use the plugins,
and how to turn them off.

## Contributing

Bug reports and pull requests are welcome. [Development](.docs/development.md) explains how
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
