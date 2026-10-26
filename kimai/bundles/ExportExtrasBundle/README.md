# Export extras for Kimai

Improvements to [Kimai](https://www.kimai.org/)'s exports. It ships with the
[Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md),
but works in any Kimai installation.

## Column order of export templates

Kimai's export templates (the **+** and pencil buttons below the preview on the **Export**
page) export their columns in the order they were picked. The form doesn't show that order,
though: when you open a template again, the picked columns are listed in the order of Kimai's
own column list. Saving the form then stores that order, so editing a template's name quietly
reorders its export.

With this plugin:

- The form shows the columns in the template's saved order, so saving it keeps that order.
- Drag a column to another place to change the order. A line below the columns says so.

## How it works

`ExportColumnsTypeExtension` is a form type extension on Kimai's `ExportColumnsType`, the
columns field of the export template form. It sets Kimai's own `order` option of its select
boxes, which turns on Tom Select's `drag_drop` plugin, and adds the saved columns, in order,
as a JSON list in the select box's `data-column-order` attribute. Kimai already stores the
columns in the order the browser sends them, and Tom Select sends them in the order shown.

Tom Select sets up a select box with its picked options in the order of the page, which is
Kimai's column list. `export-columns.js` therefore waits for the box to be set up and then puts
the picked columns in the saved order with Tom Select's `setValue`, without a change event, so
the dialog does not count it as an edit. Kimai opens the form in a dialog on the **Export**
page by inserting its markup and then setting up its select boxes, so the script watches the
page with a `MutationObserver`. It restores each box once.

The script is added through Kimai's `ThemeEvent::JAVASCRIPT` on the `export`,
`export_template_create` and `export_template_edit` routes, for users with Kimai's
`create_export_template` permission. The plugin serves it itself; browsers keep it for a day,
and a release changes the version in its address.

## Requirements and installation

Kimai 2.67.0 or later.

1. Download the zip of the latest
   [release](https://github.com/FaliseDotCom/kimai-export-extras-bundle/releases) and
   unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/ExportExtrasBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## Translations

English and Dutch, in `Resources/translations/messages.*.xlf`.

## Other plugins

This plugin is one of seven Kimai plugins made to work together: the quick start bar starts
and stops records, short time entries and inline timesheet editing make correcting them
quick, timesheet extras keep the record list tidy, reporting extras show where the time
went, and ODT templates and export extras turn the records into your own documents.
Each one also works on its own. The other six:

- [Quick start bar](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle): start and stop time recording from one bar below the top bar, and start a record again with its play button.
- [Reporting extras](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle): quarterly reports, charts on the user reports, a summary report and dashboard widgets.
- [Short time entries](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle): type `945` for 9:45 or `10` for ten minutes in Kimai's forms.
- [Inline timesheet editing](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle): edit your own records right in the record list.
- [Timesheet extras](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle): collapsible days in the record list and closable search filters.
- [ODT templates](https://github.com/FaliseDotCom/kimai-odt-templates-bundle): exports and invoices from your own LibreOffice Writer templates.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-export-extras-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/ExportExtrasBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
