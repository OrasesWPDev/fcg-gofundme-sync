# Deploy Agent Instructions

**For Deploy Agents:** Read this file before deploying to staging/production.

---

## Your Task

1. Deploy plugin files to the target environment
2. Reactivate the plugin
3. Verify deployment success
4. Report results

---

## Environments

### Staging (Primary Development)
```
SSH: frederickc2stg@frederickc2stg.ssh.wpengine.net
Path: ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync/
```

### Production (Use with caution)
```
SSH: frederickcount@frederickcount.ssh.wpengine.net
Path: ~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/
```

---

## Deploy Command (rsync)

**To Staging:**
```bash
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  /Users/chadmacbook/projects/fcg/ \
  frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync/
```

**To Production:**
```bash
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  /Users/chadmacbook/projects/fcg/ \
  frederickcount@frederickcount.ssh.wpengine.net:~/sites/frederickcount/wp-content/plugins/fcg-gofundme-sync/
```

---

## Plugin Reactivation

After deploy, reactivate the plugin to ensure hooks are registered:

**Staging:**
```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp plugin deactivate fcg-gofundme-sync && wp plugin activate fcg-gofundme-sync"
```

**Production:**
```bash
ssh frederickcount@frederickcount.ssh.wpengine.net \
  "cd ~/sites/frederickcount && wp plugin deactivate fcg-gofundme-sync && wp plugin activate fcg-gofundme-sync"
```

---

## Verification Commands

**Check plugin version:**
```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=table"
```

**Check cron jobs:**
```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp cron event list | grep fcg"
```

**Test WP-CLI commands:**
```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp fcg-sync pull --dry-run"
```

---

## Reporting Format

```
## Deployment Results

### Environment
- Target: Staging (frederickc2stg)

### rsync
- Files synced: X files
- Status: SUCCESS

### Plugin Reactivation
- Deactivate: SUCCESS
- Activate: SUCCESS

### Verification
- Plugin version: X.Y.Z
- Cron registered: YES
- WP-CLI test: PASS

### Summary
Deployment successful. Ready for testing.
```

---

## Troubleshooting

**SSH connection fails:**
- Check SSH key is loaded: `ssh-add -l`
- Verify hostname: `ping frederickc2stg.ssh.wpengine.net`

**Plugin activation fails:**
- Check PHP syntax errors in error log
- Verify required credentials are set

**Cron not registered:**
- Deactivate and reactivate plugin
- Check `wp cron event list` output
