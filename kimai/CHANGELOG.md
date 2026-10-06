# Changelog

All notable changes to this app are documented here. The version number is the Kimai
version the app contains; see the
[Kimai releases](https://github.com/kimai/kimai/releases) for changes in Kimai itself.

## 2.67.0.14

- Weekly, monthly and yearly user reports: buttons next to the period picker switch to the
  other two views for the same user, for example **Week** and **Month** in the yearly view.

## 2.67.0.13

- The Graphs plugin is now called **Reporting extras** (`ReportingBundle`), since it covers
  more of Kimai's Reporting section than graphs. Nothing changes in how it works.
- If you turned the plugin off with `plugins/SummaryBundle/.disabled`, rename that folder to
  `plugins/ReportingBundle`; the old folder no longer has any effect.

## 2.67.0.12

- Weekly, monthly and yearly user reports: customer and project names link to their detail
  pages, for users who may view those pages.

## 2.67.0.11

- Graphs: on the weekly, monthly and yearly user reports, the bar chart and the doughnut chart
  with its legend are now the same height.

## 2.67.0.10

- Quick start bar: on phones and narrow windows it now sits directly below the top bar, above
  the page's own buttons and filters, instead of between those and the list.

## 2.67.0.9

- Quick start bar: on phones and narrow windows it now shows on every page, on its own row
  above the content, instead of only on the dashboard and **My times**. It replaces Kimai's
  own start button there too.

## 2.67.0.8

- Weekly, monthly and yearly user reports: projects are indented under their customer, and
  activities under their project, so the levels of the table are easier to tell apart.

## 2.67.0.7

- Graphs: short records stay visible. Bars have a minimum height and doughnut slices a
  minimum size; tooltips still show the exact time.
- Graphs: projects with near-identical colours, such as two projects that inherit the same
  customer colour, now get clearly different colours, with a small gap between segments.
- Graphs: the time axis shows hours and minutes (1:30) instead of decimal hours.
- Graphs: the user reports show a legend with each project's total, and charts with more
  than eight groups combine the smallest into "Other".

## 2.67.0.6

- Quick start bar: in the top navigation, the description field now takes all the width
  between the page title and the other buttons.

## 2.67.0.5

- The quick start bar now sits in the top navigation of every page on wide screens, in place
  of Kimai's own start button. On narrow screens it stays above the dashboard and
  **My times**, and Kimai's own button remains in the navigation.
- New: the weekly, monthly and yearly report for one user show a bar chart of the hours per
  day (or month), stacked by project, and a doughnut chart per project.

## 2.67.0.4

- Quick start bar: pick tags, and create new ones, before starting a record.
- Quick start bar: a billable toggle that follows Kimai's rule until you change it.
- **My times**: a continue button on every record starts a new record now with the same
  description, project, activity, tags and billable setting.

## 2.67.0.3

- New: a quick start bar on the dashboard and **My times**. Type what you are working
  on, pick a project and press start; recent descriptions are suggested and fill in their
  project and activity. A running record shows a live clock and a stop button.
- New: a summary report with graphs under **Reporting** > **Summary**, with totals, a bar
  chart per day or month, a doughnut chart and a breakdown per project, customer, activity
  or user, down to each description.
- New: `kimai-console` runs Kimai console commands inside the app. The previously
  documented `docker exec ... bin/console` command could not reach the database.

## 2.67.0.2

- Always stop Kimai before its database when the app stops, and log the shutdown once.

## 2.67.0.1

- Fix the app showing as starting forever in Home Assistant, with no Stop or Restart
  buttons. The app now reports itself as started once Kimai is ready to use.

## 2.67.0

First release.

- Kimai 2.67.0 with a bundled MariaDB 10.11 database.
- Creates the first administrator from the app options.
- Persistent data, plugins and `local.yaml` support through the app configuration folder.
- Uses the Home Assistant time zone as Kimai's default time zone.
- Optional email through any Symfony Mailer transport.
- Opens through **Open web UI**; English and Dutch option descriptions.
