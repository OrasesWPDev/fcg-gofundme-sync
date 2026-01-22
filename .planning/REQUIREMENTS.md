# Requirements: FCG GoFundMe Pro Sync

**Defined:** 2026-01-22
**Core Value:** When a fund is published in WordPress, both the designation AND campaign are automatically created in Classy with correct settings — no manual data entry required.

## v1 Requirements

Requirements for bi-directional sync with campaign support. Each maps to roadmap phases.

### Configuration

- [ ] **CONF-01**: Admin can configure template campaign ID for duplication
- [ ] **CONF-02**: fundraising_goal ACF field added to funds

### Campaign Push Sync (Outbound)

- [ ] **CAMP-01**: When fund is published, campaign is created via template duplication
- [ ] **CAMP-02**: When fund is updated, campaign name and goal are updated
- [ ] **CAMP-03**: When fund is trashed, campaign is deactivated
- [ ] **CAMP-04**: When fund is restored from trash, campaign is reactivated and published
- [ ] **CAMP-05**: Campaign ID is stored in `_gofundme_campaign_id` post meta
- [ ] **CAMP-06**: Campaign URL is stored in `_gofundme_campaign_url` post meta

### Campaign Status Management

- [ ] **STAT-01**: When fund is unpublished (draft), campaign is unpublished
- [ ] **STAT-02**: When fund is republished, campaign is published
- [ ] **STAT-03**: Campaign status maps correctly: publish→active, draft→unpublished, trash→deactivated

### Inbound Sync (Classy → WordPress)

- [ ] **SYNC-01**: Donation totals are polled from Classy every 15 minutes
- [ ] **SYNC-02**: Campaign status is polled and reflected in WordPress
- [ ] **SYNC-03**: Goal progress percentage is calculated and stored
- [ ] **SYNC-04**: Post meta updated with donation data without triggering outbound sync

### Bulk Migration

- [ ] **MIGR-01**: WP-CLI command creates campaigns for existing funds without campaigns
- [ ] **MIGR-02**: Migration runs in batches (50 funds per batch) to avoid timeout
- [ ] **MIGR-03**: Migration is resume-able (only processes funds without campaign_id)
- [ ] **MIGR-04**: Migration includes dry-run mode for testing
- [ ] **MIGR-05**: Migration logs successes and failures

### Admin UI

- [ ] **ADMN-01**: Campaign URL displayed in fund edit meta box
- [ ] **ADMN-02**: Donation total displayed in fund edit meta box
- [ ] **ADMN-03**: Last sync timestamp displayed
- [ ] **ADMN-04**: Manual "Sync Now" button for individual fund

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Sync

- **SYNC-05**: Real-time sync via webhooks (when Classy supports)
- **SYNC-06**: Individual donation records (not just totals)
- **SYNC-07**: Configurable polling interval (currently hardcoded 15 min)

### Admin Enhancements

- **ADMN-05**: Bulk sync from admin list table
- **ADMN-06**: Sync status column in funds list
- **ADMN-07**: Conflict resolution UI

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Direct campaign creation (POST /campaigns) | Returns 403 — not a public endpoint |
| Campaign deletion | Destroys donation history — use deactivate instead |
| Multiple campaigns per fund | 1:1 relationship by design |
| Real-time webhooks | Classy doesn't offer webhook integration |
| Individual donation records | Only totals needed for current use case |
| Local development environment | All testing on WP Engine staging |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CONF-01 | Phase 1 | Pending |
| CONF-02 | Phase 1 | Pending |
| CAMP-01 | Phase 2 | Pending |
| CAMP-02 | Phase 2 | Pending |
| CAMP-03 | Phase 2 | Pending |
| CAMP-04 | Phase 2 | Pending |
| CAMP-05 | Phase 2 | Pending |
| CAMP-06 | Phase 2 | Pending |
| STAT-01 | Phase 3 | Pending |
| STAT-02 | Phase 3 | Pending |
| STAT-03 | Phase 3 | Pending |
| SYNC-01 | Phase 4 | Pending |
| SYNC-02 | Phase 4 | Pending |
| SYNC-03 | Phase 4 | Pending |
| SYNC-04 | Phase 4 | Pending |
| MIGR-01 | Phase 5 | Pending |
| MIGR-02 | Phase 5 | Pending |
| MIGR-03 | Phase 5 | Pending |
| MIGR-04 | Phase 5 | Pending |
| MIGR-05 | Phase 5 | Pending |
| ADMN-01 | Phase 6 | Pending |
| ADMN-02 | Phase 6 | Pending |
| ADMN-03 | Phase 6 | Pending |
| ADMN-04 | Phase 6 | Pending |

**Coverage:**
- v1 requirements: 24 total
- Mapped to phases: 24
- Unmapped: 0 ✓

---
*Requirements defined: 2026-01-22*
*Last updated: 2026-01-22 after roadmap creation*
