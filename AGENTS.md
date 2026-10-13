# Repository Guidelines

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
- PHP 8.5 constraint: `printUserBar` uses `$account[0]`, never brace offsets (`$account{0}` is fatal on PHP 8).
- After editing `module/*/ext/` or theme files, clear `tmp/model/*.php` and reload php-fpm.

## MCP Integration (zentao-mcp)

`zentao-mcp-py/` provides an MCP server that lets AI agents maintain ZenTao project management from git commits (task/story/bug linking, task status flow, effort logging).

- ZenTao side: `module/mcp/` endpoints + `module/common/ext/model/mcp.php` (open-method extension); client side: `zentao-mcp-py/` (single-file Python client, stdlib only — requires just `python3` and `git`, no PHP on client machines; 63 tools covering task/story/bug/project/product/plan/release/testcase/doc lifecycle, reviews, queries, effort reports and commit sync).
- Thin client by design: it runs local `git log`, keeps per-machine identity (`MCP_ACCOUNT` + `GIT_AUTHOR_MAP`), signs requests and speaks stdio MCP. Per-repo sync state (stored in `zt_config`) and commit log assembly live server-side in `module/mcp/`.
- Auth: signed requests (double MD5 + timestamp freshness ±300s), no user session involved. Two-tier keys: a team shared `MCP_SECRET` (`module/mcp/.env`, transition mode) and per-account personal keys generated by each user at ZenTao "My → MCP Key" page (`module/my/ext/`, stored in `zt_config`). Write calls must be signed with the actor account's personal key once generated, so accounts cannot be impersonated; reads accept any valid key. Optional `MCP_STRICT` (reject shared-key writes), `MCP_READONLY`, `MCP_IPS` hardening; write calls are audited to `tmp/mcp/access-YYYYMMDD.log` with key attribution (`shared` / `personal:<account>`).
- Agent workflow: after development work, run `zentao_get_git_commits` → update tasks with `zentao_start_task` / `zentao_record_effort` / `zentao_finish_task` (pass each commit's `authorAccount` as `actor` in multi-user repos) → write links with `zentao_sync_commits`. Commit messages reference objects with `task #N`, `story #N`, `bug #N` markers.
- After editing files under `module/*/ext/`, clear `tmp/model/*.php` (extension merge cache) and reload php-fpm. Note `checkPriv.z.mcpkeys.php` must sort after `checkPriv.php` (glob order) so the SSO ticket exchange runs first.
- Do not expose the ZenTao `api`/`editor` entry points (debug interfaces) in web server configs; the MCP endpoints are self-contained.
- See `zentao-mcp-py/README.md` for client setup (MCP registration, `.env`, stdio JSON examples), `zentao-mcp-py/AGENT-GUIDE.md` for per-tool filling specs and the full tool list.

