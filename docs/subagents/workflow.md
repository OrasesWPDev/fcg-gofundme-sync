# Phase Execution Workflow

**For Orchestrator:** Follow this workflow when executing implementation phases.

---

## Phase Execution Steps

### 1. Pre-Implementation
```bash
git checkout main && git pull origin main
git checkout -b feature/phase-X-description
```

### 2. Launch Dev Agents (Parallel)

**Spawn multiple dev agents in a SINGLE message** when tasks are independent:

```
Dev Agent 1: "Read docs/subagents/project-context.md and docs/subagents/dev-agent.md.
Implement step X.1 from docs/phase-X-implementation-plan.md"

Dev Agent 2: "Read docs/subagents/project-context.md and docs/subagents/dev-agent.md.
Implement step X.2 from docs/phase-X-implementation-plan.md"
```

**Parallelization rules:**
- ✅ Parallel: Steps modifying different files
- ✅ Parallel: Independent methods in same file
- ❌ Sequential: Step B depends on Step A's code
- ❌ Sequential: Steps modifying same method

### 3. Testing Agent

After dev agents complete:

```
Testing Agent: "Read docs/subagents/testing-agent.md.
Review the changes made in step X.1 and X.2.
Run PHP lint and code review checklist."
```

### 4. Update Plugin Version

Bump version in `fcg-gofundme-sync.php`:
- Header comment: `* Version: X.Y.Z`
- Constant: `define('FCG_GFM_SYNC_VERSION', 'X.Y.Z');`

### 5. Git Commit

```bash
git add -A
git status  # Verify changes
git commit -m "$(cat <<'EOF'
Add Phase X: Description

- Step X.1: What was implemented
- Step X.2: What was implemented

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>
EOF
)"
```

### 6. Deploy to Staging

```bash
rsync -avz --delete \
  --exclude='.git' --exclude='.github' --exclude='node_modules' --exclude='.DS_Store' \
  /Users/chadmacbook/projects/fcg/ \
  frederickc2stg@frederickc2stg.ssh.wpengine.net:~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync/

ssh frederickc2stg@frederickc2stg.ssh.wpengine.net \
  "cd ~/sites/frederickc2stg && wp plugin deactivate fcg-gofundme-sync && wp plugin activate fcg-gofundme-sync"
```

### 7. Run Verification Tests

Execute tests from `docs/phase-X-implementation-plan.md` verification section.

### 8. Update Documentation (**CRITICAL**)

Update `docs/phase-X-implementation-plan.md`:

**A. Execution Tracking Table:**
```markdown
| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| X.1 | Dev Agent | ✅ COMPLETE | Brief description |
| X.2 | Dev Agent | ✅ COMPLETE | Brief description |
| Code Review | Testing Agent | ✅ COMPLETE | PHP syntax + logic passed |
| Commit | Git Agent | ✅ COMPLETE | |
| Deploy | Main Agent | ✅ COMPLETE | Deployed to staging |
| Tests | Main Agent | ✅ COMPLETE | All tests passed |
```

**B. Commit Information:**
```markdown
**Commit SHA:** `abc1234`
**Commit Message:** Add Phase X: Description
```

**C. Test Results Table:**
```markdown
## Test Results

| Test | Result | Notes |
|------|--------|-------|
| X.8.1 | ✅ PASS | Description of what was verified |
| X.8.2 | ✅ PASS | Description of what was verified |
```

### 9. Pause for User Approval

**STOP and wait** before pushing to remote:
- Inform user: "Phase X complete on staging. Ready for review."
- Wait for explicit approval to push/merge

### 10. Push and Merge (After Approval)

```bash
git push -u origin feature/phase-X-description
git checkout main
git merge feature/phase-X-description --no-edit
git push origin main
```

---

## Quick Reference: Spawning Agents

**Lean prompt template:**
```
Read docs/subagents/project-context.md and docs/subagents/[agent-type].md.
Then [specific task] from docs/phase-X-implementation-plan.md step Y.Z.
```

**Agent types:**
- `dev-agent.md` - For implementing code
- `testing-agent.md` - For code review
- `deploy-agent.md` - For deployment

---

## Documentation Update Checklist

After each phase completion, ensure:

- [ ] Execution tracking table updated with ✅ COMPLETE
- [ ] Commit SHA added to plan document
- [ ] Test results table added
- [ ] `docs/phase-1-validation-results.md` "Next Steps" updated (if applicable)

---

## Common Mistakes to Avoid

1. **Forgetting to update docs** - Always update the phase plan after completion
2. **Sequential when parallel is possible** - Use parallel agents for independent tasks
3. **Pushing before approval** - Always pause for user approval after staging tests
4. **Missing version bump** - Update both header and constant
5. **Not documenting commit SHA** - Record the commit hash in the plan
