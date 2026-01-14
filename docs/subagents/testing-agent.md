# Testing Agent Instructions

**For Testing Agents:** Read this file before reviewing code changes.

---

## Your Task

1. Run PHP syntax checks on modified files
2. Review code against the checklist below
3. Verify version number if specified
4. Report results in a structured format

---

## PHP Syntax Check

Run on all modified PHP files:

```bash
php -l /path/to/file.php
```

**Expected output:** `No syntax errors detected in file.php`

**For multiple files:**
```bash
for f in includes/*.php fcg-gofundme-sync.php; do php -l "$f"; done
```

---

## Code Review Checklist

### 1. PHP Standards
- [ ] PHP 7.4+ syntax (typed properties, return types)
- [ ] Proper indentation (4 spaces)
- [ ] No debugging code left in (var_dump, print_r, die)

### 2. WordPress Standards
- [ ] Proper escaping for output (esc_html, esc_attr)
- [ ] Nonces for form submissions (if applicable)
- [ ] Capability checks for admin functions
- [ ] Proper hook registration

### 3. Project Patterns
- [ ] Class constants used for meta/option keys
- [ ] Logging uses the `log()` method pattern
- [ ] API results checked with `$result['success']`
- [ ] Sync loop prevention respected (`is_syncing_inbound()`)

### 4. Security
- [ ] No SQL injection (use `$wpdb->prepare()` if raw queries)
- [ ] No direct `$_GET`/`$_POST` without sanitization
- [ ] No hardcoded credentials

### 5. Logic
- [ ] Code matches the implementation plan
- [ ] Edge cases handled (empty arrays, null values)
- [ ] Error conditions logged appropriately

---

## Version Verification

Check that plugin version was updated (if specified in plan):

```bash
grep "Version:" fcg-gofundme-sync.php
grep "FCG_GFM_SYNC_VERSION" fcg-gofundme-sync.php
```

Both should show the same version number.

---

## Reporting Format

```
## Code Review Results

### PHP Syntax
- `file1.php`: PASS
- `file2.php`: PASS

### Code Review
- PHP Standards: PASS
- WordPress Standards: PASS
- Project Patterns: PASS
- Security: PASS
- Logic: PASS

### Version Check
- Expected: X.Y.Z
- Found: X.Y.Z
- Status: PASS

### Summary
All checks passed. Ready for commit.
```

---

## Common Issues to Flag

1. **Missing return types** - PHP 7.4 requires explicit return types
2. **Unescaped output** - All user-facing output needs escaping
3. **Direct database queries** - Should use WordPress functions
4. **Hardcoded strings** - Meta keys should be constants
5. **Missing error handling** - API calls need success checks
