---
repo: kimai/www.kimai.org
branch: falisedotcom-plugins
title: Add five plugins by FaliseDotCom to the store
patch: 2026-10-25-kimai-store-plugins.patch
url:
---

This adds five free plugins to the store, with a developer entry for me (`falisedotcom`).
They were written for the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai),
but work in any Kimai 2.67.0 or later, and are made to work together:

| Plugin | Repository | What it does |
| --- | --- | --- |
| Quick start bar | [kimai-quick-timer-bar-bundle](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle) | Start and stop time recording from one bar below the top bar of every page |
| Reporting extras | [kimai-reporting-graphs-bundle](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle) | Quarterly reports, charts on the user reports, a summary report and dashboard widgets |
| Short time entries | [kimai-short-time-entries-bundle](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle) | Type `945` for 9:45 or `10` for ten minutes in time and duration fields |
| Inline timesheet editing | [kimai-inline-timesheet-edit-bundle](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle) | Edit your own records right in the record list |
| Timesheet extras | [kimai-timesheet-extras-bundle](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle) | Collapsible days in the record list and closable search filters |

For the review checklist:

- **composer.json:** each has a name, description, version, `homepage` and `support` links,
  and `extra.kimai` with `require: 26700` and a display name.
- **Translations:** English and Dutch, in each plugin's `Resources/translations/`.
- **Installation:** a GitHub release zip per version that unzips into `var/plugins/`, or
  `git clone` into `var/plugins/<BundleName>`; no database changes, only
  `bin/console kimai:reload`.
- **Permissions:** none of the plugins adds a permission. Every controller requires a login
  or one of Kimai's own permissions with a class-level `#[IsGranted]`.
- **Documentation:** each repository's README covers the functionality and how it works.
- **Support:** each repository has its own issue tracker, linked from its store page.

Store data is in English and Dutch; I left the other locales to your translation script.
I have not added screenshots yet. I'm happy to add some if you'd like them before merging.
