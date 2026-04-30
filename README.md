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

## Quick Start
### 1. Run a local web server
```bash
php -S 127.0.0.1:8080 -t www
```

### 2. Initialize CLI helper scripts (optional)
```bash
bash bin/init.sh /usr/bin/php http://127.0.0.1:8080
```

### 3. Run a module action from CLI (example)
```bash
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
