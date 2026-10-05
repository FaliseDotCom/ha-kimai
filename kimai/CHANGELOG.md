# Changelog

All notable changes to this app are documented here. The version number is the Kimai
version the app contains; see the
[Kimai releases](https://github.com/kimai/kimai/releases) for changes in Kimai itself.

## 2.67.0.4

- Timer bar: pick tags, and create new ones, before starting a record.
- Timer bar: a billable toggle that follows Kimai's rule until you change it.
- **My times**: a continue button on every record starts a new record now with the same
  description, project, activity, tags and billable setting, like Toggl.

## 2.67.0.3

- New: a Toggl-style timer bar on the dashboard and **My times**. Type what you are working
  on, pick a project and press start; recent descriptions are suggested and fill in their
  project and activity. A running record shows a live clock and a stop button.
- New: a Toggl-style summary report under **Reporting** > **Summary**, with totals, a bar
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
