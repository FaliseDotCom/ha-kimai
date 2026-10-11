# Security audit plan: bundled Kimai plugins

How the plugins in `kimai/bundles/` are audited for security. The plan is reusable: run it
again before a release that adds endpoints, permissions or user-supplied output, and file the
results in `reports/` as `YYYY-MM-DD-security-audit.md`, dated the day of the audit.

## Scope

In scope: every file in the five plugins, at the commit named in the report.

| Plugin | Attack surface |
| ------ | -------------- |
| `QuickTimerBarBundle` | POST endpoints that start, update, stop, finish and continue records; a JSON endpoint that reads one record; quick-create endpoints for customers, projects and activities; the bar template on every page; `timer-bar.js` |
| `InlineTimesheetEditBundle` | JSON endpoints that describe records, list booking options, save changes and quick-create; a user preference; `inline-edit.js` and `input-parsing.js` |
| `ReportingGraphsBundle` | The summary report (form, repository queries, template); chart data on the user reports; two dashboard widgets; `reporting.js` |
| `ShortTimeEntriesBundle` | A script added to every page; `input-parsing.js` |
| `TimesheetExtrasBundle` | A script and stylesheet added to the record lists; `timesheet-days.js` |
| All five | `AssetController`, which serves each plugin's scripts and stylesheets; route and service configuration |

Out of scope: Kimai itself, its dependencies, the Docker image, `run.sh` and the Home Assistant
packaging. Kimai's own behaviour is used as the reference for what a plugin may rely on, for
example its voters, `TimesheetService` validation and CSRF handling.

## Threat model

Kimai runs behind a login. The attackers considered are:

1. **A logged-in user with fewer rights than the victim**, for example a plain user against a
   team lead or administrator, or a user in one team against another team's data. They can
   send any request to any route with their own session.
2. **An outside website** visited by a logged-in user, which can make the browser send
   cross-site requests (CSRF) and load the plugins' routes in frames or scripts.
3. **Stored content** typed by one user and shown to another: descriptions, customer, project,
   activity and tag names, user names and team names. They may contain HTML or script.

An anonymous visitor only reaches the login page; the plugin routes must not change that.

## Checklist

Each plugin is reviewed against these questions.

- **Authentication and authorisation.** Does every route require a login? Does every action
  check Kimai's permission or voter for the record, project, activity, customer or user it
  touches (`edit`, `start`, `stop`, `view_rate_*`, `create_*`, `report:other`, team access)?
  Can a request name another user's record by its ID (IDOR)?
- **Input handling.** Are posted IDs checked against what the user may see, not only cast to
  integers? Can fields be set that the form does not offer (mass assignment), such as the
  user, rates, exported or billable without `edit_billable`? Are dates, times and durations
  validated, and do records still pass Kimai's validation (`TimesheetService`)?
- **CSRF.** Does every state-changing request require POST and a valid CSRF token? Can a GET
  request change anything?
- **Injection.** Are all database queries parameterised (DQL, query builder)? Is any user
  input used in a sort field, column name, raw SQL or a file path?
- **Cross-site scripting.** Does Twig auto-escaping apply everywhere, and is every `|raw` or
  `|json_encode|raw` safe in its context (for example `</script>` inside JSON in a script
  tag)? Does the JavaScript put user content into the page with `innerHTML`, template
  strings, attributes or URLs without escaping? Are translated messages with parameters
  escaped?
- **Information disclosure.** Do JSON responses, chart data and suggestions reveal data the
  user may not see: other users' records, rates and amounts without `view_rate_*`, customers
  or projects outside their teams, hidden or invisible items? Do error messages reveal
  internals?
- **Redirects.** Can a parameter such as `_target_path` send the user to another site
  (open redirect)?
- **Asset serving.** Is the asset controller limited to a fixed list of files, safe from path
  traversal, and does it send the right content type and caching headers?
- **Denial of service.** Are lists of IDs, date ranges and result sets bounded?
- **Client-side storage.** Does anything sensitive end up in `localStorage`?
- **Dependencies on page markup.** Can content from Kimai's page, which a user may partly
  control, make the scripts act on the wrong record or element?

## Method

1. **Independent review.** Each plugin is reviewed by a separate reviewer that did not write
   the code, working only from the source, this checklist and Kimai's own source (read from
   the `kimai/kimai2` image of the version in `config.yaml`). Reviewers report every finding
   with file and line, the attack scenario, its preconditions and a suggested fix, and also
   state which checklist items they checked and found sound.
2. **Verification.** Every finding is checked against the code again, and against Kimai's
   behaviour where it depends on it, before it goes in the report. Findings that do not
   survive are listed as rejected, with the reason.
3. **Dynamic checks** where a finding needs confirming, against a local test instance (see
   [Development](../development.md#2-build-and-run-with-docker)), never against real data.
4. **Report.** The results go in `reports/YYYY-MM-DD-security-audit.md`, with the commit
   audited.

## Severity

| Severity | Meaning |
| -------- | ------- |
| Critical | An outside attacker or any user can take over accounts or read or change all data. |
| High | A user can read or change data of other users or teams they have no right to, or stored XSS reaches other users. |
| Medium | A user can bypass a permission on their own data (for example rates or billable), CSRF on a state change, or an open redirect. |
| Low | Defence in depth: hardening that limits impact if something else fails, minor information leaks, or resource use. |
| Informational | Observations without a direct security impact. |

## Report contents

- Date, commit and plugin versions audited.
- Summary with the number of findings per severity.
- Each finding: severity, plugin, file and line, description, attack scenario, recommendation
  and status (open, fixed in a commit, or accepted with a reason).
- Rejected findings, with why they are not a problem.
- The checklist items that were reviewed and found sound, per plugin.
