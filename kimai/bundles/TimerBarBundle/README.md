# Timer bar for Kimai

A Toggl-style timer bar for [Kimai](https://www.kimai.org/). It adds a bar to the top of the
dashboard and **My times**:

- **Idle:** "What are you working on?", a project picker grouped by customer, an activity
  picker and a start button.
- **Running:** the description, project, customer and activity of the running record, a live
  clock (also shown in the browser tab title) and a stop button.

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

The bar posts to its own routes, `/timer-bar/start` and `/timer-bar/stop`, protected by a
CSRF token, and then returns to the page it was used on. Records are created and stopped
through Kimai's `TimesheetService`, so Kimai's own rules apply:

- validation, such as required fields, budgets and locked periods;
- rounding of the start time;
- the limit on running records: when only one may run, starting a new one stops the old one.

The bar is shown to users with the `create_own_timesheet` permission. It only offers, and
only accepts, projects and activities the user may book on according to Kimai's own team
and visibility rules.

## Translations

English and Dutch, in `Resources/translations/`. Error messages use Kimai's
`flashmessages` domain.

## License

MIT, see the [repository license](../../../LICENSE).
