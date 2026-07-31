# Autonomous remediation policy

This policy grants narrow operational authority for unattended runs. It does
not grant general root authority. Use a dedicated operator identity and
command-level `sudoers` restrictions when available.

## Universal preconditions

Before every mutation:

1. Resolve the target from the approved inventory and confirm its environment.
2. Confirm the current report finding directly on that target.
3. Capture the relevant pre-action state and logs.
4. Check that no deployment, backup, migration, package operation, or another
   remediation is currently active.
5. Confirm that the action below exactly covers the service and condition.
6. Know the direct verification command and the recovery signal.

If any precondition fails, diagnose and report without mutation.

## Tier 1: permitted autonomously

### Start an installed required service

Permitted only for `apache2`, `cron`, and `fail2ban` when the service is
reported as required, is currently inactive or failed, and has not been
administratively masked.

- For Apache, require a successful `apachectl configtest`.
- For Fail2ban, require a successful configuration test and confirm its
  expected jails afterward.
- Capture recent service logs before starting.
- Use `systemctl start`, not `enable`; do not change boot policy.
- Stop after one attempt. Do not loop.

### Reload healthy Apache configuration

Permitted when Apache is active, `apachectl configtest` passes, and the finding
is specifically caused by Apache not having loaded already-installed
configuration or certificates.

- Use a graceful reload.
- Do not edit virtual hosts or certificate paths.
- Verify Apache state and affected HTTPS endpoints afterward.

### Clear a systemd failed marker

Permitted with `systemctl reset-failed <approved-service>` only after the
underlying approved service is healthy. This is bookkeeping after recovery,
not remediation by itself.

### Run the existing Nimbly scheduler orchestrator once

Permitted when the scheduler is stale or its previous invocation failed, no
scheduler process or lock holder is active, the registry is valid, and disk
space is not critical.

- Run the already-installed orchestrator as its configured service user.
- Do not edit schedules, registries, cron files, projects, or queue records.
- Stop after one attempt.
- Verify the scheduler log, project exit statuses, and a fresh host audit.

### Run existing certificate renewal

Permitted only when an installed certificate is within the report's critical
window, the certificate name and domains match the enabled site, the existing
renewal configuration validates with a dry run, and no DNS or webroot change
is required.

- Use the existing renewal mechanism without changing configuration.
- Do not request a different certificate or alter Apache.
- Verify the served certificate from outside the local process afterward.

## Never autonomous

- Editing or creating scripts, application files, framework files, environment
  files, systemd units, cron entries, virtual hosts, firewall rules, SSH
  configuration, DNS, users, groups, permissions, secrets, databases, or
  monitoring/report definitions.
- Git pulls, deployments, migrations, package installation or upgrades.
- Reboots, destructive commands, log or data deletion, filesystem repair,
  database repair, backup restoration, or certificate replacement requiring
  configuration changes.
- Restarting a healthy service merely to test whether a finding disappears.
- Retrying application jobs whose idempotency and business side effects are
  not explicitly documented.
- Acting on a host absent from approved inventory.

Escalate these with evidence, impact, exact proposed action, rollback, and
verification steps.
