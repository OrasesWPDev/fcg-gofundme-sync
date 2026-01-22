# Stack Research: Classy Campaign API

**Domain:** Campaign sync for WordPress plugin with GoFundMe Pro (Classy) API
**Researched:** 2026-01-22
**Confidence:** MEDIUM (WebSearch verified with multiple sources, awaiting official API docs confirmation on some details)

## Executive Summary

Campaign operations in the Classy API 2.0 (now GoFundMe Pro as of May 6, 2025) follow a duplication-based workflow rather than direct creation. The existing plugin already has OAuth2 authentication, WordPress HTTP client (wp_remote_*), and designation sync patterns in place. Campaign sync extends these patterns with template duplication, field updates, and status management.

**Key Finding:** The project context states that `POST /organizations/{org_id}/campaigns` is NOT a public endpoint. This was not explicitly confirmed in available documentation, but the duplication workflow is well-documented as the standard approach.

## Campaign Endpoints

### Campaign Duplication (Create)

**Endpoint Pattern:** `POST /campaigns/{source_campaign_id}/duplicate`

**Purpose:** Create a new campaign by duplicating an existing template campaign.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the organization's campaigns.

**Request Body:**
```json
{
  "overrides": {
    "name": "New Campaign Name",
    "raw_goal": "5000.000",
    "raw_currency_code": "USD",
    "description": "Campaign description",
    "started_at": "2026-01-22T00:00:00Z"
  },
  "duplicates": []
}
```

**Key Parameters:**
- `overrides` (object): Attributes to overwrite from the source campaign. Can include any attribute settable in the campaign creation endpoint.
- `duplicates` (array): Specifies which related objects to duplicate (tickets, ecards, permissions, etc.). Default: empty (related objects not duplicated).

**Behavior:**
- Creates a new campaign as a copy of the source campaign
- Applies overrides to customize the new campaign
- Related objects (tickets, ecards, permissions) are NOT duplicated by default
- New campaign inherits template structure and configuration
- As of 2025 updates, email settings are correctly retained in duplicated campaigns

**Response:**
```json
{
  "id": 12345,
  "name": "New Campaign Name",
  "status": "unpublished",
  "raw_goal": "5000.000",
  "raw_currency_code": "USD",
  "...": "other campaign fields"
}
```

**Confidence:** MEDIUM - Well-documented in search results, but exact endpoint URL format not confirmed from official API docs.

**Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [GoFundMe Pro API Documentation](https://docs.classy.org/)
- [Automate Campaign Creation via API | Convertr](https://www.convertr.io/resources/product/campaign-duplication-api)

### Campaign Publishing

**Endpoint Pattern:** `POST /campaigns/{campaign_id}/publish`

**Purpose:** Publish a campaign that is currently in unpublished or draft status.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the campaign.

**Request Body:** Empty object `{}`

**Behavior:**
- Changes campaign status from 'unpublished' or 'draft' to 'active'
- Active campaigns count towards the organization's max campaign limit
- Legacy campaigns (created_with = classyapp) CANNOT be published via API

**Response:**
```json
{
  "id": 12345,
  "status": "active",
  "...": "other campaign fields"
}
```

**Confidence:** MEDIUM - Status workflow documented in search results, exact endpoint pattern inferred from documentation references.

**Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions)

### Campaign Unpublishing

**Endpoint Pattern:** `POST /campaigns/{campaign_id}/unpublish`

**Purpose:** Unpublish a campaign that is currently in active status.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the campaign.

**Request Body:** Empty object `{}`

**Behavior:**
- Changes campaign status from 'active' to 'unpublished'
- Does not count towards organization's max campaign limit when unpublished
- Preserves campaign data and donation history

**Response:**
```json
{
  "id": 12345,
  "status": "unpublished",
  "...": "other campaign fields"
}
```

**Confidence:** MEDIUM - Referenced in search results alongside publish endpoint.

**Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)

### Campaign Deactivation

**Endpoint Pattern:** `POST /campaigns/{campaign_id}/deactivate`

**Status:** ALREADY IMPLEMENTED in existing codebase (see `class-api-client.php` line 362)

**Purpose:** Deactivate a campaign that is currently in a non-deactivated state.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the campaign.

**Request Body:** Empty object `{}`

**Behavior:**
- Changes campaign status to 'deactivated'
- More permanent than unpublish, but still preserves donation history
- Can be reactivated later

**Response:**
```json
{
  "success": true,
  "data": null
}
```

**Confidence:** HIGH - Already implemented and working in existing plugin code.

**Sources:**
- Existing codebase: `/Users/chadmacbook/projects/fcg/includes/class-api-client.php`
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)

### Campaign Reactivation

**Endpoint Pattern:** `POST /campaigns/{campaign_id}/reactivate`

**Purpose:** Reactivate a campaign that has been deactivated.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the campaign.

**Request Body:** Empty object `{}`

**Behavior:**
- Changes campaign status from 'deactivated' to 'unpublished'
- Campaign returns in unpublished state (not automatically active)
- Must be published again to make active

**Response:**
```json
{
  "id": 12345,
  "status": "unpublished",
  "...": "other campaign fields"
}
```

**Confidence:** MEDIUM - Referenced in search results as counterpart to deactivate.

**Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)

### Campaign Update

**Endpoint Pattern:** `PUT /campaigns/{campaign_id}`

**Status:** ALREADY IMPLEMENTED in existing codebase (see `class-api-client.php` line 309)

**Purpose:** Update an existing campaign's attributes.

**Authentication:** Requires valid OAuth2 access token with permissions to manage the campaign.

**Request Body:**
```json
{
  "name": "Updated Campaign Name",
  "raw_goal": "10000.000",
  "description": "Updated description"
}
```

**Important Notes on Updatable Fields:**
- Uses HTTP method PUT (not PATCH)
- Parameters to update must be passed as valid JSON in the payload
- **Goal field handling is complex:**
  - Campaign has 4 goal-related attributes: `goal`, `currency_code`, `raw_goal`, `raw_currency_code`
  - `raw_goal` is the amount in the currency specified by `raw_currency_code` (what the campaign hopes to raise)
  - `goal` is the normalized amount in the organization-level `currency_code`
  - Normalization occurs whenever `raw_goal` is updated using the conversion rate at that time
  - If `raw_currency_code` and `currency_code` differ, the `goal` field CANNOT be set manually - must use `raw_goal` instead
  - `currency_code` is inherited from the organization and cannot be changed

**Updatable Fields (Documented):**
- `name` - Campaign name
- `raw_goal` - Fundraising goal (as string with decimals, e.g. "5000.000")
- `raw_currency_code` - Currency code for the goal (e.g. "USD")
- `description` - Campaign description/overview
- Other fields documented in API reference (consult official docs for complete list)

**Fields That CANNOT Be Changed:**
- `currency_code` - Inherited from organization
- `goal` - Calculated from `raw_goal` conversion (cannot set directly if currencies differ)

**Response:**
```json
{
  "id": 12345,
  "name": "Updated Campaign Name",
  "raw_goal": "10000.000",
  "...": "other campaign fields"
}
```

**Confidence:** MEDIUM-HIGH - Existing implementation works, goal field behavior documented, but complete list of updatable fields not available without official API docs.

**Sources:**
- Existing codebase: `/Users/chadmacbook/projects/fcg/includes/class-api-client.php`
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [Intro to the Classy API](https://support.classy.org/s/article/intro-to-the-classy-api)

### Campaign Retrieval (GET)

**Endpoint Pattern:** `GET /campaigns/{campaign_id}`

**Status:** ALREADY IMPLEMENTED in existing codebase (see `class-api-client.php` line 319)

**Purpose:** Retrieve a single campaign's data.

**Authentication:** Requires valid OAuth2 access token.

**Response:**
```json
{
  "id": 12345,
  "name": "Campaign Name",
  "status": "active",
  "raw_goal": "5000.000",
  "raw_currency_code": "USD",
  "goal": "5000.000",
  "currency_code": "USD",
  "...": "other campaign fields"
}
```

**Confidence:** HIGH - Already implemented and working.

### Campaign List (GET)

**Endpoint Pattern:** `GET /organizations/{org_id}/campaigns?page={page}&per_page={per_page}`

**Status:** ALREADY IMPLEMENTED in existing codebase (see `class-api-client.php` line 329)

**Purpose:** List all campaigns for the organization with pagination.

**Authentication:** Requires valid OAuth2 access token.

**Query Parameters:**
- `page` - Page number (starts at 1)
- `per_page` - Results per page (max 100)

**Response:**
```json
{
  "data": [
    {
      "id": 12345,
      "name": "Campaign 1",
      "...": "fields"
    }
  ],
  "last_page": 5,
  "current_page": 1,
  "total": 458
}
```

**Confidence:** HIGH - Already implemented and working.

## Campaign Status Workflow

```
[Duplicate from template]
         ↓
    unpublished ←→ active (via publish/unpublish)
         ↓
    deactivated (via deactivate)
         ↓
    unpublished (via reactivate, then must publish again)
```

**Key Status Transitions:**
1. **New campaigns start as:** `unpublished` (after duplication)
2. **To go live:** `unpublished` → `active` (via `/publish` endpoint)
3. **To take down temporarily:** `active` → `unpublished` (via `/unpublish` endpoint)
4. **To deactivate permanently:** `any status` → `deactivated` (via `/deactivate` endpoint)
5. **To restore deactivated:** `deactivated` → `unpublished` (via `/reactivate` endpoint, then publish if needed)

**Important Notes:**
- Active campaigns count towards organization's max campaign limit
- Unpublished campaigns do NOT count towards limit
- Deactivated campaigns preserve donation history
- Campaigns are NEVER deleted via API (preserves financial records)
- Legacy campaigns (created_with = classyapp) cannot be published via API

**Confidence:** MEDIUM-HIGH - Status workflow well-documented in search results and Salesforce integration notes.

**Sources:**
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions)
- [Salesforce integration release notes](https://prosupport.gofundme.com/hc/en-us/articles/37288754355483-Salesforce-integration-release-notes-Nonprofit-Success-Pack-archive)

## Authentication (Existing)

**OAuth2 Flow:** `client_credentials` (machine-to-machine)

**Token Endpoint:** `https://api.classy.org/oauth2/auth`

**Request:**
```
POST https://api.classy.org/oauth2/auth
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id={CLIENT_ID}
&client_secret={CLIENT_SECRET}
```

**Response:**
```json
{
  "access_token": "eyJ...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

**Token Caching:**
- Stored in WordPress transient: `gofundme_access_token`
- Cache duration: `expires_in - 300` seconds (5 minute safety buffer)
- Minimum cache: 60 seconds
- Automatic refresh when expired

**Authorization Header:**
```
Authorization: Bearer {access_token}
```

**Confidence:** HIGH - Already implemented and working in existing plugin.

**Implementation:** See `FCG_GFM_API_Client::get_access_token()` in `/Users/chadmacbook/projects/fcg/includes/class-api-client.php`

## Rate Limits

**Documentation Status:** NOT FOUND in available search results.

**Known Information:**
- Classy/GoFundMe Pro API documentation exists at [docs.classy.org](https://docs.classy.org/) but rate limit details not indexed
- Third-party integrations (ClassyPress WordPress plugin) implement 1-hour caching to limit API usage
- Existing plugin uses 15-minute polling interval (900 seconds) for inbound sync

**Recommendations:**
1. **Assume conservative limits until confirmed:**
   - Max 100 requests per minute (common API standard)
   - Max 10,000 requests per day (conservative estimate)

2. **Implement rate limit handling:**
   - Check for HTTP 429 (Too Many Requests) responses
   - Implement exponential backoff for retries
   - Cache campaign data when possible (use existing WordPress transient pattern)

3. **Optimize request patterns:**
   - Batch operations when possible (use pagination efficiently)
   - Only fetch campaigns that have changed (use last_modified or polling hash)
   - Don't duplicate on every WordPress save (check if campaign exists first)

4. **Contact Classy/GoFundMe Pro support for:**
   - Official rate limit documentation
   - Best practices for bulk operations (758 existing funds to migrate)
   - Webhook availability (if real-time sync becomes required)

**Confidence:** LOW - No official rate limit documentation found. Recommendations based on industry standards and existing plugin patterns.

**Action Item:** Reach out to Classy contact for official rate limit documentation before implementing bulk migration tool.

**Sources:**
- [GFM Suite WordPress Donation Plugin — A GoFundMe Pro (Formerly Classy) Fundraising Plugin](https://www.mittun.com/classypress/) (mentions 1-hour caching for API limits)

## Campaign-Specific Considerations

### Template Campaign Requirement

**Pre-requisite:** A template campaign MUST exist in Classy before the plugin can create campaigns.

**Setup Process:**
1. Client creates a template campaign in Classy UI with desired:
   - Design assets (hero images, logos, branding)
   - Email templates and settings
   - Form configuration (donation amounts, fields)
   - Payment processor settings

2. Client provides template campaign ID to plugin:
   - Store in WordPress options: `fcg_gfm_template_campaign_id`
   - Used as `{source_campaign_id}` in duplication endpoint

3. Each fund duplication will inherit template structure, then override:
   - Name (from fund title)
   - Goal (from ACF fundraising_goal field)
   - Description (from fund content)
   - Start date (from fund publish date)

**Important:** Template campaign should be set to 'unpublished' status to avoid appearing as a live campaign.

**Confidence:** HIGH - Duplication workflow and template inheritance well-documented.

**Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [How to Edit Campaign Templates - Classy support center](https://support.classy.org/s/article/edit-templates)

### Campaign URL Generation

**Pattern:** `https://www.classy.org/{org_id}/campaigns/{campaign_id}`

**Storage:**
- Post meta key: `_gofundme_campaign_url` (already defined in existing stack)
- Constructed after successful campaign creation
- Displayed in WordPress admin for easy access

**Confidence:** MEDIUM - URL pattern inferred from Classy platform structure, not confirmed from API docs.

### Email Settings Inheritance

**2025 Update:** Classy fixed a bug where email settings were not retained when duplicating campaigns.

**Current Behavior:**
- Duplicated campaigns now correctly inherit the original campaign's email configuration
- Email templates from template campaign will be applied to new campaigns
- No additional email setup required per-campaign

**Confidence:** HIGH - Documented in 2024 release notes.

**Sources:**
- [Release notes 2024 – GoFundMe Pro Help Center](https://prosupport.gofundme.com/hc/en-us/articles/37288721467931-Release-notes-2024)

### Studio vs Classic Campaigns

**Two Campaign Types:**
1. **Studio Campaigns:** New campaign creation workflow (generally available as of 2024)
   - Faster setup: "idea to launch-ready in minutes"
   - Meta feature defaults to "on"
   - Progress bars default to "off"
   - Uses new template system with Giving Season filters

2. **Legacy Campaigns:** Created with Classy Mode (classyapp)
   - CANNOT be published via API
   - May have different field structures
   - Plugin should detect and skip or warn on these

**Plugin Implications:**
- Template campaign should be a Studio campaign (not legacy)
- Check `created_with` field when retrieving campaigns
- Warn in admin UI if template campaign is legacy type

**Confidence:** MEDIUM - Documented in release notes, but exact field names not confirmed.

**Sources:**
- [Release notes 2024 – GoFundMe Pro Help Center](https://prosupport.gofundme.com/hc/en-us/articles/37288721467931-Release-notes-2024)

## WordPress HTTP Client (Existing)

**Implementation:** Uses WordPress core `wp_remote_request()` function

**Configuration:**
```php
$args = [
    'method'  => 'POST',
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
    ],
    'body'    => wp_json_encode($data),
    'timeout' => 30,
];
$response = wp_remote_request($url, $args);
```

**Error Handling:**
- Uses `is_wp_error($response)` to detect WordPress-level errors
- Uses `wp_remote_retrieve_response_code($response)` to get HTTP status
- Uses `wp_remote_retrieve_body($response)` to get response body
- Handles 204 No Content for successful DELETE operations
- Handles HTTP 4xx/5xx errors with error logging

**Confidence:** HIGH - Already implemented and working for designation endpoints.

**Implementation:** See `FCG_GFM_API_Client::request()` in `/Users/chadmacbook/projects/fcg/includes/class-api-client.php`

## Data Persistence (Existing + Campaign Extensions)

**Existing Post Meta Keys:**
- `_gofundme_designation_id` - Classy designation ID (already in use)
- `_gofundme_last_sync` - MySQL datetime of last successful sync (already in use)
- `_gofundme_poll_hash` - MD5 hash for change detection (already in use)
- `_gofundme_sync_source` - 'wordpress' or 'gofundme' (already in use)
- `_gofundme_sync_error` - Error message if sync failed (already in use)
- `_gofundme_sync_attempts` - Number of retry attempts (already in use)
- `_gofundme_sync_last_attempt` - Timestamp of last retry (already in use)

**Campaign-Specific Meta Keys (Already Defined in Stack):**
- `_gofundme_campaign_id` - Classy campaign ID
- `_gofundme_campaign_url` - Campaign public URL

**WordPress Options (Plugin Settings):**
- New option needed: `fcg_gfm_template_campaign_id` - Template campaign ID for duplication

**ACF Field:**
- New field needed: `fundraising_goal` - Campaign fundraising goal (stored as number)

**Confidence:** HIGH - Meta keys already defined in existing stack document, pattern consistent with designation sync.

## Recommended Implementation Approach

### Phase 1: Duplication Infrastructure
1. Add template campaign ID setting to admin UI
2. Implement `duplicate_campaign()` method in `FCG_GFM_API_Client`
3. Implement `publish_campaign()` method in `FCG_GFM_API_Client`
4. Add campaign ID and URL to post meta on successful duplication

### Phase 2: Sync Handler Integration
1. Extend `FCG_GFM_Sync_Handler` to hook campaign operations
2. On fund publish: duplicate template → update fields → publish campaign
3. On fund update: check if campaign exists → update campaign
4. On fund trash: unpublish campaign (not deactivate, to allow restore)
5. On fund untrash: publish campaign

### Phase 3: Bulk Migration
1. WP-CLI command to find funds without campaigns
2. Process in batches (10-20 at a time) to respect rate limits
3. Sleep between batches to avoid overwhelming API
4. Log successes and failures for review

### Phase 4: Inbound Sync
1. Extend `FCG_GFM_Sync_Poller` to fetch campaign data
2. Pull campaign status, goal progress, donation totals
3. Update WordPress post meta with synced data
4. Display in admin UI (meta box or column)

**Confidence:** HIGH - Implementation approach follows existing designation sync patterns with proven track record.

## Open Questions

These questions should be answered before full implementation:

1. **Rate Limits:** What are the official rate limits for the Classy API?
   - **Why it matters:** Bulk migration of 758 funds requires rate limit knowledge
   - **Contact:** Reach out to Classy support or partner contact

2. **Updatable Fields:** Complete list of fields that can be updated via PUT /campaigns/{id}
   - **Why it matters:** Need to know if we can update description, start_date, etc.
   - **Resolution:** Consult official API reference at docs.classy.org

3. **Template Campaign Type:** Can a Studio campaign be duplicated via API?
   - **Why it matters:** Need to ensure template compatibility with API workflow
   - **Resolution:** Test with staging environment or confirm with Classy docs

4. **Campaign URL Pattern:** What's the correct public URL format for campaigns?
   - **Why it matters:** Need to store and display correct campaign links in WordPress
   - **Resolution:** Inspect existing campaign URLs or consult Classy docs

5. **Duplicate Endpoint Return Value:** What campaign data is returned after successful duplication?
   - **Why it matters:** Need to know if we get campaign ID and URL immediately or must fetch separately
   - **Resolution:** Test with staging API or consult docs

6. **Campaign Status on Duplicate:** What status does a newly duplicated campaign start in?
   - **Why it matters:** Need to know if we must publish immediately or can set up first
   - **Resolution:** Test with staging API or consult docs

## Confidence Assessment

| Component | Level | Rationale |
|-----------|-------|-----------|
| OAuth2 Authentication | HIGH | Already implemented and working |
| WordPress HTTP Client | HIGH | Already implemented and working |
| Campaign Duplication Concept | MEDIUM | Well-documented in searches, URL pattern not confirmed |
| Campaign Status Workflow | MEDIUM-HIGH | Multiple sources agree on status transitions |
| Campaign Update Fields | MEDIUM | Goal fields well-documented, complete list needs official docs |
| Rate Limits | LOW | No documentation found, requires Classy contact |
| Template Campaign Setup | HIGH | Template system well-documented in user docs |
| Implementation Pattern | HIGH | Follows proven designation sync architecture |

## Next Steps

1. **Verify with official docs:** Access [docs.classy.org](https://docs.classy.org/) to confirm endpoint URLs and parameters
2. **Contact Classy support:** Get rate limit documentation and updatable fields confirmation
3. **Test in staging:** Use WP Engine staging environment with sandbox API to verify duplication workflow
4. **Document findings:** Update this stack research with confirmed details from testing

## Sources Summary

**Primary Sources:**
- [Classy API Documentation](https://developers.classy.org/api-docs/v2/index.html)
- [GoFundMe Pro API Documentation](https://docs.classy.org/)
- [Campaign Status Definitions - Classy support center](https://support.classy.org/s/article/campaign-status-definitions)
- [Release notes 2024 – GoFundMe Pro Help Center](https://prosupport.gofundme.com/hc/en-us/articles/37288721467931-Release-notes-2024)

**Supporting Sources:**
- [Automate Campaign Creation via API | Convertr](https://www.convertr.io/resources/product/campaign-duplication-api)
- [Intro to the Classy API](https://support.classy.org/s/article/intro-to-the-classy-api)
- [GFM Suite WordPress Donation Plugin](https://www.mittun.com/classypress/)
- [Harnessing the Power of the Classy API to drive online donations - Factor 1 Studios](https://factor1studios.com/harnessing-power-classy-api/)

---

*Stack research completed: 2026-01-22*
*Rebranding note: Classy became GoFundMe Pro on May 6, 2025*
