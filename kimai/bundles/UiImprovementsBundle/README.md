# UI improvements for Kimai

Small improvements to [Kimai](https://www.kimai.org/)'s forms and record lists. It ships with the
[Kimai app for Home Assistant](../../DOCS.md), but works in any Kimai installation.

## Quicker time and duration entry

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

## Editing records in the list

On **My times** and **All times**, the cells of the user's own records can be edited in
place: date, start, end, duration, break, customer and project (a project picker grouped by
customer), activity, description, tags (comma-separated) and billable (toggles on click).
Enter or leaving the field saves, Escape cancels; Kimai then reloads the list. When a new
project does not allow the record's activity, an activity picker follows and both are saved
together.

Only records the user owns and may edit are offered, and only the fields Kimai's tracking
mode and permissions allow (billable needs `edit_billable`, new tags need `create_tag`).
Other cells, and other users' records, keep opening Kimai's edit dialog. Every change goes
through `TimesheetService`, so Kimai's validation, rounding and rate calculation apply, and a
refusal is shown with Kimai's own message.

Each user can turn this off with the **Edit records directly in the list** preference
(`inline_edit_enabled`, on by default).

## How it works

**Short time and duration entry:** one module script, added to every page for logged-in
users through Kimai's `ThemeEvent::JAVASCRIPT`. It listens for `change` events in the capture phase, so it also works in forms that Kimai
loads into dialogs later, and runs before Kimai's own handlers. Fields are recognised by
Kimai's own markup: `input[data-timepicker="on"]` for times and `input.duration-input` for
durations. Before a form is submitted, all its time and duration fields are completed too.
The parsing lives in `input-parsing.js`, which the scripts import with their own version
query, so a release never mixes old and new files from the browser cache.

**Editing in the list:** on the `timesheet` and `admin_timesheet` routes, and only when the
preference is on, `inline-edit.js` and `inline-edit.css` are added too. The script reads the
record IDs from the rows' edit links, asks `InlineEditController` which of them the user owns
and may edit (with their raw values and editable fields), and marks those cells by Kimai's
`col_*` column classes. Clicks on a marked cell are handled in the capture phase and kept
from Kimai's row handler, which would open the edit dialog. A change is posted as one field
and value; times are sent as 24-hour `HH:MM`, durations in minutes. After a save the script
dispatches `kimai.timesheetUpdate`, Kimai reloads the list, and `kimai.reloadedContent`
marks the new rows.

## Requirements and installation

Kimai 2.67.0 or later.

1. Copy this `UiImprovementsBundle` folder to `var/plugins/UiImprovementsBundle/` in your
   Kimai installation.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## License

MIT, see the [repository license](../../../LICENSE).
