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

## MCP Integration (zentao-mcp)

`zentao-mcp/` provides an MCP server that lets AI agents maintain ZenTao project management from git commits (task/story/bug linking, task status flow, effort logging).

- ZenTao side: `module/mcp/` endpoints + `module/common/ext/model/mcp.php` (open-method extension); client side: `zentao-mcp/` (25 tools covering task/story/bug lifecycle, queries, effort reports and commit sync).
- Auth: HMAC-signed requests with timestamp freshness (±300s), no user session involved. Secrets live in `module/mcp/.env` and `zentao-mcp/.env` (both gitignored). Optional `MCP_READONLY` / `MCP_IPS` hardening; write calls are audited to `tmp/mcp/access-YYYYMMDD.log`.
- Agent workflow: after development work, run `zentao_get_git_commits` → update tasks with `zentao_start_task` / `zentao_record_effort` / `zentao_finish_task` (pass each commit's `authorAccount` as `actor` in multi-user repos) → write links with `zentao_sync_commits`. Commit messages reference objects with `task #N`, `story #N`, `bug #N` markers.
- After editing files under `module/common/ext/`, clear `tmp/model/*.php` (extension merge cache) and reload php-fpm.
- Do not expose the ZenTao `api`/`editor` entry points (debug interfaces) in web server configs; the MCP endpoints are self-contained.
- See `zentao-mcp/README.md` for deployment, configuration and the full tool list.

