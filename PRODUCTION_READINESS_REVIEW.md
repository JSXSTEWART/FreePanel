# FreePanel Production Readiness Review

Scope: security, system operations, configuration, reliability, testing, and
completeness gaps blocking a production deployment of FreePanel as a WHM/cPanel
alternative. Findings cite specific file:line locations verified against the
source on branch `claude/review-free-panel-production-2HNJJ`.

Assumption: the panel runs with elevated privileges (`sudo` / root) on servers
that host real customer data.

---

## CRITICAL — Ship-blockers

### C1. Command injection via unescaped `queueId` in mail queue management
`app/Http/Controllers/Api/V1/Admin/MailQueueController.php:40,56,83,116-122,133`

`$queueId` comes directly from the URL route parameter (`destroy(string $queueId)`,
`show(string $queueId)`, `hold(...)`) and is interpolated into a shell string:

```php
Process::run("sudo /usr/sbin/postsuper -d {$queueId} 2>&1");
```

An admin attacker (or anyone who compromises an admin session) can submit
`ABC123;rm -rf /` and execute arbitrary commands as root. The admin role gate
is a defense-in-depth layer, not a sanitizer. Fix: validate queue IDs against
`/^[A-F0-9]+$/i` and use `Process::run([...])` with array args, or
`escapeshellarg()`.

### C2. Command injection in `SystemdManager`
`app/Services/System/SystemdManager.php:14,26,38,50,60,...`

Every method interpolates `$service` into a shell string:

```php
Process::run("sudo /usr/bin/systemctl start {$service}");
```

No validation is performed on `$service` anywhere in this class. Callers in
`ServiceController` pass a route parameter through to these methods. A single
unvalidated call path = root RCE. Fix: enforce an allowlist of managed services
or validate `/^[a-z][a-z0-9@._-]*$/` and use array-form `Process::run`.

### C3. Weak password escaping in `UserManager::setPassword`
`app/Services/System/UserManager.php:229-238`

```php
$escapedPassword = str_replace(['\\', "'"], ['\\\\', "\\'"], $password);
Process::run("echo '{$username}:{$escapedPassword}' | chpasswd");
```

The manual replacement misses newlines, `$(...)`, backticks, and unicode
lookalikes. A password like `x'$(id>/tmp/p)'` can break out. Additionally the
password appears in the process list (`ps auxww`) while the command runs. Fix:
pass credentials on stdin (`$proc->input("{$username}:{$password}\n")`) using
array-form `Process::run`, never via a shell pipeline.

### C4. MySQL password leaks via command line
`app/Services/Database/MysqlService.php:189-218`

`mysqldump -p<password>` exposes the password to every user who can read
`/proc/<pid>/cmdline` for the brief window it runs, and it lands in shell
logs/crash dumps. Use `--defaults-extra-file=/tmp/XXX` (0600) or the
`MYSQL_PWD` env var, not CLI args.

### C5. `CronService` uses unescaped `$systemUser`
`app/Services/System/CronService.php:44,59`

```php
Process::run("sudo crontab -u {$systemUser} {$tempFile}");
```

Account usernames are regex-validated at creation (UserManager line 263), but
this class trusts them forever and never re-validates. Any future code path
that creates an Account with a non-validated username (import, migration,
reseller flow, API seam) yields root RCE. Defense-in-depth: escape at every
shell boundary.

### C6. Pure-FTPd command construction
`app/Services/Ftp/PureFtpdService.php:40`

`escapeshellarg($password)` is correct, but `$command` is interpolated and may
itself embed a username that is not guaranteed to be sanitized upstream. Same
class of bug as C5 — escape at the boundary, don't trust distant invariants.

### C7. Public `/setup/initialize` with no proven rate limit
`routes/api.php:56-60`

The setup endpoint initializes the first admin and is **public**. It relies on
`throttle:setup`, but no custom throttle is defined in
`app/Providers/RouteServiceProvider.php` (grep confirms no `RateLimiter::for('setup', …)`).
If the named limiter is missing, Laravel falls through with no limit applied.
If setup state can be triggered again (e.g., DB reset, partial migration,
table truncation via another bug), an attacker can seize admin. Fix: define
the limiter explicitly and gate by a one-time install token written to disk.

---

## HIGH

### H1. `TerminalController::execute` is a shell-execution API
`app/Http/Controllers/Api/V1/User/TerminalController.php:49-80`

This is a web terminal that runs arbitrary shell commands as the hosted user.
Even with per-account isolation, this is a large attack surface (local
privilege escalation via kernel bugs, sudo misconfig, SUID binaries). At
minimum: disable by default, require 2FA re-auth on session creation, and run
the command inside a pinned systemd-nspawn/container, not just as the system
user. Today it only filters with `validateCommand` — a denylist that cannot
cover `bash -c ...`, `php -r`, heredocs, env-var expansion, etc.

### H2. Cron command validator is a denylist
`app/Services/System/CronService.php:87-100`

Blocks specific binaries but not `bash -c`, `sh -c`, `python -c`, `php -r`,
`perl -e`. Customer cron is effectively arbitrary shell execution — that's
expected for cPanel parity, but it must be paired with per-user resource
limits (cgroups, nice/ionice, ulimit) which are not visible in the service.

### H3. Redis exposure in dev / default config
`docker-compose.yml` (dev stack) and `.env.example:60` (`REDIS_PASSWORD=null`)

Dev compose runs Redis without `--requirepass`. Staging/demo deployments that
copy the dev compose leak sessions, cache, and queue jobs. The prod compose
does set `--requirepass "${REDIS_PASSWORD}"`, but because the env default is
literally `null`, a misconfigured prod host silently starts an unauth Redis.
Fix: fail startup if `REDIS_PASSWORD` is empty/`null` in production.

### H4. HTTPS / HSTS not enforced
`.env.example:16` (`APP_URL=http://...`), `app/Http/Kernel.php`

No `TrustProxies` tightening, no HSTS middleware, no forced-HTTPS scheme. For
a panel that handles sudo credentials, DB passwords, 2FA tokens, and SSH keys,
this is not acceptable. Fix: middleware to force HTTPS in production, HSTS
header, document certbot setup in README.

### H5. `awk` interpolation of config values
`app/Services/System/UserManager.php:244`

```php
Process::run("getent passwd | awk -F: '\$3 >= {$this->minUid} && \$3 <= {$this->maxUid} {print \$3}' | sort -n | tail -1");
```

`minUid`/`maxUid` come from config. If the config ever becomes
admin-writable (UI setting, DB-backed settings table), this is an injection
sink. Cast to `(int)` at the boundary.

### H6. File manager path-traversal check is `str_starts_with`
`app/Http/Controllers/Api/V1/User/FileController.php:24-32`

Symlink escapes are not prevented. If the account home contains a symlink to
`/etc`, `resolvePath()` may return a path that passes the prefix check but
points outside the jail. Fix: `realpath()` both sides, reject symlinks whose
targets resolve outside the jail, and chroot where possible.

### H7. No static analysis or security scanning in CI
`.github/workflows/` (Pint + PHPUnit only)

`phpstan.neon` is committed but not executed. No dep-vuln check
(`composer audit`, `roave/security-advisories`), no SAST (Psalm taint analysis
would catch most of C1-C5 automatically). Add `composer audit` and phpstan
level 6+ with taint rules to CI — this single change would have caught most
CRITICAL findings above.

---

## MEDIUM

- **`APP_DEBUG=true` in test bootstrap** (`tests/TestCase.php:45`) — fine for
  tests, but several operators run test suites in staging; document the risk.
- **Backups written without enforced umask** (`MysqlService.php:205-225`) — if
  backups land on shared storage (S3 gateway, NFS), world-readable mode leaks
  every database dump. Enforce `chmod 0600` post-write.
- **Mail-log grep uses user regex** (`MailQueueController.php:274-280`) —
  `escapeshellarg($filter)` prevents shell escape, but a caller-supplied
  regex can ReDoS the mail log. Constrain to literal substring.
- **OAuth users with `password = null`** (`OAuthController.php:146`) — verify
  password-reset and 2FA-recovery flows don't assume a password exists.
- **No audit trail for destructive admin actions** that we could find for
  `postsuper -d ALL`, firewall changes, SSH key removal. `audit` middleware
  is applied to the v1 group, but its implementation is worth a dedicated
  review (not done here).
- **No explicit 2FA enforcement for admin role** — 2FA endpoints exist but
  there's no middleware gating admin APIs on 2FA being active.

---

## LOW

- `chpasswd` invocation logs command text if Laravel process logging is
  enabled in production — move to stdin (also fixes C3).
- `APP_KEY` rotation is not documented; rotating it invalidates every
  encrypted column (API tokens, DB creds stored encrypted) — document a
  rotation procedure before first production deploy.
- Docker image runs as… *(not verified in this pass — worth checking the
  `Dockerfile` `USER` directive; a panel that shells out to `sudo` must
  deliberately not run the PHP process as root)*.

---

## Corrections to earlier scan

- Admin API routes **do** enforce role middleware
  (`routes/api.php:371` applies `role:admin,reseller`, with narrower
  `role:admin` sub-groups for resellers, services, server, DNS, firewall,
  backup-schedules, SSH, mail-queue, modsecurity). The authorization layer
  is in place; the bugs are in the commands those authorized admins can
  trigger.

---

## Punch list — do these before production

1. Replace every `Process::run("... {$var} ...")` with array-form
   `Process::run([...])`. This one refactor eliminates C1, C2, C3, C5, C6
   and most of H5.
2. Move DB credentials out of `mysqldump`/`mysql` argv (C4).
3. Define the `setup` rate limiter and gate initialization on a disk token
   (C7).
4. Add `composer audit` + phpstan (with taint rules if available) to CI
   (H7).
5. Force HTTPS + HSTS in production middleware; fail startup when
   `REDIS_PASSWORD` is empty (H3, H4).
6. Re-review `TerminalController` and the cron validator under the
   assumption that customer input is hostile (H1, H2); decide whether to
   ship them v1 or feature-flag them off.
7. Add integration tests that attempt shell metacharacters in every
   user-reachable field that feeds a shell command; wire into CI.
