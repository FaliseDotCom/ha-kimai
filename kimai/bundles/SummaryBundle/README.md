# Graphs for Kimai

Charts for [Kimai](https://www.kimai.org/)'s reports.

## Charts on the user reports

Kimai's **Weekly view for one user**, **Monthly view for one user** and **Yearly view for one
user** get two charts above their table:

- a bar chart of the hours per day (per month in the yearly view), stacked by project;
- a doughnut chart of each project's share of the period.

The charts follow the period and user picked in the report, including a financial year when
one is configured. In the table, customer and project names link to their detail pages for
users who may view them.

## Summary report

**Reporting** > **Summary** shows a period on one page:

- totals for the period: total time, billable time and, with permission, the amount;
- a stacked bar chart of the time per day (periods up to 62 days) or per month;
- a doughnut chart of each group's share;
- a breakdown per project, customer, activity or user that opens to show the time per
  description.

The period comes from Kimai's own date range picker, with its presets for weeks, months,
quarters and years, plus arrows to step back and forward. Without a period ("all time") the
report covers everything from the first to the last record.

It ships with the [Kimai app for Home Assistant](../../DOCS.md), but works in any Kimai
installation.

## Requirements

Kimai 2.67.0 or later. It uses the Chart.js build that Kimai ships, so it needs no assets of
its own beyond one script and one stylesheet, which the plugin serves itself.

## Installation

1. Copy this `SummaryBundle` folder to `var/plugins/SummaryBundle/` in your Kimai installation.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## Permissions

| Who                                          | Sees in the summary report                      |
| -------------------------------------------- | ----------------------------------------------- |
| Users with `view_reporting`                  | Their own time                                  |
| Users who may see other users' reports       | A user picker with every user they may see, and **All users** |
| Users with `view_rate_own_timesheet` / `view_rate_other_timesheet` | Amounts                    |

"Other users' reports" is Kimai's `report:other` check: `view_other_reporting` plus
`view_other_timesheet`. Team leads only see their team members, as in Kimai's own reports.

## Translations

English and Dutch, in `Resources/translations/summary.*.xlf`.

## License

MIT, see the [repository license](../../../LICENSE).
