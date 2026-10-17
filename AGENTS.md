# Repository Guidelines

**Project**: WenYinOS ZenTaoPMS 9.9.0 — an **unofficial community build** (<https://github.com/wenyinos/ZenTaoPMS>) combining the ZenTao **9.8.3** core (business logic frozen) with the **10.1** interface. The `9.9.0` version number matches no upstream release by design.

## Project Structure & Module Organization
This repository is a classic ZenTao PHP monolith.
- `module/`: business modules (`bug`, `story`, `task`, `project`, etc.). Typical MVC-style layout with `control.php`, `model.php`, and `view/*.html.php`.
- `framework/`: core runtime and routing (`router.class.php`, base classes).
- `www/`: web entry points and static assets (`index.php`, `api.php`, `js/`, `theme/`).
- `config/`: runtime configuration (keep local overrides in `config/my.php`).
- `db/`: SQL schema/bootstrap files.
- `extension/`: safe extension/customization points.
- `tmp/`: runtime cache/log/temp artifacts (do not commit).

## Build, Test, and Development Commands
No Node/Composer build pipeline is defined in this snapshot. Use PHP and bundled scripts:
- `php -S 127.0.0.1:8080 -t www` — run a local web server for quick UI/API checks.
- `bash bin/init.sh /usr/bin/php http://127.0.0.1:8080` — generate CLI helper scripts (`bin/backup.sh`, `bin/checkdb.sh`, etc.).
- `php bin/ztcli 'http://127.0.0.1:8080/index.php?m=admin&f=checkdb'` — execute a module action from CLI.

## Release Automation
Pushing a `v9.*` tag (e.g. `v9.9.0`) triggers `.github/workflows/release.yml`, which lints all PHP (the two partial-class ext files `module/common/ext/model/mcp.php` and `module/user/ext/model/wyauth.php` are excluded by design — the framework merges them into a class at runtime), checks the tag against `config/config.php::$config->version` and the `VERSION` file, and attaches to the GitHub Release: the full source tarball (top-level layout so it can be extracted straight over a deployment; `config/my.php`, attachments and `.env` keys are not in the archive, and an empty `tmp/` entry is injected) plus the standalone `zentao-mcp-py` client zip. Bump the three version markers together before tagging. The workflow also requires `doc/release-notes/<version>.md` (the release body) and **fails if it is missing** — write it as the increment from the previous version, not the whole feature history.

## Coding Style & Naming Conventions
- Follow existing PHP style: 4-space indentation, K&R braces, and guarded conditionals.
- Keep module names lowercase (`module/bug`, `module/testtask`).
- Use descriptive method names (`getBugs`, `buildSearchForm`) and avoid one-letter variables except loop indexes.
- Keep view templates in `module/<name>/view/*.html.php`; keep business logic out of views.

## Testing Guidelines
Automated PHPUnit-style suites are not configured at repository root. Use focused manual regression:
- Validate changed flows via web UI and the corresponding `ztcli` endpoint.
- For DB-related changes, run `admin->checkdb` from UI or CLI.
- Document tested modules and scenarios in each PR.

## Commit & Pull Request Guidelines
Current history includes short messages (for example `update`, `first commit`). Prefer stronger conventions:
- Commit format: `<module>: <imperative summary>` (example: `bug: fix browse filter reset`).
- One logical change per commit; include schema/config changes explicitly.
- PRs should include: purpose, impacted modules, manual test steps, and screenshots for UI changes.

## Security & Configuration Tips
- Never commit secrets or environment-specific overrides from `config/my.php`.
- Treat `www/data/` and `tmp/` as runtime data.
- Prefer changes in `extension/` when customizing behavior to reduce upgrade risk.

## UI Theme System (10.1 interface port)

The front end was ported from ZenTao 10.1 (`feat/ui-101`), keeping the 9.8.3 core and all business logic untouched.

- **Three layers move together**: HTML skeleton (`module/common/view/{header,header.lite,footer,footer.lite}.html.php`), JS (9.8.3 `all.js` retained + `www/js/compat.js` shim), CSS (10.1 skin base + theme overlay + `www/theme/default/compat.css` compatibility layer). Changing only one layer breaks the UI — the 10.1 skin contains no legacy selectors (`#mainmenu`/`#modulemenu`/`#featurebar`/`#titlebar` are absent from it).
- **`.outer` wrapper is load-bearing**: `header.html.php` keeps `<div id='wrap'><div class='outer'>` inside `main > .container` so ~200 legacy view templates keep their layout JS (`fixTfoot`, `saveOuterBox`, `#wrap>.outer` selectors). Do not remove it when editing the skeleton.
- **Themes**: production mode loads `theme/default/{lang}.{theme}.css` (pre-merged = 10.1 base + theme overlay). Merged bundles are generated, not hand-edited: WenYin purple comes from the 10.1 `purple` overlay with a color map (`#3123ae→#6f42c1` and the rgba equivalents). `www/theme/wenyin/style.css` is the debug-mode counterpart. Registered themes: `wenyin` (default), `lightblue`, `blackberry`; `wenyin` is the default via the deployment `config/my.php`.
- **compat.css** is extracted from the old 9.8.3 skin (49 legacy icons incl. ZenIcon/NewZenIcon font-faces + ~267 layout rules). Regenerate, don't hand-patch.
- **mobile.css** targets the new ids (`#mainHeader`/`#navbar`/`#subNavbar`) and must free the 10.1 skin's `min-width:1280px` on `#header,#main`.
- **Page switchers live in `#pageNav`**: `header.html.php` renders `$lang->modulePageNav` there, so the top-left switchers (project/product/branch/case-library/module) must be built as ZUI `dropdown + search-list` markup (`<button data-toggle='dropdown'>` + `<div id='dropMenu' class='dropdown-menu search-list' data-ride='searchList' data-url='...ajaxGetDropMenu...'>`) and assigned to `$lang->modulePageNav` in the module's `setMenu()`/`setLibMenu()`. The 9.8.3 approach — `showSearchMenu()` + injecting the switcher into a `menu` key via `common::setMenuVars` — is dead: `showSearchMenu` was removed with ZUI 1.8.1, and the injected keys (`product->menu->list`, `caselib->menu->lib`) do not exist in the 10.1 language pack. `ajaxGetDropMenu` endpoints need defaulted `$extra` params when the generated link omits it.
- PHP 8.5 constraint: `printUserBar` uses `$account[0]`, never brace offsets (`$account{0}` is fatal on PHP 8).
- After editing `module/*/ext/` or theme files, clear `tmp/model/*.php` and reload php-fpm.

## 22.6 Feature Port (9.10.0 line)

The 9.10.0 line (`feat/port-22.6-m0`) ports a curated set of ZenTao 22.6 features onto the 9.8.3 core, keeping the 10.1 interface. Plan, per-feature specs, acceptance criteria and the record of implementation deviations live in `doc/porting-22.6-plan.md`; per-version user-facing notes live in `doc/release-notes/`.

Mechanisms introduced by this batch that you must know before touching the related modules:

- **Upgrade convention**: every DB change ships as an **idempotent** method on `module/upgrade/model.php` (probe `information_schema` + `CREATE TABLE IF NOT EXISTS`; seeds only when absent), called from the top of `execute()` — *not* inside the `switch($fromVersion)` block, so cross-version jumps still apply. `db/zentao.sql` must be updated in step for fresh installs. Re-running `execute()` must stay error-free.
- **`errorlog`**: `zt_errorlog` / `zt_errorlogreq` written from the framework's `saveError()` hook. Logging failures are swallowed by design — never let this path throw or affect the response. Cleanup is a `cron` row (`moduleName=errorlog&methodName=deleteLog`).
- **`pivot` (read-only)**: `zt_pivot` / `zt_pivotspec` / `zt_pivotdrill`. Only `preview` (list) and `show` (render) exist — **no designer, version switcher, drill-down or charts**; do not add them casually. `module/pivot/model.php` is a mechanical port of the 22.6 engine and must stay free of `zin` / `bi` / `dataview` / `dimension` / `mark` / `workflowfield` / `execution` / `$app->tab` / `$config->edition`. Built-in pivots are defined in `config->pivot->builtins` and upserted by `code` (never overwriting a row a user edited). A `settings.columns` entry is `array('field' => valueField, 'stat' => sum|count|avg|min|max|distinct, 'slice' => expandField)` — `stat` defaults to `count` and a missing `slice` means "one column, no expansion".
- **CSV batch import/export**: `file->parseImportFile($fileName, $fields, $encode)` is the single entry point (BOM stripping, encoding decision, exact + loose column matching, leading-empty-cell normalisation). It returns `columns` as `列序号 => 字段名`, so always map cells by that index rather than by header order. Do **not** modify `parseCSV()` itself — it is hand-rolled and still relied on by other callers. `export2CSV` prepends a UTF-8 BOM for utf-8 output (global behaviour change by design).
- **`doc`**: `zt_doc.order` (drag sort; `order` is a reserved word — always backtick it), `zt_doclib.type` (`'template'` marks the template scope lib seeded by the upgrade), `zt_docaction` (view-action log behind "recently visited"; `view` writes are throttled to one per 4s and bump `zt_doc.views`). The six `getLibs`-family queries in `module/doc/model.php` all exclude `type = 'template'` so templates never leak into normal library lists — keep that filter when adding new library queries. Categories reuse `zt_module` (`type='docTemplate'` for templates, `type='doc'` for documents).
- **`filter.php` cookies**: with `framework->filterParam = 2`, any cookie a method reads must have a rule in `config/filter.php`, keyed by the **lower-case method name**, with its parent object initialised via `new stdclass()` first. Otherwise the cookie is silently dropped (e.g. `$filter->project->track->cookie['projectTrackOrder'] = 'reg::orderBy';`).
- **Counting SQL**: `dao::$querys` records every statement **twice** — once from `processSQL()` (string build) and once from `query()` — so halve the array length before comparing against a query budget. Execution itself happens once.
- **`helper::substr($string, $length, $append)`** has **no `$start` parameter** — `helper::substr($s, 0, 16)` renders the literal `16`. Use `helper::substr($s, 16)`.

## MCP Integration (zentao-mcp)

`zentao-mcp-py/` provides an MCP server that lets AI agents maintain ZenTao project management from git commits (task/story/bug linking, task status flow, effort logging).

- ZenTao side: `module/mcp/` endpoints + `module/common/ext/model/mcp.php` (open-method extension); client side: `zentao-mcp-py/` (single-file Python client, stdlib only — requires just `python3` and `git`, no PHP on client machines; 68 tools covering task/story/bug/project/product/plan/release/testcase/doc lifecycle, reviews, queries, effort reports and commit sync).
- Thin client by design: it runs local `git log`, keeps per-machine identity (`MCP_ACCOUNT` + `GIT_AUTHOR_MAP`), signs requests and speaks stdio MCP. Per-repo sync state (stored in `zt_config`) and commit log assembly live server-side in `module/mcp/`.
- Auth: signed requests (double MD5 + timestamp freshness ±300s), no user session involved. **Single-channel personal keys only**: each account generates its own key at ZenTao "My → MCP Key" (`module/my/ext/`, stored in `zt_config`) and puts it in the client's `MCP_SECRET`. There is **no shared/team key and no `MCP_STRICT` switch** — every request (read and write) must be signed with a personal key, writes must use the key of the account they claim as `actor` (so accounts cannot be impersonated), and an account without a key simply cannot use MCP (401). Optional `MCP_READONLY` / `MCP_IPS` hardening lives in `module/mcp/.env`; write calls are audited to `tmp/mcp/access-YYYYMMDD.log` attributed as `personal:<account>`.
- Agent workflow: after development work, run `zentao_get_git_commits` → update tasks with `zentao_start_task` / `zentao_record_effort` / `zentao_finish_task` (pass each commit's `authorAccount` as `actor` in multi-user repos) → write links with `zentao_sync_commits`. Commit messages reference objects with `task #N`, `story #N`, `bug #N` markers.
- **Delete endpoints are soft-delete only** (`deleteTask` / `deleteBug` / `deleteStory` / `deleteTestcase` / `deleteDoc`): each calls the module **model** layer's `delete()` (the `framework/model.class.php` override that sets `deleted=1` and writes a `deleted` action), never the control layer — the control layer's `delete()` is gated behind `js::confirm` and `die`s with JS. Every one of them requires an explicit `confirm=yes` query param (`requireConfirm()` → 403 `CONFIRM_REQUIRED`) and mirrors the control layer's dependency handling for tasks (child tasks, parent status, `zt_bug.toTask`, `story->setStage`). Never add physical `DELETE FROM` or an undelete endpoint. `getTask`/`getBug`/`getStory` expose a `deleted` field so a caller can verify a soft delete, since the list endpoints filter `deleted=0`; `deleteDoc` reads `zt_doc` directly because `doc::getById` runs `checkPrivDoc` and can `die` with JS on a session-less MCP request.
- After editing files under `module/*/ext/`, clear `tmp/model/*.php` (extension merge cache) and reload php-fpm. Note `checkPriv.z.mcpkeys.php` must sort after `checkPriv.php` (glob order) so the SSO ticket exchange runs first.
- Do not expose the ZenTao `api`/`editor` entry points (debug interfaces) in web server configs; the MCP endpoints are self-contained. Keep `install.php` / `upgrade.php` / `checktable.php` 404-blocked at runtime as well; when installing or upgrading, temporarily lift only that residue rule (or use the `bin/ztcli` `upgrade-execute` / `upgrade-afterExec` commands, which need no web exposure) and restore it immediately.
- See `zentao-mcp-py/README.md` for client setup (MCP registration, `.env`, stdio JSON examples), `zentao-mcp-py/AGENT-GUIDE.md` for per-tool filling specs and the full tool list.

