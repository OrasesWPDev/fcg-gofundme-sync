# Coding Conventions

**Analysis Date:** 2026-01-22

## Naming Patterns

**Files:**
- Class files: `class-{name}.php` (kebab-case)
  - Examples: `class-api-client.php`, `class-sync-handler.php`, `class-sync-poller.php`, `class-admin-ui.php`

**Functions:**
- Plugin-level functions: `fcg_gfm_{verb}_{noun}()` (snake_case with FCG_GFM prefix)
  - Examples: `fcg_gfm_has_credential()`, `fcg_gfm_sync_init()`, `fcg_gfm_sync_activate()`
- Class methods: snake_case
  - Public: `on_save_fund()`, `poll()`, `cli_push()`, `render_sync_column()`
  - Private: `build_designation_data()`, `get_credential()`, `log_error()`

**Variables:**
- Local/instance variables: snake_case
  - Examples: `$post_id`, `$designation_id`, `$api`, `$client_id`, `$sync_error`
- Array keys: snake_case
  - Examples: `'success'`, `'error'`, `'post_title'`, `'last_sync'`

**Types:**
- Classes: PascalCase
  - Examples: `FCG_GFM_API_Client`, `FCG_GFM_Sync_Handler`, `FCG_GFM_Sync_Poller`, `FCG_GFM_Admin_UI`
- Constants: UPPER_SNAKE_CASE (class constants are private)
  - Examples: `API_BASE`, `TOKEN_URL`, `POST_TYPE`, `META_KEY_DESIGNATION_ID`
- Plugin-level constants: UPPER_SNAKE_CASE with FCG_GFM prefix
  - Examples: `FCG_GFM_SYNC_VERSION`, `FCG_GFM_SYNC_PATH`, `FCG_GFM_SYNC_URL`

## Code Style

**Formatting:**
- PSR-12 compliant PHP code
- 4-space indentation
- Opening braces on same line (K&R style)
- No newline at end of opening brace for control structures

**Type Hints:**
- All method parameters have type hints
  - Examples: `public function on_save_fund(int $post_id, WP_Post $post, bool $update): void`
  - Examples: `private function get_credential(string $name): string`
- Return types specified for all methods
  - Void methods: `: void`
  - Nullable returns: `?string`, `?int`
  - Array returns: `: array`

**Guard Clauses:**
- Early returns prevent nesting
  - `if (!$this->api->is_configured()) { return; }`
  - `if (!$post || $post->post_type !== self::POST_TYPE) { return; }`
  - `if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }`
- Reduces cognitive load and prevents deep nesting

**WordPress Coding Standards:**
- All plugin code wrapped in `if (!defined('ABSPATH')) { exit; }` guard
- Proper escaping when outputting data: `esc_html()`, `esc_attr()`, `esc_url()`
  - See: `echo '<span class="fcg-sync-status fcg-sync-error" title="' . esc_attr($sync_error) . '">Error</span>';`
- Nonce verification for AJAX: `check_ajax_referer('fcg_gfm_sync_now', 'nonce')`
- Capability checks: `current_user_can('manage_options')`, `current_user_can('edit_posts')`

## Import Organization

**Class instantiation pattern:**
- API client instantiated once in constructor
  - `$this->api = new FCG_GFM_API_Client();`
- Configured check before proceeding
  - `if (!$this->api->is_configured()) { return; }`

**WordPress Functions:**
- No explicit imports (WordPress is global)
- Functions called directly: `get_post()`, `update_post_meta()`, `add_action()`, etc.
- Plugin-specific constants used as namespaces: `FCG_GFM_SYNC_PATH`, `FCG_GFM_SYNC_URL`

## Error Handling

**Patterns:**
- Return arrays with success flag for API operations
  ```php
  return [
      'success' => false,
      'error'   => 'Failed to obtain access token',
  ];
  ```
  or on success:
  ```php
  return [
      'success' => true,
      'data'    => $body,
  ];
  ```

- Check success flag before processing response
  ```php
  if (!$result['success']) {
      return $result;
  }
  ```

- WP_Error handling for WordPress remote requests
  ```php
  if (is_wp_error($response)) {
      $this->log_error('Token request failed: ' . $response->get_error_message());
      return false;
  }
  ```

- HTTP status code checks
  ```php
  if ($code >= 400) {
      $error_message = $body['error'] ?? $body['message'] ?? "HTTP {$code}";
      $this->log_error("API {$method} {$endpoint} returned {$code}: {$error_message}");
      return ['success' => false, 'error' => $error_message, 'http_code' => $code];
  }
  ```

## Logging

**Framework:** PHP `error_log()` with plugin prefix

**Patterns:**
- Check `WP_DEBUG` before logging (info messages only)
  ```php
  if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[FCG GoFundMe Sync] ' . $message);
  }
  ```

- Error messages always logged regardless of WP_DEBUG
  ```php
  error_log('[FCG GoFundMe Sync] ERROR: ' . $message);
  ```

- Consistent prefix: `[FCG GoFundMe Sync]`

- Informative messages with IDs and context
  - `"Created designation {$designation_id} for post {$post_id}"`
  - `"Poll complete: %d processed, %d updated, %d skipped, %d orphaned, %d errors, %d retried"`

## Comments

**When to Comment:**
- Complex logic requiring explanation
- Intent is not obvious from code
- Non-standard patterns
- Business logic reasons for decisions

**JSDoc/PHPDoc:**
- All public methods documented
- All class properties documented
- All private methods documented when complex
- Format:
  ```php
  /**
   * Brief description.
   *
   * Longer description if needed.
   *
   * @param Type $name Description
   * @param Type $name Description
   * @return Type Description
   */
  ```

**Examples from codebase:**
```php
/**
 * Get OAuth2 access token
 *
 * @return string|false Access token or false on failure
 */
private function get_access_token()
```

```php
/**
 * Build designation data from WordPress post
 *
 * @param WP_Post $post Post object
 * @return array Designation data
 */
private function build_designation_data(WP_Post $post): array
```

## Function Design

**Size:** Most functions/methods 20-80 lines
- `get_credential()`: 11 lines
- `on_save_fund()`: 46 lines
- `poll()`: 63 lines
- `build_designation_data()`: 27 lines
- Exceptions: CLI commands and complex operations can be longer (e.g., `cli_push()`: 128 lines)

**Parameters:**
- Minimal parameters (1-3 typical)
- Use object parameters instead of multiple primitives
  - `WP_Post $post` instead of `$post_title, $post_status, $post_content`
- Optional parameters use typed arrays with known keys
  - `?array $data = null`

**Return Values:**
- Single responsibility: return consistent types
- API methods return `array` with `['success' => bool, 'error' => string|null, 'data' => mixed]`
- Queries return `?Type` (nullable for not found)
- Void for WordPress hooks/callbacks

## Module Design

**Exports:**
- Classes are primary exports
  - `FCG_GFM_API_Client`: OAuth/API wrapper
  - `FCG_GFM_Sync_Handler`: WordPress hooks
  - `FCG_GFM_Sync_Poller`: Polling and WP-CLI commands
  - `FCG_GFM_Admin_UI`: Admin interface

- Plugin-level functions for initialization
  - `fcg_gfm_sync_init()`: Main entry point
  - `fcg_gfm_sync_activate()`: Activation hook
  - `fcg_gfm_sync_deactivate()`: Deactivation hook

**Barrel Files:** Not used (single class per file)

**Instantiation:**
- Classes instantiated in plugin bootstrap via hooks
- Constructor registers WordPress hooks (Visitor pattern)
- Single instance pattern not enforced (new instances per call acceptable)

## Post Meta Keys

**Designated keys (private, prefixed with underscore):**
- `_gofundme_designation_id`: The GoFundMe Pro designation ID
- `_gofundme_campaign_id`: The GoFundMe Pro campaign ID
- `_gofundme_campaign_url`: The canonical URL to the campaign
- `_gofundme_last_sync`: Timestamp of last sync (MySQL format)
- `_gofundme_sync_error`: Error message if sync failed
- `_gofundme_sync_attempts`: Number of failed sync attempts
- `_gofundme_sync_last_attempt`: Timestamp of last failed sync attempt
- `_gofundme_sync_source`: Source of last sync (`'wordpress'` or `'gofundme'`)
- `_gofundme_poll_hash`: MD5 hash for change detection during polling
- `_fundraising_goal`: Optional custom field for fundraising goal

---

*Convention analysis: 2026-01-22*
