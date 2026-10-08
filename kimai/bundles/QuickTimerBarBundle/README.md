# Quick start bar for Kimai

A quick start bar for [Kimai](https://www.kimai.org/): start and stop time recording from one
bar, without opening a form.

The bar replaces Kimai's own start button and sits on its own row directly below the top bar
of every page, above the page's own buttons and filters.

- **Fields:** "What are you working on?", a project picker grouped by customer, an activity
  picker, a tag picker (which can create tags, with permission) and a billable toggle (with
  permission).
- **Timer mode** (clock icon): a start button that starts a record now. While a record runs,
  the bar shows it in the same fields, editable, with its date and start time, a live clock
  and a stop button. Changes are saved as they are made.
- **Manual mode** (list icon): a date with start and end time, and an add button that adds a
  finished record. While a record runs, the bar shows that record with an end time instead
  of the clock, and a save button that finishes it with the entered date, start and end time.
  A record copied with a play button (see below) temporarily takes its place.

The two small icons next to the main button switch modes, as in Toggl Track; the browser
remembers the last choice. Times accept short forms such as `915` or `945p`, and an end time
before the start time means the next day.

When Kimai starts or stops a record elsewhere on the page, for example with its "repeat"
action, the page reloads so the bar shows the current state.

On **My times** each record gets a play button. In timer mode it continues the record: a
new record starts now with the same description, project, activity, tags and billable
setting. In manual mode it copies those into the bar instead, so a date, start and end time
can be entered before adding it.

Each user can turn the bar off with **Show quick start bar** in their preferences; Kimai's
own start button then returns. The bar is on by default.

Typing in the description field suggests the user's recent descriptions (last 120 days).
Picking one fills in the project and activity it was last booked on. The activity picker
only offers activities that can be booked on the selected project, and selects the one used
most recently with it.

It ships with the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md), but works in any Kimai
installation.

## Requirements

Kimai 2.67.0 or later. No JavaScript build step is needed; the plugin serves its own script
and stylesheet.

## Installation

1. Download the zip of the latest [release](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle/releases)
   and unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/QuickTimerBarBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## New projects and activities

A **+** button next to the project picker adds a project from just its name and its customer,
an existing one or a new one; a **+** next to the activity picker adds an activity. The new
item is selected straight away. Kimai's defaults apply (a new customer gets the default
country, currency, language and time zone), and everything can be changed later in Kimai's
own forms. A name that already exists is reused instead of creating a duplicate. A new
activity is global, unless the selected project only allows its own activities; then it
belongs to that project.

The buttons need Kimai's `create_project` and `create_activity` permissions; a new customer
also needs `create_customer`. `Service/QuickCreator.php` creates everything through Kimai's
`CustomerService`, `ProjectService` and `ActivityService`, so Kimai's validation and creation
events apply. The quick start bar and inline timesheet editing plugins carry the same class,
differing only in its namespace; change both together.

## How it starts and stops records

The bar posts to its own routes, `/timer-bar/start`, `/timer-bar/update`, `/timer-bar/stop`
and `/timer-bar/continue`, protected by a CSRF token; in manual mode the running record is
finished through `/timer-bar/finish`. In manual mode the play button reads
the record from `/timer-bar/entry` as JSON, which only answers for the user's own records. The script sends changes to the running
record to `/timer-bar/update` in the background and gets JSON back; everything else returns
to the page it was used on. Records are created and stopped through Kimai's `TimesheetService`, and continuing goes
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

## Source

This plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository, in
`kimai/bundles/QuickTimerBarBundle/`. The
[kimai-quick-timer-bar-bundle](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle) repository
is a read-only mirror of that folder for releases: report issues and send changes to the app
repository.

## License

MIT, see [LICENSE](LICENSE).
