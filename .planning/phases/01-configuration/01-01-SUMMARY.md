# Plan 01-01 Summary: Create Template Campaign in Classy Sandbox

**Status:** Complete
**Completed:** 2026-01-23
**Updated:** 2026-01-23 (corrected campaign type)

## Deliverables

### Template Campaign Created

| Property | Value |
|----------|-------|
| Campaign ID | **762968** |
| Campaign Name | FCG Template Source |
| Campaign Type | Embedded form (Studio Donation - Embedded) |
| Default Goal | $1,000 |
| Public URL | https://giving.classy.org/campaign/762968/donate |
| Organization ID | 105659 |

### Configuration Details

- **Template type**: Embedded form (matches client's existing campaigns)
- **Donation options**: One-time (default) and Monthly
- **Default amounts**: $75, $50, $25, $10, Other
- **Default designation**: General Fund Project
- **Branding**: Default (to be customized later)

## Verification

- [x] Campaign exists in Classy sandbox
- [x] Campaign is published and active
- [x] Campaign ID is numeric and can be configured in plugin settings
- [x] Campaign is Studio type (not legacy Classy Mode)
- [x] Campaign type matches client's campaigns (Embedded form)
- [x] Plugin settings updated and validated with new ID

## Notes

- **Original campaign 762966** was created as "Donation page" type - deprecated in favor of 762968
- Campaign 762968 uses "Embedded form" type to match client's manually-created campaigns
- Goal amount ($1,000) is a placeholder - will be overridden per-fund when campaigns are duplicated
- Campaign URL follows pattern: `https://giving.classy.org/campaign/{id}/donate`

## Next Steps

Campaign ID **762968** configured in plugin settings (Plan 01-02 complete). Continue with Plan 01-03.

---

*Plan: 01-01 | Phase: 01-configuration*
