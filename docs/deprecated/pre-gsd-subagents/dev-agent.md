# Dev Agent Instructions

**For Dev Agents:** Read this file before implementing code changes.

**Also read:** `docs/subagents/project-context.md` for project overview.

---

## Your Task

1. Read the implementation plan: `docs/phase-X-implementation-plan.md`
2. Implement the specific step(s) assigned to you
3. Follow the patterns and standards below
4. Report what you implemented and any issues encountered

---

## PHP Standards

**Version:** PHP 7.4+ (use typed properties, return types, null coalescing)

**Formatting:**
- 4 spaces indentation (no tabs)
- Opening brace on same line for functions/classes
- One blank line between methods

**Example:**
```php
private function example_method(int $post_id, array $data): ?string {
    if (empty($data)) {
        return null;
    }

    return $data['value'] ?? 'default';
}
```

---

## WordPress Conventions

**Hooks:**
```php
add_action('hook_name', [$this, 'method_name'], 10, 2);
add_filter('filter_name', [$this, 'filter_method']);
```

**Post Meta:**
```php
$value = get_post_meta($post_id, '_meta_key', true);
update_post_meta($post_id, '_meta_key', $value);
delete_post_meta($post_id, '_meta_key');
```

**Options:**
```php
$value = get_option('option_name', $default);
update_option('option_name', $value, false); // false = no autoload
delete_option('option_name');
```

**Transients:**
```php
$value = get_transient('transient_name');
set_transient('transient_name', $value, $expiration_seconds);
delete_transient('transient_name');
```

---

## Project Patterns

**Class Constants:** Use for option/meta keys
```php
private const META_KEY_DESIGNATION_ID = '_gofundme_designation_id';
private const OPTION_LAST_POLL = 'fcg_gfm_last_poll';
```

**Logging:**
```php
private function log(string $message): void {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[FCG GoFundMe Sync] ' . $message);
    }
}
```

**API Result Format:**
```php
// API client methods return:
['success' => true, 'data' => [...]]
// or
['success' => false, 'error' => 'Error message']
```

**Error Handling:**
```php
$result = $this->api->some_method();
if (!$result['success']) {
    $this->log("Operation failed: {$result['error']}");
    return;
}
```

---

## File Modification Guidelines

1. **Read before editing** - Always read the file first to understand context
2. **Minimal changes** - Only modify what's necessary for the task
3. **Preserve structure** - Follow existing code organization
4. **Add constants** - Use class constants for new meta/option keys
5. **Update version** - Bump version in main plugin file if specified in plan

---

## Implementing from Phase Plans

Each `docs/phase-X-implementation-plan.md` contains:
- Step numbers (e.g., 4.1, 4.2)
- Code snippets to implement
- File locations
- Expected behavior

**Your job:**
1. Find your assigned step(s)
2. Locate the target file
3. Implement the code as specified
4. Verify it integrates with existing code

---

## Reporting

When complete, report:
- Files modified
- Methods added/changed
- Any deviations from the plan
- Any issues or questions
