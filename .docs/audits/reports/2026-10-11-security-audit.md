# Security audit of the bundled Kimai plugins, 2026-10-11

Results of the [security audit plan](../security-audit-plan.md), run on 2026-10-11.

- **Commit audited:** `dd53736` (app version 2.67.0.26).
- **Plugins and versions:** `QuickTimerBarBundle` 1.2.0, `InlineTimesheetEditBundle` 1.1.1,
  `ReportingGraphsBundle` 1.3.1, `ShortTimeEntriesBundle` 1.0.0, `TimesheetExtrasBundle` 1.0.0.
- **Reference:** Kimai 2.67.0, read from the `kimai/kimai2:2.67.0` image.
- **Method:** four independent reviewers, one each for the quick start bar, inline editing and
  reporting extras, and one for short time entries, timesheet extras and the code all plugins
  share (asset controllers, routes, services, script injection). Each finding was then checked
  again against the plugin code and Kimai's source. The dynamic checks were limited to
  anonymous requests to the asset routes, the redirect check run in PHP, and an HTML parser test
  of the JSON case. No data was changed.

## Summary

- Critical: 0
- High: 1
- Medium: 4
- Low: 4
- Informational: 10

No route is reachable without a login, every state change needs POST and a CSRF token, every
query is parameterised, and no finding allows script injection today. The most important
problems are:

- the summary report showing record descriptions from projects a team lead may not see (SA-01);
- two places where a record change is written to the database before Kimai validates it
  (SA-02);
- the quick start bar not applying Kimai's tracking mode and edit and stop permissions (SA-03,
  SA-04).

All findings are open.

## Findings

### SA-01 — Summary report shows descriptions from projects outside the viewer's teams

- **Severity:** High
- **Plugin:** reporting extras
- **Where:** `Repository/SummaryRepository.php:126-163` (`findRows`),
  `Service/SummaryBuilder.php:116-119`, `Resources/views/summary.html.twig:130-146`.
- **Description:** The summary report selects records only by user (`u.id IN (:users)`). It has
  no team criteria on the project, customer or activity. Kimai hides a team member's records
  on projects, customers or activities restricted to teams the team lead is not in
  (`RolePermissionManager::checkTeamAccessTimesheet`, `TimesheetRepository::addPermissionCriteria`).
  Kimai's own user reports show such records only as project and activity totals, never with
  their descriptions. The summary report adds a breakdown per description, with amounts when
  rates are shown.
- **Attack scenario:** Team lead L has `report:other` (the default for team leads) and leads
  team T. Member M is in T and also in team X. Project "Secret" is restricted to team X. M
  books time on it with the description "Due diligence ACME takeover". Kimai's list, export and
  voters hide the record from L, but L opens the summary report for M, or for all users, and
  reads the description, its hours and, with `view_rate_other_timesheet`, its amount.
- **Recommendation:** When the viewer is not the record's user and cannot see all data, apply
  Kimai's team criteria to the description rows (the project, customer and activity teams are
  empty or include one of the viewer's teams). Keep the totals as Kimai's reports show them, and
  collapse the restricted descriptions into one "other records" line. Test with a member in two
  teams.
- **Status:** Open.

### SA-02 — A record change is saved before Kimai validates it when a new tag is created

- **Severity:** Medium
- **Plugins:** inline editing and quick start bar
- **Where:**
  - Inline editing: `Service/EntryEditor.php:160-169` (fields applied, then
    `validateTimesheet()`), `:354-356`, `Service/BookingOptions.php:138-143`.
  - Quick start bar: `Service/EntryWriter.php:95-117` (`update`), `:208-216` (`createTags`),
    `Repository/TimerBarRepository.php:132-145` (`findOrCreateTag`).
- **Description:** Both plugins create new tags with Kimai's `TagRepository::saveTag()`, which
  calls `persist()` and then a full `flush()`. That flush writes every pending change, including
  the record that is being edited: it is a managed entity and already holds the new date,
  times, project, activity and description. Validation runs afterwards. When it fails, the user
  gets Kimai's error, but the change is already stored. This bypasses every rule that only the
  validator enforces: lockdown periods, a project's locked-until date, exported records, future
  times, overlapping records, budgets, negative durations, and constraints from other plugins.
  Kimai's update events are not dispatched for that write either. Tags created in a refused
  save stay behind.
- **Attack scenario:** Any user with `create_tag`, which `ROLE_USER` has by default, posts an
  inline edit that moves an own record into a locked month together with
  `changes[tags]=<a new tag name>`. Kimai answers "This period is locked", but the record has
  already moved. With the quick start bar, the same works on the running record through
  `/timer-bar/update` and `new_tags`, for example to book it on a locked project. A new tag name
  per request makes it repeatable. Users can also trigger it by accident: any change that
  Kimai refuses is still saved when it adds a new tag.
- **Recommendation:** Never flush before validation. Create a new tag with `new Tag()` and
  `persist()` only, so the record's own save stores both, or create tags only after
  `validateTimesheet()` succeeds. Also run the whole change in
  `TimesheetRepository::begin()`/`commit()`/`rollback()`, as Kimai's `saveNewTimesheet()` does,
  and validate each new `Tag` (see SA-13).
- **Status:** Open.

### SA-03 — The quick start bar ignores Kimai's tracking mode

- **Severity:** Medium
- **Plugin:** quick start bar
- **Where:** `Controller/TimerBarController.php:92-103` (start and add), `:127-133` and
  `:304-319` (begin of the running record), `:166-174` (finish); `Service/EntryWriter.php`.
  Nothing in the plugin reads the tracking mode.
- **Description:** In the "punch in/out" and "fixed start time" tracking modes, Kimai does not
  let users choose start or end times (`canEditBegin()` and `canEditEnd()` are false). Kimai
  enforces this in its forms and API, not in a validator. The bar always accepts a date, start
  and end time, so users can backdate a running record, move its start, finish it at any time
  and add finished records with any times.
- **Attack scenario:** An administrator sets punch in/out so that records reflect real clock-in
  and clock-out times. A user starts a record at 10:00 with a start time of 07:00, or adds an
  eight-hour record for yesterday in manual mode. Kimai accepts it, apart from lockdown and
  future-time rules.
- **Recommendation:** Read `TimesheetService::getActiveTrackingMode()`. Ignore or refuse the date
  and start time when `!canEditBegin()`, and the end time and the finish route when
  `!canEditEnd()`. Hide those fields and manual mode in the template. Optionally allow them with
  `view_other_timesheet`, as Kimai's API does.
- **Status:** Open.

### SA-04 — The quick start bar skips Kimai's edit and stop permissions

- **Severity:** Medium. It only matters when roles are customised.
- **Plugin:** quick start bar
- **Where:** `Controller/TimerBarController.php:116-143` (update), `:153-177` (finish),
  `:231-252` (stop).
- **Description:** Kimai's own edit and stop actions check the `edit` and `stop` voters, which
  require `edit_own_timesheet` and `stop_own_timesheet`. The bar only checks
  `create_own_timesheet` and that the record is the user's own running record. Kimai's validator
  still enforces lockdown, exported records and project locks, but not the role permissions
  themselves.
- **Attack scenario:** An administrator removes `edit_own_timesheet` from a role so that its
  users cannot change their records. A user still changes the running record's project,
  activity, description and start through `/timer-bar/update`, or finishes it with any times
  through `/timer-bar/finish`.
- **Recommendation:** Check `edit` on the record before update and finish, and `stop` before stop.
  Show the running record read-only when `edit` is denied.
- **Status:** Open.

### SA-05 — JSON in script elements can break pages for other users

- **Severity:** Medium for the quick start bar, Low for reporting extras.
- **Plugins:** quick start bar and reporting extras
- **Where:** `QuickTimerBarBundle/Resources/views/bar.html.twig:105`;
  `ReportingGraphsBundle/Resources/views/chart_data.html.twig:16`, which is used by the user
  report charts, the summary report and the working time widget.
- **Description:** Both templates write `{{ data|json_encode|raw }}` inside
  `<script type="application/json">`. Twig's `json_encode` escapes `/`, so `</script>` cannot
  appear and no script can run. It does not escape `<`, so a stored name containing
  `<!--<script>` puts the HTML parser into a state in which the element's own `</script>` no
  longer closes it. An HTML parser test confirmed that the rest of the page, including Kimai's
  scripts, then becomes text inside the element. The JSON no longer parses either.
- **Attack scenario:**
  - Quick start bar: the bar is on every page, and its suggestions include descriptions from
    the user's own records and the names of the projects they booked on. A project named
    `<!--<script>`, by anyone who may create or edit projects (also through the "+" buttons),
    breaks every page for every user who booked on it in the last 120 days, including the
    preferences page where the bar can be turned off.
  - Reporting extras: a user sets their own alias to `<!--<script>`. A team lead who opens the
    summary report grouped by user loses the report from the chart onwards. Project, customer
    and activity names work too.
- **Recommendation:** Encode with HTML-safe flags:
  `json_encode(constant('JSON_HEX_TAG') b-or constant('JSON_HEX_AMP') b-or constant('JSON_HEX_APOS') b-or constant('JSON_HEX_QUOT'))`,
  or move the data into an auto-escaped `data-` attribute, as the other plugins do.
- **Status:** Open.

### SA-06 — Open redirect through a tab character in `_target_path`

- **Severity:** Low. An open redirect is Medium on the plan's scale. It is rated lower because
  Kimai's session cookies are `SameSite=Lax`, so it is only reachable from the same site.
- **Plugin:** quick start bar
- **Where:** `Controller/TimerBarController.php:394-405` (`redirectBack`). It also runs when the
  CSRF token is missing or invalid.
- **Description:** The check `#^/(?![/\\])#` only looks at the second character. `/<tab>/evil.com`
  passes it and is sent unchanged in the `Location` header (checked in PHP in the container).
  Browsers remove tabs and newlines from URLs, so they go to `//evil.com`, another site.
- **Attack scenario:** A page on the same site, such as another app on the same host name, makes
  a logged-in user's browser post to `/en/timer-bar/start` with `_target_path=/%09/evil.com`. No
  token is needed, because the failure path redirects as well. The user lands on the
  attacker's site.
- **Recommendation:** Refuse control characters and whitespace, for example
  `#^/(?![/\\])[^\x00-\x20\x7f]*$#`. Better, only redirect to a path the router matches. Ignore
  `_target_path` when the token is invalid.
- **Status:** Open.

### SA-07 — Quick-created projects ignore "copy teams on create"

- **Severity:** Low
- **Plugins:** quick start bar and inline editing (the shared `Service/QuickCreator.php:135-138`)
- **Description:** Kimai copies the creator's teams to a new project only when a `Context` is
  passed to `ProjectService::saveProject()`, and its own form passes one. The "+" buttons do
  not, so with `project.copy_teams_on_create` turned on, a project created there has no teams
  and is visible to and bookable by every user.
- **Attack scenario:** An administrator turns on "copy teams on create" to keep teams apart. A
  team lead quick-creates "Acme – Secret pitch". Every user in every team sees the project and
  its new customer in their pickers and can book on it.
- **Recommendation:** Call `saveProject( $project, new Context( $user ) )` in both copies.
- **Status:** Open.

### SA-08 — Server error text is shown through Kimai's alert, which inserts HTML

- **Severity:** Low (defence in depth)
- **Plugin:** inline editing
- **Where:** `Resources/public/inline-edit.js:275-285` (`showError`), with messages from
  `Controller/InlineEditController.php` (`describeViolations()`).
- **Description:** Kimai's `alert.error()` builds its dialog by inserting the message into an HTML
  string without escaping (checked in Kimai's built `app.js`). The plugin passes the server's
  error text to it. Today that text comes only from the plugin's translations and Kimai's
  validators, none of which repeat user input. A constraint that does, from another plugin or a
  future Kimai version, would turn this into stored XSS.
- **Recommendation:** Escape the message before passing it, for example with Kimai's `escape`
  plugin (`escapeForHtml`), or show errors in an element filled through `textContent`.
- **Status:** Open.

### SA-09 — The summary report result set is unbounded

- **Severity:** Low (resource use)
- **Plugin:** reporting extras
- **Where:** `Repository/SummaryRepository.php:119-165`, `Controller/SummaryController.php:135-152`,
  `Resources/views/summary.html.twig:131-146`.
- **Description:** The date range is bounded by Kimai's date form, and the grouping is a
  whitelist, but an empty period means all time. For all users, the query returns about one row
  per distinct record. All rows are loaded into memory and every description is rendered.
- **Attack scenario:** On a large installation, a user who may see many users opens the summary
  for all users over all time. A single request can run into PHP's memory or time limit. A plain
  user can only do this over their own records.
- **Recommendation:** Limit the descriptions per group (a top N with an "N more" line), or limit
  the period for all-time queries over all users.
- **Status:** Open.

### Informational

These have no direct security impact. They are recorded so they are not rediscovered as
findings next time, and some are cheap hardening.

- **SA-10 — Kimai's login rule for `/{_locale}/` never matches.** Kimai's `access_control` holds
  the literal pattern `^/{_locale}/`, which Symfony does not expand (confirmed in the compiled
  access map). Every plugin route is protected only by its controller's class-level
  `#[IsGranted]`. All eight controllers have one, and anonymous requests to the asset routes are
  redirected to the login. Keep the class-level attribute a hard rule for new controllers. A
  check that fails on a controller without one would catch mistakes.
- **SA-11 — Asset responses.** The five asset controllers set public caching for a day, but
  Symfony's session listener rewrites it to private and uncached. The assets are therefore
  downloaded again on every page load, which costs performance, not security. If this is
  changed, keep the responses private. The reporting assets need `view_reporting` while the
  dashboard widget that loads them needs only `view_own_timesheet`. A user with the widget but
  without reporting gets no charts.
- **SA-12 — Very large minute values cause a server error.** In inline editing,
  `EntryEditor::parseMinutes()` accepts any number of digits. A huge duration or break gives a
  type error or a date the database refuses. That is a 500 response, and nothing is saved.
  Limit the input length and answer with a validation message.
- **SA-13 — New tags are not validated and their number is not limited.** Both plugins save new
  tags without validating the `Tag` entity (special characters, uniqueness). The quick start bar
  has no server-side limit on the number of new tags per request. Kimai's own tag field behaves
  the same. Validate each new tag and cap the count. Related: when a quick-created project is
  refused, the new customer created for it stays behind (`QuickCreator.php:133-138`).
- **SA-14 — The user report charts can show a different user than Kimai's table.** The chart
  subscriber reads `?user=` itself and accepts disabled team members, which Kimai's form refuses.
  The table then falls back to the viewer while the charts show the disabled member. Access is
  still limited to users the viewer may report on.
- **SA-15 — Array query parameters cause an error page.** `?user[]=1`, `?date[]=x` and
  `?working_time_week[]=x` make Symfony's `InputBag` throw in the reporting subscriber and the
  working time widget. Only that page load fails. Check that the values are strings.
- **SA-16 — The dashboard link subscriber changes shared widget services.** This is harmless
  with PHP's per-request model, which the Kimai image uses. Under a persistent worker runtime it
  would leak one user's link option into other users' requests.
- **SA-17 — The short time entries script tag is added to the two-factor page.** The asset
  request is refused there, so the only effect is a failed script load. Add the tag only when
  the user is fully logged in.
- **SA-18 — Collapsed days are stored per browser, not per user.** Two accounts in one browser
  share the collapsed-days state, which is only dates and a flag. Records in collapsed days stay
  selected by the table's select-all checkbox, as the plugin's README documents.
- **SA-19 — `/timer-bar/entry` does not check `view_own_timesheet`.** It only returns fields of
  the user's own record, without rates.

### Other observations

These two functional bugs came up during the review. They have no security impact.

- In both copies of `QuickCreator`, the search for an existing activity only looks at global
  activities, so an activity that belongs to a project is created again instead of reused,
  contrary to the README.
- In inline editing, a row clicked while another one is being saved fails to open when the
  clicked column has no field (`td.` becomes an invalid selector, `inline-edit.js:378`).

## Adjustments and rejected findings

No finding was rejected. Three were adjusted during verification:

- **The JSON-in-script issue** was reported by three reviewers. It is merged into SA-05 and
  raised to Medium for the quick start bar, because project names also reach the bar's
  suggestions and can break every page for other users.
- **The open redirect (SA-06)** is rated Low instead of Medium, because the `SameSite=Lax`
  session cookies keep it out of reach of other sites.
- **The reporting asset permission and caching notes** from two reviewers are merged into SA-11.

## Checked and found sound

### All plugins

- **Login:** every controller has a class-level `#[IsGranted]`, and anonymous requests are sent
  to the login page (see SA-10).
- **Routes:** each plugin's routes carry the `/{_locale}` prefix and the `app_locales`
  requirement, and none collide with Kimai's own routes.
- **Asset serving:** the file name must be a key of a fixed list, and the path is built from a
  constant folder. Traversal attempts return 404. The content types are explicit, and the cache
  version comes only from the listed files.
- **Script injection:** the subscribers build their tags with `htmlspecialchars( ENT_QUOTES )` and
  URLs from Symfony's URL generator. Twig auto-escaping covers all other output, apart from
  SA-05.
- **Database access:** queries use Doctrine's query builder or `find`/`findBy` with bound
  parameters. No input reaches a sort field, column name, raw SQL or file path.

### Quick start bar

- **Records are the user's own:** the running record is looked up only among the user's own
  running records. Continue and the record lookup compare the owner.
- **Project, activity and tag IDs:** projects and activities come from Kimai's team-aware form
  queries, and the combination is checked. Tags are limited to visible tags.
- **No mass assignment:** the user is always the logged-in user, and rates, exported status and
  meta fields are never read from the request. Billable needs the `edit_billable` voter, with
  whitelisted values.
- **New records:** start, add and continue go through `saveNewTimesheet()` with the `start` or
  `create` voter and validation, so an old record cannot be restarted after access is revoked.
- **CSRF:** every POST route checks the `timer_bar` token before writing, and the GET routes only
  read.
- **No XSS in `timer-bar.js`:** user text goes in through `textContent`, `new Option()`, `title`
  and `dataset`. `innerHTML` only receives static icon markup.
- **Quick-create:** each creation is checked against `create_project`, `create_customer` or
  `create_activity`, and only visible items are reused.
- **Limits:** suggestions are capped at 50 entries and 120 days. Only the timer or manual mode
  string is stored in `localStorage`, and it is checked against a whitelist.

### Inline editing

- **Records:** only finished records the user owns and may edit (owner, `edit` voter) are
  described or saved.
- **Fields:** only fields from `getEditableFields()` are accepted, which follows the tracking mode
  and `edit_billable`.
- **Project and activity IDs:** they are checked against Kimai's team-aware form queries, and the
  activity must fit the project.
- **Options endpoint:** it sends only team-filtered customers, projects and activities, and no
  rates.
- **Limits and CSRF:** the entries endpoint is capped at 500 IDs, and every POST checks the
  `inline_timesheet_edit` token.
- **No XSS in `inline-edit.js`:** user text goes in through `new Option()`, `optgroup.label`,
  `value`, `textContent` and `title`. The only weak spot is SA-08.

### Reporting extras

- **Choosing a user:** the user picker exists only with `report:other` and is limited to team
  members. Its "all users" option uses Kimai's team-aware user query.
- **Amounts:** they appear only in the summary report, with `view_rate_own_timesheet` or
  `view_rate_other_timesheet` as Kimai requires. They never go into chart data or widgets.
- **Links:** detail links are only built for customers and projects the `view` voter allows.
  Period and view links are relative paths generated by the router.
- **Widgets:** "everyone's hours" needs `view_other_timesheet`, as Kimai's own cards do. The
  working time widget only shows the user's own records.
- **Queries:** the grouping is whitelisted twice, and the date range is bounded by Kimai's date
  form (years 1900–2099).
- **No XSS in `reporting.js`:** it uses no HTML sinks. Chart labels and tooltips are drawn on the
  canvas.

### Short time entries

- **No XSS:** the script only writes `value` on Kimai's time and duration fields.
- **Parsing:** its patterns are anchored and only run on the user's own input.
- **Storage and state:** it uses no storage and has no state-changing routes.

### Timesheet extras

- **Escaped attributes:** the `data-list` and `data-messages` attributes are escaped and built
  from constants and static translations.
- **DOM:** the script creates elements and moves Kimai's own rows, with no HTML sinks.
- **Storage:** collapsed days in `localStorage` are shape-checked before use.
