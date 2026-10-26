# Quick start bar for Kimai

A quick start bar for [Kimai](https://www.kimai.org/): start and stop time recording from one
bar, without opening a form.

The bar replaces Kimai's own start button and sits on its own row directly below the top bar
of every page, above the page's own buttons and filters.

- **Fields:** "What are you working on?", a project picker grouped by customer, an activity
  picker, a tag picker (which can create tags, with permission) and a billable toggle (with
  permission).
- **Timer mode** (clock icon): a date and start time, and a start button that starts a
  record now, or running since the entered start time. There is no end time. While a record runs,
  the bar shows it in the same fields, editable, with its date and start time, a live clock
  and a stop button. Changes are saved as they are made, one after another, and the stop
  button saves what the bar shows before it stops the record.
- **Manual mode** (list icon): a date with start and end time, and an add button that adds a
  finished record. While a record runs, the bar shows that record with an end time instead
  of the clock, and a save button that finishes it with the entered date, start and end time.
  A record copied with a play button (see below) temporarily takes its place.

The two small icons next to the main button switch modes, as in Toggl Track; the browser
remembers the last choice. Times accept short forms such as `915` or `945p`, and an end time
before the start time means the next day. The date starts on today and moves on to the new
day when the page stays open past midnight, unless another date was chosen.

Kimai's **punch in/out** and **fixed start time** tracking modes do not let users choose
times. In those modes the bar has no date, times or manual mode: records start and stop now,
and the server refuses posted times.

Changing and stopping the running record follow Kimai's `edit` and `stop` permissions for it
(`edit_own_timesheet`, `stop_own_timesheet`, exported records, lockdown). Without `edit` the
running record is shown read-only; without `stop` there is no stop button.

When Kimai starts or stops a record elsewhere on the page, for example with its "repeat"
action, the page reloads so the bar shows the current state.

The same goes for other tabs and windows: every 30 seconds while the page is in view, and when
it comes back into view or gets the focus, the script asks `/timer-bar/status` which record
runs, and reloads the page when that is not the one the bar shows. It waits for a later check
while a reload would lose work: while a Kimai dialog is open, while a field has the focus, and
after the user changed a field that is not saved yet (anything outside the running bar, Kimai's
dialogs and its search filters, plus the running bar's end time). Pages in view keep the
session alive this way.

On **My times** each record gets a play button, a white circle with a green play icon. In
timer mode it continues the record: a new record starts now with the same description,
project, activity, tags and billable setting. In manual mode it copies those into the bar instead, so a date, start and end time
can be entered before adding it.

The running record stands out in Kimai's record lists (**My times** and **All times**): its
row is tinted with the bar's green edge, and a pulsing green play button replaces its play
button and Kimai's actions menu. A click anywhere on the row, except its checkbox and links,
scrolls to the bar, puts the focus in the description and briefly highlights the bar.

Every running clock on the page ticks each second with a blinking colon: the bar's own, and
every duration Kimai shows for a running record (its `[data-since]` elements, in Kimai's
duration format). The script takes those over from Kimai's script, which only updates them
every ten seconds, except the one in Kimai's hidden start button, which keeps the browser tab
title up to date. All colons blink in step with the full seconds.

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
and stylesheet, which browsers keep for a day. A release changes the version in their address.

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
own forms. A name that already exists is reused instead of creating a duplicate, also an
activity that belongs to the selected project. A new activity is global, unless the selected
project only allows its own activities; then it belongs to that project. A new project gets
the creator's teams when Kimai's **copy teams on create** setting is on, as in Kimai's own
form, and a new customer is only kept when Kimai accepts its project too.

The buttons need Kimai's `create_project` and `create_activity` permissions; a new customer
also needs `create_customer`. `Service/QuickCreator.php` creates everything through Kimai's
`CustomerService`, `ProjectService` and `ActivityService`, so Kimai's validation and creation
events apply. The quick start bar and inline timesheet editing plugins carry the same class,
differing only in its namespace; change both together.

## How it starts and stops records

The bar posts to its own routes, `/timer-bar/start`, `/timer-bar/update`, `/timer-bar/stop`
and `/timer-bar/continue`, protected by a CSRF token; in manual mode the running record is
finished through `/timer-bar/finish`. `/timer-bar/status` answers with the ID of the running
record as JSON, or 0. In manual mode the play button reads
the record from `/timer-bar/entry` as JSON, which needs `view_own_timesheet` and only
answers for the user's own records. The script sends changes to the running record to
`/timer-bar/update` in the background and gets JSON back; everything else returns to the
page it was used on, and only to a path on this site. Records are created and stopped through Kimai's `TimesheetService`, and continuing goes
through Kimai's restart events like Kimai's own "repeat" action, so Kimai's own rules apply:

- validation, such as required fields, budgets and locked periods;
- rounding of the start time;
- the limit on running records: when only one may run, starting a new one stops the old one.

The bar is shown to users with the `create_own_timesheet` permission. It only offers, and
only accepts, projects and activities the user may book on according to Kimai's own team
and visibility rules. The billable toggle needs `edit_billable_own_timesheet`, creating tags
needs `create_tag`, and the continue button only accepts the user's own records. New tags are
only saved together with their record, after Kimai accepted both; one request creates at most
20 of them, and further new names are ignored.

## Translations

English and Dutch, in `Resources/translations/`. Error messages use Kimai's
`flashmessages` domain.

## Other plugins

This plugin is one of seven Kimai plugins made to work together: the quick start bar starts
and stops records, short time entries and inline timesheet editing make correcting them
quick, timesheet extras keep the record list tidy, reporting extras show where the time
went, and ODT templates and export extras turn the records into your own documents.
Each one also works on its own. The other six:

- [Reporting extras](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle): quarterly reports, charts on the user reports, a summary report and dashboard widgets.
- [Short time entries](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle): type `945` for 9:45 or `10` for ten minutes in Kimai's forms.
- [Inline timesheet editing](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle): edit your own records right in the record list.
- [Timesheet extras](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle): collapsible days in the record list and closable search filters.
- [ODT templates](https://github.com/FaliseDotCom/kimai-odt-templates-bundle): exports and invoices from your own LibreOffice Writer templates.
- [Export extras](https://github.com/FaliseDotCom/kimai-export-extras-bundle): export templates that keep their column order, with columns you drag into place.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/QuickTimerBarBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
