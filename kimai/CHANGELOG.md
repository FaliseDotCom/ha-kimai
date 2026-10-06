# Changelog

All notable changes to this app are documented here. The version number is the Kimai
version the app contains; see the
[Kimai releases](https://github.com/kimai/kimai/releases) for changes in Kimai itself.

## 2.67.0.21

- Reporting extras: a **Your working hours by project** dashboard widget, in the style of the
  report charts: hours per day stacked by project, a doughnut chart of the projects, and
  totals for today, the week, the month and the year that open the user reports. Add it
  under the dashboard's **Settings**, in place of Kimai's **My working hours**.
- Reporting extras: an **Hours overview** dashboard widget with today, this week, this month and
  this year under **My hours**, and, for users who may see other people's records, the same
  for everyone under **Everyone's hours**. Every card opens the matching report. It replaces
  Kimai's duration cards, whose titles do not say they count everyone's hours.
- Reporting extras: the icons of the dashboard's duration cards open the matching report:
  **Today**, **This week**, **This month** and **This year** (everyone's hours) the reports for
  all users, and the **My working hours ...** cards your own user reports.

## 2.67.0.20

- The quick start bar and reporting extras plugins get more distinctive folder names, so they
  cannot clash with other Kimai plugins: `QuickTimerBarBundle` and `ReportingGraphsBundle`.
  If you turned either off with `plugins/TimerBarBundle/.disabled` or
  `plugins/ReportingBundle/.disabled`, rename that folder; the old one can be removed. The
  **Show quick start bar** preference keeps its setting.
- Quick start bar and inline timesheet editing: **+** buttons next to the project and
  activity pickers add a new project (with a new or existing customer) or activity from just
  its name, and select it. Kimai's defaults apply; everything can be changed later.
- Inline timesheet editing: switching to another window or app, for example to take a
  screenshot, no longer saves and closes the record being edited.

## 2.67.0.19

- Rebuilds the app so installations that already showed 2.67.0.18 pick up the split of the
  UI improvements into **Short time entries** and **Inline timesheet editing** (see below).

## 2.67.0.18

- The UI improvements plugin is split in two: **Short time entries** (typing `945` for 9:45)
  and **Inline timesheet editing**. If you turned the UI improvements off with
  `plugins/UiImprovementsBundle/.disabled`, use `plugins/ShortTimeEntriesBundle/.disabled`
  and `plugins/InlineTimesheetEditBundle/.disabled` instead; the old folder can be removed.
  The **Edit records directly in the list** preference keeps its setting.
- Inline timesheet editing: clicking one of your own records on **My times** or **All times**
  now turns the whole record into fields. Tab moves between them, Enter or leaving the record
  saves all changes at once, and Escape cancels.
- Inline timesheet editing: clicking a record no longer opens Kimai's edit dialog, also for
  other users' records. Use **Edit** in the record's actions menu instead.

## 2.67.0.17

- UI improvements: your own records on **My times** and **All times** can be edited directly
  in the list. Click a date, time, duration, break, customer, project, activity, description,
  tags or billable cell to change it. An **Edit records directly in the list** preference
  turns this off per user.
- Quick start bar: always on its own row directly below the top bar, on every screen size,
  instead of inside the top navigation on wide screens.
- Quick start bar: date, start time and end time are always shown; the clock button is gone.
  No times start a timer now, a start time alone starts a timer from that time, and a start
  and end time add a finished record. The running record's date can be changed too.
- Quick start bar: a **Show quick start bar** preference lets each user turn the bar off and
  get Kimai's own start button back.
- Quick start bar: no longer appears a second time on **My times** after the entry table
  reloads, for example after filtering or adding a record.

## 2.67.0.16

- Quick start bar: a clock button switches to entering a start and end time (and date), to
  add a finished record instead of starting a timer. The choice is remembered.
- Quick start bar: the running record can be edited in the bar. Description, project,
  activity, tags, billable setting and start time are saved as soon as they change, and the
  list on **My times** updates with them.

## 2.67.0.15

- New plugin, **UI improvements**: times and durations can be typed in short form. `945`
  becomes 9:45 as a start or end time, and `10` becomes ten minutes as a duration. A plain
  number in a duration field now means minutes instead of hours.

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
