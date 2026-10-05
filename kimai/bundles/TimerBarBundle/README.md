# Quick start bar for Kimai

A quick start bar for [Kimai](https://www.kimai.org/): start and stop time recording from one
bar, without opening a form.

On screens at least 1200 pixels wide the bar sits in the top navigation of every page, in
place of Kimai's own start button. On narrower screens Kimai's own button stays, and the bar
appears above the content of the dashboard and **My times**.

- **Idle:** "What are you working on?", a project picker grouped by customer, an activity
  picker, a tag picker (which can create tags, with permission), a billable toggle (with
  permission) and a start button.
- **Running:** the description, project, customer, activity, tags and billable state of the
  running record, a live clock and a stop button.

When Kimai starts or stops a record elsewhere on the page, for example with its "repeat"
action, the page reloads so the bar shows the current state.

On **My times** each record gets a play button that continues it: a new record starts now
with the same description, project, activity, tags and billable setting.

Typing in the description field suggests the user's recent descriptions (last 120 days).
Picking one fills in the project and activity it was last booked on. The activity picker
only offers activities that can be booked on the selected project, and selects the one used
most recently with it.

It ships with the [Kimai app for Home Assistant](../../DOCS.md), but works in any Kimai
installation.

## Requirements

Kimai 2.67.0 or later. No JavaScript build step is needed; the plugin serves its own script
and stylesheet.

## Installation

1. Copy this `TimerBarBundle` folder to `var/plugins/TimerBarBundle/` in your Kimai installation.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## How it starts and stops records

The bar posts to its own routes, `/timer-bar/start`, `/timer-bar/stop` and
`/timer-bar/continue`, protected by a CSRF token, and then returns to the page it was used
on. Records are created and stopped through Kimai's `TimesheetService`, and continuing goes
through Kimai's restart events like Kimai's own "repeat" action, so Kimai's own rules apply:

- validation, such as required fields, budgets and locked periods;
- rounding of the start time;
- the limit on running records: when only one may run, starting a new one stops the old one.

The bar is shown to users with the `create_own_timesheet` permission. It only offers, and
only accepts, projects and activities the user may book on according to Kimai's own team
and visibility rules. The billable toggle needs `edit_billable_own_timesheet`, creating tags
needs `create_tag`, and the continue button only accepts the user's own records.

## Translations

English and Dutch, in `Resources/translations/`. Error messages use Kimai's
`flashmessages` domain.

## License

MIT, see the [repository license](../../../LICENSE).
