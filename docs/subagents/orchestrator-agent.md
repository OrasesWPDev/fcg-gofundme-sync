# Orchestrator Agent Instructions

**For Orchestrator Agents:** You manage the entire phase execution lifecycle.

**Your job:** Execute a complete implementation phase autonomously, spawning subagents as needed, then report results to the main agent.

---

## Input Required

When spawned, you will receive:
- Phase number (e.g., "5")
- Target version (e.g., "1.4.0")

---

## Phase Execution Workflow

Execute these steps in order:

### Step 1: Read Phase Plan

```
Read docs/phase-{N}-implementation-plan.md
```

Analyze:
- What steps need to be implemented
- Which files are modified
- What the verification tests are
- Dependencies between steps

### Step 2: Git Setup

```bash
git checkout main && git pull origin main
git checkout -b feature/phase-{N}-description
```

### Step 3: Analyze Parallelization

Group implementation steps by parallelization potential:

**Can run in parallel:**
- Steps modifying different files
- Independent methods in same file

**Must run sequentially:**
- Step B calls/uses Step A's code
- Steps modifying the same method

### Step 4: Spawn Dev Agents

Launch dev agents using the Task tool. **Spawn parallel agents in a SINGLE message.**

**Lean prompt template:**
```
Read docs/subagents/project-context.md and docs/subagents/dev-agent.md.
Then implement step {N.X} from docs/phase-{N}-implementation-plan.md.
```

Wait for all dev agents to complete before proceeding.

### Step 5: Spawn Testing Agent

```
Read docs/subagents/testing-agent.md.
Review changes made to [list files modified].
Run PHP lint and code review checklist.
Expected version: {target_version}
```

If testing agent finds issues, fix them before proceeding.

### Step 6: Update Plugin Version

Edit `fcg-gofundme-sync.php`:
- Header: `* Version: {target_version}`
- Constant: `define('FCG_GFM_SYNC_VERSION', '{target_version}');`

### Step 7: Git Commit

```bash
git add -A
git commit -m "$(cat <<'EOF'
Add Phase {N}: {Description}

- Step {N}.1: {What was implemented}
- Step {N}.2: {What was implemented}
...

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>
EOF
)"
```

Record the commit SHA.

### Step 8: Deploy to Staging

```bash
rsync -avz --delete --delete-excluded \
  --exclude='.git' --exclude='.github' --exclude='.claude' --exclude='.idea' \
  --exclude='.gitignore' --exclude='node_modules' --exclude='.DS_Store' \
  --exclude='CLAUDE.md' --exclude='readme.txt' --exclude='docs/' \
  /Users/chadmacbook/projects/fcg/ \
  frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync/

ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp plugin deactivate fcg-gofundme-sync && wp plugin activate fcg-gofundme-sync"
```

### Step 9: Run Verification Tests

Execute all tests from the phase plan's "Verification Tests" section.
Record results for each test.

### Step 10: Update Phase Documentation

Edit `docs/phase-{N}-implementation-plan.md`:

**A. Update Execution Tracking Table:**
Change all PENDING to ✅ COMPLETE with notes.

**B. Add Commit SHA:**
```markdown
**Commit SHA:** `{sha}`
**Commit Message:** Add Phase {N}: {Description}
```

**C. Add Test Results Table:**
```markdown
## Test Results

| Test | Result | Notes |
|------|--------|-------|
| {N}.X.1 | ✅ PASS | {Description} |
```

### Step 11: Update Project Context

Edit `docs/subagents/project-context.md`:
- Update phase status table to show this phase as complete

### Step 12: Commit Documentation

```bash
git add -A
git commit -m "$(cat <<'EOF'
Update Phase {N} documentation with completion status

- Execution tracking table updated with ✅ COMPLETE
- Commit SHA documented
- Test results table added
- Project context phase status updated

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>
EOF
)"
```

---

## Final Report Format

When complete, report to the main agent using this format:

```
## Phase {N} Orchestration Complete

### Summary
- **Branch:** feature/phase-{N}-description
- **Version:** {target_version}
- **Implementation Commit:** `{sha1}`
- **Documentation Commit:** `{sha2}`

### Steps Completed
| Step | Status | Agent | Notes |
|------|--------|-------|-------|
| {N}.1 | ✅ | Dev Agent 1 | {brief description} |
| {N}.2 | ✅ | Dev Agent 2 | {brief description} |
| Code Review | ✅ | Testing Agent | All checks passed |
| Deploy | ✅ | Orchestrator | Deployed to staging |
| Tests | ✅ | Orchestrator | All {X} tests passed |

### Test Results
| Test | Result |
|------|--------|
| {test name} | ✅ PASS |

### Files Modified
- {file1}
- {file2}

### Ready for User Approval
Phase {N} is deployed to staging and tested.
Awaiting approval to push to GitHub and merge to main.
```

---

## Important Notes

1. **Do NOT push to remote** - Only the main agent pushes after user approval
2. **Do NOT skip documentation** - Always update phase plan and project context
3. **Spawn agents in parallel** when possible to save time
4. **Record all commit SHAs** for the final report
5. **If any step fails**, stop and report the failure with details

---

## Error Handling

If any step fails:
1. Document what failed and why
2. Attempt to fix if it's a minor issue
3. If unfixable, stop and report:
   - What step failed
   - Error message/output
   - What was attempted
   - Suggested resolution
