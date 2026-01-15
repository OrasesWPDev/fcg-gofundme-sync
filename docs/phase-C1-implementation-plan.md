# Phase C1: Campaign API Integration - Implementation Plan

**PRD Reference:** `/Users/chadmacbook/projects/fcg/docs/PRD-campaigns.md` (Phase C1)
**Goal:** Add campaign CRUD methods to API client + research campaign types
**Version:** 2.0.0
**Branch:** `feature/phase-C1-campaign-api`
**Depends On:** Phase C0 (designation sync fixed)

---

## Overview

Add campaign-related methods to the `FCG_GFM_API_Client` class, mirroring the existing designation methods. This phase focuses on API integration only - no sync logic yet.

---

## API Endpoints (from PRD research)

| Operation | Endpoint | Method |
|-----------|----------|--------|
| Create | `/organizations/{org_id}/campaigns` | POST |
| Get | `/campaigns/{id}` | GET |
| Update | `/campaigns/{id}` | PUT |
| List All | `/organizations/{org_id}/campaigns` | GET |
| Deactivate | `/campaigns/{id}/deactivate` | POST |

---

## Substeps Overview

| Step | Description | Files |
|------|-------------|-------|
| C1.1 | Add `create_campaign()` method | `class-api-client.php` |
| C1.2 | Add `update_campaign()` method | `class-api-client.php` |
| C1.3 | Add `get_campaign()` method | `class-api-client.php` |
| C1.4 | Add `get_all_campaigns()` method with pagination | `class-api-client.php` |
| C1.5 | Add `deactivate_campaign()` method | `class-api-client.php` |
| C1.6 | Add campaign post meta constants | `class-sync-handler.php` |
| C1.7 | Update plugin version to 2.0.0 | `fcg-gofundme-sync.php` |
| C1.8 | API research via test calls | WP-CLI |

---

## Step C1.1: Add `create_campaign()` Method

**File:** `includes/class-api-client.php`

**Add method after `create_designation()`:**

```php
/**
 * Create a campaign
 *
 * @param array $data Campaign data (name, goal, type, etc.)
 * @return array Response
 */
public function create_campaign(array $data): array {
    return $this->request('POST', "/organizations/{$this->org_id}/campaigns", $data);
}
```

---

## Step C1.2: Add `update_campaign()` Method

**File:** `includes/class-api-client.php`

**Add method:**

```php
/**
 * Update a campaign
 *
 * @param int|string $campaign_id Campaign ID
 * @param array $data Updated data
 * @return array Response
 */
public function update_campaign($campaign_id, array $data): array {
    return $this->request('PUT', "/campaigns/{$campaign_id}", $data);
}
```

---

## Step C1.3: Add `get_campaign()` Method

**File:** `includes/class-api-client.php`

**Add method:**

```php
/**
 * Get a campaign
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function get_campaign($campaign_id): array {
    return $this->request('GET', "/campaigns/{$campaign_id}");
}
```

---

## Step C1.4: Add `get_all_campaigns()` Method

**File:** `includes/class-api-client.php`

**Add method with pagination (matching `get_all_designations()` pattern):**

```php
/**
 * Get all campaigns for the organization with pagination.
 *
 * @param int $per_page Results per page (default 100, max 100)
 * @return array {success: bool, data: array|null, error: string|null, total: int}
 */
public function get_all_campaigns(int $per_page = 100): array {
    $all_campaigns = [];
    $page = 1;

    do {
        $endpoint = "/organizations/{$this->org_id}/campaigns?page={$page}&per_page={$per_page}";
        $result = $this->request('GET', $endpoint);

        if (!$result['success']) {
            return $result;
        }

        $data = $result['data'];
        $all_campaigns = array_merge($all_campaigns, $data['data'] ?? []);

        $has_more = $page < ($data['last_page'] ?? 1);
        $page++;

    } while ($has_more);

    return [
        'success' => true,
        'data' => $all_campaigns,
        'total' => count($all_campaigns),
    ];
}
```

---

## Step C1.5: Add `deactivate_campaign()` Method

**File:** `includes/class-api-client.php`

**Add method:**

```php
/**
 * Deactivate a campaign
 *
 * @param int|string $campaign_id Campaign ID
 * @return array Response
 */
public function deactivate_campaign($campaign_id): array {
    return $this->request('POST', "/campaigns/{$campaign_id}/deactivate", []);
}
```

---

## Step C1.6: Add Campaign Post Meta Constants

**File:** `includes/class-sync-handler.php`

**Add constants after existing meta key constants:**

```php
/**
 * Meta key for GoFundMe Pro Campaign ID
 */
private const META_CAMPAIGN_ID = '_gofundme_campaign_id';

/**
 * Meta key for GoFundMe Pro Campaign URL
 */
private const META_CAMPAIGN_URL = '_gofundme_campaign_url';
```

**Note:** These constants prepare for Phase C2 when campaign sync logic is added.

---

## Step C1.7: Update Plugin Version

**File:** `fcg-gofundme-sync.php`

**Update:**
1. Header comment: `* Version: 2.0.0`
2. Version constant: `define('FCG_GFM_SYNC_VERSION', '2.0.0');`

---

## Step C1.8: API Research via Test Calls

**Goal:** Discover campaign required fields and response structure

**Test Script (run on staging via WP-CLI):**

```bash
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net

cd ~/sites/frederickc2stg

# Test 1: List existing campaigns
wp eval '
$api = new FCG_GFM_API_Client();
$result = $api->get_all_campaigns();
echo "Total campaigns: " . ($result["total"] ?? 0) . "\n";
if (!empty($result["data"])) {
    echo "Sample campaign:\n";
    print_r($result["data"][0]);
}
'

# Test 2: Try creating a test campaign (minimal fields)
wp eval '
$api = new FCG_GFM_API_Client();
$result = $api->create_campaign([
    "name" => "Test Campaign " . time(),
    "type" => "crowdfunding",
    "goal" => 1000,
    "timezone_identifier" => "America/New_York"
]);
print_r($result);
'
```

**Expected Research Outputs:**
- List of required fields for campaign creation
- Campaign response structure (ID, URL, status fields)
- Any differences from designation API

---

## Verification Tests

| Test | Command/Action | Expected Result |
|------|----------------|-----------------|
| C1.T1 | PHP syntax check | `php -l` passes for all modified files |
| C1.T2 | List campaigns | `get_all_campaigns()` returns array |
| C1.T3 | Get single campaign | `get_campaign(ID)` returns campaign data |
| C1.T4 | Create campaign | `create_campaign()` returns new campaign with ID |
| C1.T5 | Plugin version | Shows 2.0.0 in plugins list |

### Test Commands

```bash
# T1: PHP Syntax
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg/wp-content/plugins/fcg-gofundme-sync && php -l includes/class-api-client.php && php -l includes/class-sync-handler.php && php -l fcg-gofundme-sync.php"

# T2: List campaigns
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp eval '\$api = new FCG_GFM_API_Client(); \$r = \$api->get_all_campaigns(); echo \"Success: \" . (\$r[\"success\"] ? \"yes\" : \"no\") . \", Total: \" . (\$r[\"total\"] ?? 0);'"

# T5: Plugin version
ssh frederickc2stg@frederickc2stg.ssh.wpengine.net "cd ~/sites/frederickc2stg && wp plugin list --name=fcg-gofundme-sync --format=csv"
```

---

## Files Modified Summary

| File | Action | Changes |
|------|--------|---------|
| `includes/class-api-client.php` | Modified | Add 5 campaign methods |
| `includes/class-sync-handler.php` | Modified | Add 2 campaign meta constants |
| `fcg-gofundme-sync.php` | Modified | Version bump 1.6.0 → 2.0.0 |

---

## Execution Tracking

| Step | Agent | Status | Notes |
|------|-------|--------|-------|
| C1.1 | dev-agent | COMPLETE | create_campaign() added |
| C1.2 | dev-agent | COMPLETE | update_campaign() added |
| C1.3 | dev-agent | COMPLETE | get_campaign() added |
| C1.4 | dev-agent | COMPLETE | get_all_campaigns() with pagination added |
| C1.5 | dev-agent | COMPLETE | deactivate_campaign() added |
| C1.6 | dev-agent | COMPLETE | META_CAMPAIGN_ID, META_CAMPAIGN_URL added |
| C1.7 | dev-agent | COMPLETE | Version bump 1.6.0 -> 2.0.0 |
| C1.8 | deploy-agent | COMPLETE | Deployed to staging, methods verified |

**Commit:** `7337ce2`
**Deployed:** 2025-01-14 to WP Engine Staging

---

## Success Criteria

After this phase:
1. All 5 campaign API methods work
2. Can list, get, create, update, and deactivate campaigns via API
3. Campaign meta constants ready for Phase C2
4. Plugin version is 2.0.0
5. API research documents actual required fields

---

## Notes for Dev Agent

1. **Pattern to follow:** Match existing designation methods exactly
2. **Order of methods:** Keep campaign methods grouped after designation methods
3. **PHPDoc:** Include proper return type documentation
4. **Testing:** Methods will be tested via WP-CLI after deployment
