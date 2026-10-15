# WenYinOS ZenTaoPMS 9.9.0

English | [简体中文](README.zh-CN.md)

> **Unofficial build** — this is a community fork maintained by the [WenYin Open Source Community](https://github.com/wenyinos/ZenTaoPMS). Version `9.9.0` does not correspond to any official ZenTao release: it means ZenTao **9.8.3** core with the **10.1** interface ported on top (see below).

## Overview
WenYinOS ZenTaoPMS is a PHP + MySQL project management system based on the classic ZenTao monolith architecture. It includes product, project, QA, document, and organization management in one application.

Two upstream generations are combined here:

- **Core & business logic**: ZenTao **9.8.3** — unchanged, feature-frozen. All modules keep their original behaviour.
- **Interface layer**: ZenTao **10.1** — application skeleton (`module/common/view/` header/footer), page templates, skin base (ZUI 1.8.1) and the WenYin purple theme, plus a compatibility layer (`www/theme/default/compat.css`, `www/js/compat.js`) that keeps legacy pages rendering correctly.

Themes registered in this build: **WenYin** (purple, default), **LightBlue**, **Blackberry**.

## Key Capabilities
- Product management: stories, plans, releases
- Project management: tasks, teams, builds, sprint execution
- QA management: bugs, test cases, test tasks
- Document and knowledge management
- User, organization, and daily collaboration support

## What's New (9.8.3_1 → 9.9.0)

### Interface (ported from ZenTao 10.1)
- **Entirely new UI**: the 10.1 application skeleton, 615 page templates, 126 language files and the skin base, ported wholesale.
- **Front-end framework upgraded**: ZUI 1.7 → 1.8.1, with a compatibility layer (`www/theme/default/compat.css` + `www/js/compat.js`) keeping legacy pages render correctly.
- **Three themes**: WenYin purple (default) / Light Blue / Blackberry.
- **New dashboard layout**: the my/product/project/qa dashboards silently migrate to the 10.1 layout (two columns, statistic tiles, progress rings).
- **Full mobile adaptation**: responsive across all three themes; single-column stacking and horizontally scrollable nav on narrow screens.

### Features (newly ported onto the 9.8.3 core)
- **Activity stream**: date-grouped dynamics (my/company/product/project/user) with bidirectional paging (older/newer) and highlighted major actions.
- **Document favorites**: star/unstar documents and libraries, with a "My Favorites" browse entry and homepage statistics.
- **Parent task date roll-up**: child task date changes are rolled up to the parent task (earliest start / latest deadline).
- **Project overview statistics**: linked story/task/bug counts plus burn-down chart.
- **Batch close for todos**: select and close finished todos in batch.
- **Test report charts**: case result/type/module/runner distribution charts.
- **Project tree view**: node tree rendering with a story/task detail side panel.

### Platform
- **Upgrade flow (community single channel)**: only 9.8.3_1 and later community builds are accepted; official ZenTao databases are explicitly refused to prevent data corruption; a CLI upgrade channel is available.
- **Automated releases**: GitHub Actions builds the full delivery package (full lint + triple version check + content assertions + SHA256 sums).

## Unified Authentication (wenyinos SSO)

This build integrates with the **WenYin Open Source Community unified authentication center**: the ZenTao login page and logout are redirected to the central auth service, with automatic sign-in when you are already logged in on <https://wenyinos.com/auth/> — one account covers both the community forum and ZenTao. Password/profile changes made in ZenTao are synchronized back to the center.

- Connector: [`module/user/ext/`](./module/user/ext/) — configuration and the **on/off switch** (`'enabled' => true/false` in one config file, which restores the native login instantly) are documented in its [README](./module/user/ext/README.md).
- Requires `$config->framework->extensionLevel = 1;` in `config/my.php`.

## MCP Integration (zentao-mcp)

This build ships an MCP (Model Context Protocol) service that lets AI agents maintain ZenTao project management directly from git commits — linking commits to tasks/stories/bugs, driving task status flow and logging effort.

- Client: [`zentao-mcp-py/`](./zentao-mcp-py/) — a single-file Python client (standard library only; requires just `python3` and `git`). Works with any stdio MCP host (Qoder, Claude Desktop, Cursor, ...).
- Server side: [`module/mcp/`](./module/mcp/) — self-contained signed endpoints (double MD5 + timestamp freshness ±300s, no user session). Two-tier keys: per-account personal keys are generated in-app at **My → MCP Key** ([`module/my/ext/`](./module/my/ext/), stored in `zt_config`), so write actions cannot impersonate another account; the team shared `MCP_SECRET` covers reads and not-yet-keyed accounts (transition mode). Each client keeps its own account and git-author mapping for attribution; per-repo sync state and commit-log assembly live server-side. Optional `MCP_STRICT` / `MCP_READONLY` / `MCP_IPS` hardening.
- Setup and the full tool list (63 tools): see [`zentao-mcp-py/README.md`](./zentao-mcp-py/README.md), [`zentao-mcp-py/AGENT-GUIDE.md`](./zentao-mcp-py/AGENT-GUIDE.md) and [`AGENTS.md`](./AGENTS.md).

## Installation (via official install.php wizard)

No SQL import is needed — the built-in wizard creates the database, tables, company and admin account. Verified end-to-end on a clean deployment (54/54 tables reported OK by `admin → checkdb` afterwards).

### Prerequisites
- PHP ≥ 8.4 (enforced by the installer; tested on 8.5) with extensions: `pdo`, `pdo_mysql`, `json`, `openssl`, `mbstring`, `zlib`, `curl`, `filter`, `iconv`
- MySQL 5.x (utf8/utf8mb4; tested on 5.7); a MySQL user able to create databases (or pre-create an empty database)
- A web server: Nginx + php-fpm, Apache, or PHP's built-in server for a quick trial

### Steps
1. Point the web server's document root at the `www/` directory. For a quick local trial:
   ```bash
   php -S 127.0.0.1:8080 -t www
   ```
2. Make sure the following are writable by the PHP process: `config/`, `tmp/`, `www/data/`
   ```bash
   chmod -R 777 tmp www/data config    # tighten to the php-fpm user in production
   ```
3. Open `http://<your-host>/install.php` in a browser and follow the 5-step wizard:
   1. **Welcome** — click "开始安装" (Start)
   2. **License** — accept ZPL 1.2 and continue
   3. **Environment check** — all items should pass (PHP version / PDO / extensions / writable `tmp` and `www/data`)
   4. **Database config** — host, port, user, password, database name, table prefix. The wizard creates the database and all tables automatically; leave "清空现有数据" unchecked unless reinstalling over existing data
   5. **Save config & admin account** — the wizard writes `config/my.php` (verify the on-screen content is saved to that path), then fill in company name, admin account and password (optionally import demo data)
4. After "安装成功" (Installation successful), log in with the admin account you just created.
5. **Security — close the installer entry points.** Either delete the files:
   ```bash
   rm www/install.php www/upgrade.php
   ```
   …or keep them and block them at the web server — a hardened config 404s all three residue entries, case- and path-info-insensitive:
   ```nginx
   location ~* /(install|upgrade|checktable)\.php(/|$) { return 404; }
   ```
   With the block in place, an install or upgrade needs **this one line temporarily commented out** (or narrowed to `allow <your-ip>; deny all;`), then restored right after with an `nginx -s reload`. Never lift the `api`/`editor` blocks. The Upgrade section has the exact web procedure and a CLI path that needs no web exposure at all.
6. Optional post-install check: visit `admin → checkdb` ("数据库" consistency check) — all tables should report OK.

> Notes
> - The wizard writes `config/my.php`; keep this file local (it is gitignored) and never commit real credentials.
> - To enable the SSO connector and the MCP module's extension hooks, add `$config->framework->extensionLevel = 1;` to `config/my.php` (see the sections above).
> - If the site is served through a web server, configure the pseudo-static rewrite below before using clean URLs.

## Upgrade

This build has its **own version series**: it ships the ZenTao 9.8.3 business core with the 10.1 interface, so it is compatible with neither upstream 9.8.3 nor upstream 10.x data layouts. The upgrade script is therefore deliberately narrow:

- **Supported sources:** `9.8.3_1` (the first WenYinOS build) and later WenYinOS releases (`9.9.x` onward).
- **Upgrading from an official ZenTao database is refused** — `upgrade.php` shows a "无法升级" (cannot upgrade) page naming the detected version instead of proceeding.
- The one-click flow is `backup warning → 开始升级 (Start) → 升级成功 (done)`; the historical `db/update*.sql` chain and per-version selectors are gone. New releases that need schema changes add one branch in `module/upgrade/model.php::execute()`.

Prebuilt full packages (complete source + the `zentao-mcp-py/` MCP client, with `SHA256SUMS`) are attached to every release on the [Releases page](https://github.com/wenyinos/ZenTaoPMS/releases). Extract over the code root to upgrade in place — `config/my.php`, attachments and caches are left untouched; as root extraction also resets the ownership of `config/`, `tmp/` and `www/data/`, run `chown -R www:www config tmp www/data` (adjust the user) afterwards.

Steps for a WenYinOS → WenYinOS upgrade:

1. **Back up the database** first (the wizard repeats this warning).
2. Deploy the new code over the old tree.
3. Create the safe-file gate: `touch www/ok.txt` (or delete an `ok.txt` older than 1 hour — the flow refuses to run without a fresh one).
4. Run the upgrade — either the web wizard or the CLI:
   - **Web:** if the web server blocks `upgrade.php` (the recommended production state — see Installation step 5), first **temporarily comment out that one 404 rule** (or narrow it to `allow <your-ip>; deny all;`) and reload nginx. Open `http://<your-host>/upgrade.php`, follow the wizard (the current and target versions are shown before you confirm), then **restore the rule and reload again**. Keep the `api`/`editor` blocks enabled throughout.
   - **CLI (no web exposure needed):** with the same PHP binary the site uses, run two commands — this path is unaffected by the web-server block and skips the safe-file gate:
     ```bash
     php bin/ztcli 'http://<your-host>/upgrade-execute.html'
     php bin/ztcli 'http://<your-host>/upgrade-afterExec.html'
     ```
     The first performs the upgrade work, the second stamps the new version into the database.
5. Nothing to restore for the CLI path; for the web path, re-enable the nginx rule (or re-remove the installer files) immediately after finishing.

## CLI Helpers (optional)
```bash
bash bin/init.sh /usr/bin/php http://127.0.0.1:8080    # generate bin/backup.sh, bin/checkdb.sh, ...
php bin/ztcli 'http://127.0.0.1:8080/index.php?m=admin&f=checkdb'
```

## Repository Structure
- `module/`: business modules (`bug`, `story`, `task`, `project`, etc.)
- `framework/`: core runtime, base classes, routing
- `www/`: web entry points and static assets
- `config/`: runtime configuration
- `db/`: SQL schema/bootstrap resources (fresh install only — `zentao.sql`, `demo.sql`)
- `extension/`: extension and customization entry points
- `tmp/`: runtime cache/log/temp data

## Configuration
- Main defaults are in `config/config.php`.
- Put environment-specific overrides in `config/my.php`.
- Do not commit secrets or local-only configuration.

## Nginx Rewrite (Pseudo-static)
Add the following rules in your Nginx `location /` block:

```nginx
if (!-d $request_filename){
set $rule_0 1$rule_0;
}
if (!-f $request_filename){
set $rule_0 2$rule_0;
}
if ($rule_0 = "21"){
rewrite /(.*)$ /index.php/$1 last;
}
```

## PHP 8 Upgrade Notes
- Runtime target supports PHP 8.
- Legacy string offset syntax (`$var{0}`) has been migrated to bracket syntax (`$var[0]`).
- Legacy autoload handling has been aligned with `spl_autoload_register`.
- Dynamic invocations using `call_user_func_array()` were adjusted to avoid PHP 8 named-parameter incompatibility.
- Dynamic nested assignment behavior has been updated for PHP 8 strict object handling.
- After upgrading PHP, clear opcode/cache layers (for example OPcache) before regression checks.

## Development Notes
- This repository does not depend on a Node/Composer build pipeline in its default flow.
- Prefer incremental changes in `extension/` for custom behavior.
- Validate affected flows via UI plus `ztcli` checks.
- **Releases** are automated: pushing a `v9.*` tag (e.g. `v9.9.0`) makes `.github/workflows/release.yml` lint every PHP file, verify the tag equals `config/config.php`'s `$config->version` and `VERSION`, then attach the full source package (top-level layout, extract-over-deploy ready) and the standalone MCP client zip to the GitHub Release. Keep those three version markers in sync before tagging.

## Security and Operations
- Keep `tmp/` and runtime data out of version control.
- Review file permissions for `config/`, `tmp/`, and upload/runtime directories in deployment.
- Keep the install/upgrade residue (`install.php`, `upgrade.php`, `checktable.php`) and the `api`/`editor` debug entry points blocked at runtime; lift only the residue rule for an install/upgrade and restore it immediately (see Installation step 5).
- Run DB consistency checks after database-related changes.

## License
Z PUBLIC LICENSE 1.2 (see `LICENSE`).
