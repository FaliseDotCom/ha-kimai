# ODT templates for Kimai

Exports and invoices for [Kimai](https://www.kimai.org/) from your own OpenDocument text
templates (`.odt`), made in LibreOffice Writer. Lay the document out as you like, with your
own fonts, logo and table styles, and put placeholders such as `${entry.date}` where Kimai's
values go. It ships with the
[Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md),
but works in any Kimai installation.

## Exports

Every `.odt` file in Kimai's export template folder, `var/export/`, becomes an export:

- On **My times** and **All times**, it appears in the **Export** menu, named after the
  file: `Timesheet.odt` shows as **Timesheet**. It exports the records the list's filter
  shows, such as one project for last quarter.
- On the **Export** page, the templates sit under an **ODT** button next to PDF, Excel and
  CSV.

The records are listed oldest first. A new or changed template is used straight away;
there is no need to rebuild Kimai's cache.

## Invoices

Every `.odt` file in Kimai's invoice document folder, `var/invoices/`, can be chosen as the
**Invoice document** of an invoice template, under **Invoices** > **Templates**. Invoices
use the same placeholders as Kimai's own Word documents, listed in
[Kimai's invoice template documentation](https://www.kimai.org/documentation/invoice-templates.html).

## Uploading templates

Kimai's own upload only accepts DOCX, ODS and XLSX files, so the plugin has its own page:
**Invoices** > **Templates** > **ODT templates**. It needs the same permission as Kimai's
upload, `upload_invoice_template`, which only the super admin has by default.

- Choose the file and whether it is for **Export** or **Invoice**, and press **Upload**.
  The file must be an OpenDocument text file of at most 2 MB, PHP's upload limit in the
  Kimai images.
- The file name becomes ASCII letters, digits, dashes and underscores, with spaces as
  dashes: `Uren MediaWise.odt` is stored as `Uren-MediaWise.odt` and shows as **Uren
  MediaWise** in the export menu.
- A template with the same name is only replaced when **Replace a template with the same
  name** is ticked. An invoice document may not share its name with another invoice
  document, such as Kimai's own `invoice.html.twig` for `invoice.odt`, because Kimai would
  then use only one of them.
- The list below the form shows all ODT templates, to download or delete. An invoice
  document that an invoice template uses cannot be deleted, as in Kimai's own list.

Templates can also be copied into the folders directly.

## Writing a template

Start from one of the examples in [`Resources/examples/`](Resources/examples/): a timesheet
export and an invoice. Open it in LibreOffice Writer, change it, save it as ODF Text
Document (`.odt`), and upload it.

- **Placeholders** are written as `${name}`, for example `${export.projects}`. Formatting
  applied to the placeholder, such as bold or a font, carries over to the value.
- **Repeating rows.** The table row that holds an `${entry.…}` placeholder is repeated once
  per record. Outside a table, the list item or paragraph with the placeholder repeats. A
  row with only document-wide placeholders, such as a total row, appears once. Kimai's Word
  documents mark the row with `${entry.row}`; that works here too, but is not needed.
- **Line breaks and tabs** in a value, such as a description over several lines, stay line
  breaks and tabs.
- **Unknown placeholders** stay in the document as written, so a typing error is easy to
  spot. In a repeated row, an `${entry.…}` value that a record does not have, such as an
  empty custom field, is left empty.
- Writer sometimes stores a placeholder in pieces, for example after a spelling correction
  or a formatting change halfway. The plugin puts the pieces back together, and the value
  takes the formatting of the placeholder's first character.

### Values of each record

Each record has the same `entry.…` values as in Kimai's invoice documents; the most useful:

| Placeholder | Value |
| --- | --- |
| `${entry.date}` | Date of the record |
| `${entry.begin_time}`, `${entry.end_time}` | Start and end time |
| `${entry.duration_format}` | Duration in hours and minutes, such as `2:30` |
| `${entry.duration_decimal}` | Duration in decimal hours, such as `2.50` |
| `${entry.description}` | Description |
| `${entry.description_safe}` | Description, or the activity's name when there is none |
| `${entry.activity}`, `${entry.project}` | Activity and project name |
| `${entry.user_display}` | Name of the user who recorded it |
| `${entry.tags}` | Tags, separated by commas |
| `${entry.rate}`, `${entry.total}` | Hourly rate and amount, with currency |
| `${entry.meta.name}` | The record's custom field with the internal name `name` |

### Values of an export

| Placeholder | Value |
| --- | --- |
| `${export.begin}`, `${export.end}` | The filter's period, or the first and last record's date |
| `${export.date}` | Today's date |
| `${export.count}` | Number of records |
| `${export.duration}` | Total duration in hours and minutes |
| `${export.duration_decimal}` | Total duration in decimal hours |
| `${export.total}` | Total amount, with currency when all records share one |
| `${export.total_nc}`, `${export.total_plain}` | Total amount without currency, and as a plain number |
| `${export.customers}`, `${export.projects}` | The customers and projects of the records, separated by commas |
| `${export.activities}`, `${export.users}` | The activities and users of the records, separated by commas |
| `${export.author}` | Name of the user who exports |

Dates, times and amounts are formatted for the locale in the exporting user's preferences.

### Permissions

Exports follow Kimai's own rules. Rates and amounts (`entry.rate…`, `entry.total…` and
`export.total…`) stay empty for users who may not see rates, and custom fields that Kimai
hides from users are left out.

## How it works

Kimai registers its exports while it builds its service container, but templates are files
that come and go. The plugin therefore sets a configurator on Kimai's `ServiceExport`
service: each time Kimai creates that service, `ExportRendererRegistrar` reads the export
folders configured in `kimai.export.documents` and adds an `OdtExportRenderer` per file,
both as export renderer and as timesheet exporter. When another plugin already set a
configurator on the service, the plugin leaves it alone and offers no exports.

For the record values, `ExportDataBuilder` hands the records to Kimai's own invoice code: an
unsaved invoice model with the default calculator and all registered item hydrators. So
`entry.…` means the same in exports as in invoices, including values that other plugins
add. For invoices, `OdtInvoiceRenderer` implements Kimai's invoice `RendererInterface` and
claims documents with the `.odt` extension; Kimai's `InvoiceDocumentRepository` already
lists every file in the invoice folders.

`OdtDocument` fills the template. It opens the `.odt` package as a zip and edits
`content.xml` (the body) and `styles.xml` (headers and footers) with PHP's DOM extension:

1. In every paragraph, placeholders split over several text nodes are joined into the node
   where they start.
2. The nearest table row, list item or paragraph around each `${entry.…}` placeholder is
   cloned once per record, filled, and the original removed.
3. The remaining placeholders are replaced throughout. Line breaks and tabs in values become
   `text:line-break` and `text:tab` elements, and control characters that XML does not allow
   are dropped.

The rest of the package is copied unchanged, including the uncompressed `mimetype` entry
that must come first.

The upload page is `TemplateController`, at `/invoice/odt-templates`, guarded by
`#[IsGranted( 'upload_invoice_template' )]`. `TemplateLibrary` lists, stores and deletes
the files. Uploads go to the last configured folder that is not Kimai's own, as Kimai does
for invoice documents: `var/export/` and `var/invoices/` by default. An upload is only
stored when it has the `.odt` extension and is a zip archive whose `mimetype` entry names
the ODT type, with a `content.xml`. `ActionsSubscriber` adds the page's button to Kimai's
invoice templates page (`actions.invoice_templates`), and the download and delete buttons
to its list. Delete links carry a CSRF token and ask for confirmation first.

## Requirements and installation

Kimai 2.67.0 or later, with PHP's `zip` and `dom` extensions, which the official Kimai
images include.

1. Download the zip of the latest
   [release](https://github.com/FaliseDotCom/kimai-odt-templates-bundle/releases) and
   unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/OdtTemplatesBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.
3. Upload your templates on the **ODT templates** page, or copy them into `var/export/` and
   `var/invoices/`.

There are no database changes.

## Translations

English and Dutch: the upload page in `Resources/translations/odt_templates.*.xlf`, and
the **ODT** button label on the export page in `Resources/translations/messages.*.xlf`.

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
- [Export extras](https://github.com/FaliseDotCom/kimai-export-extras-bundle): export templates that keep their column order, with columns you drag into place.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-odt-templates-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/OdtTemplatesBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
