# Classy Email Thread - January 2026

**Source:** Email thread with Luke Dringoli (GoFundMe Principal Technical Partnerships Manager)
**Date Range:** December 2025 - January 28, 2026
**Subject:** Re: Scheduling a Community Foundation call

---

## Key Technical Findings

### 1. Designation Pass-Through Parameter (Jan 28, 2026)

**Question:** Does `?designation=[id]` work with inline embedded forms (`<div id="..." classy="...">`)?

**Answer (Luke):** Yes, the designation pass-through parameter is compatible with inline forms. Ensure the parameter is always included in the URL when linking to the page.

### 2. Adding Designations to Campaigns (Jan 28, 2026)

**Question:** How does a new designation get added to the campaign?

**Answer (Luke):** Campaigns do NOT automatically inherit organization-level designations, but you CAN add a designation to a campaign automatically using the `updateCampaign` endpoint:

```
PUT /campaigns/{campaign_id}
{"designation_id": "{designation_id}"}
```

**Luke's Screenshot Evidence:**
- Endpoint: `PUT {{url}}/{{version}}/campaigns/763276`
- Request body: `{"designation_id": "1896309"}`
- Response: `200 OK` with response showing:
  ```json
  {
    "designation_id": "1896309",
    "type": "dynamic",
    "is_general": 0,
    ...
  }
  ```

**Classy UI Confirmation:**
The screenshot shows the campaign's Settings → Program designations page:
- "Group designations" section with "Active designations"
- "Default Active Group: 1 designations, includes default"
- "Test Designation" visible in the list
- "General Funds Project" also listed

### 3. Single Campaign Architecture Recommendation (Jan 27, 2026)

**Luke's recommendation:** Use a single campaign with all designations loaded in, rather than creating 861 individual campaigns.

> "What we are suggesting is to not create new campaigns at all, but instead use a single campaign with all designations loaded in."

**Jon Bierma's clarification:** The designations appear in the donation form dropdown under "I'd like to support". The default designation can be set campaign-wide OR overridden using `&designation=` parameter.

### 4. Studio Campaign API Limitations (Jan 26, 2026)

The public `duplicateCampaign` and `publishCampaign` endpoints don't properly support Studio campaign types. Classy has internal-only endpoints for Studio campaigns.

---

## Implementation Summary for Phase 6

**After creating a designation via API:**
1. Call `PUT /campaigns/{master_campaign_id}` with `{"designation_id": "{new_designation_id}"}`
2. This adds the designation to the campaign's "Default Active Group"
3. The designation then appears in the donation form dropdown
4. Use `?designation={id}` in page URL to pre-select the fund

**Master Campaign Details:**
- Campaign ID: 764694
- Component ID: mKAgOmLtRHVGFGh_eaqM6

---

## Contact Information

- **Luke Dringoli** - Principal Technical Partnerships Manager, GoFundMe
  - Email: ldringoli@gofundme.com
  - Phone: 203.640.2553
- **Jon Bierma** - Senior Technical Support Engineer, GoFundMe
  - Email: jbierma@gofundme.com
- **Ticket:** #18229900

---

*Document created: 2026-01-29*
*Source: PDF export of email thread*
