# WenYinOS ZenTaoPMS 9.8.3_1

English | [简体中文](README.zh-CN.md)

## Overview
WenYinOS ZenTaoPMS is a PHP + MySQL project management system based on the classic ZenTao monolith architecture. It includes product, project, QA, document, and organization management in one application.

## Key Capabilities
- Product management: stories, plans, releases
- Project management: tasks, teams, builds, sprint execution
- QA management: bugs, test cases, test tasks
- Document and knowledge management
- User, organization, and daily collaboration support

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
5. **Security — remove the installers from the web root:**
   ```bash
   rm www/install.php www/upgrade.php
   ```
6. Optional post-install check: visit `admin → checkdb` ("数据库" consistency check) — all tables should report OK.

> Notes
> - The wizard writes `config/my.php`; keep this file local (it is gitignored) and never commit real credentials.
> - To enable the SSO connector and the MCP module's extension hooks, add `$config->framework->extensionLevel = 1;` to `config/my.php` (see the sections above).
> - If the site is served through a web server, configure the pseudo-static rewrite below before using clean URLs.

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
- `db/`: SQL schema/bootstrap resources
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

## Security and Operations
- Keep `tmp/` and runtime data out of version control.
- Review file permissions for `config/`, `tmp/`, and upload/runtime directories in deployment.
- Run DB consistency checks after database-related changes.

## License
Z PUBLIC LICENSE 1.2 (see `LICENSE`).
