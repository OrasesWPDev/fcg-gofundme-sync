# Classy Technical Meeting - Modal Compatibility Questions

**Meeting Date:** 2026-01-29 at 4:00 PM
**Topic:** Embedded SDK compatibility with Bootstrap modals

---

## Background for Classy

We're implementing Classy's embedded donation form on a WordPress site for Frederick County Community Foundation. The site has ~860 funds, each with its own designation ID linked to a master campaign.

**What works:**
- Embedded form on standalone pages works perfectly
- Designation pre-selection via `?designation={id}` URL parameter works
- Full donation flow completes successfully

**What doesn't work:**
- Embedded form inside Bootstrap 4 modals

---

## The Problem

When the Classy embed is rendered inside a Bootstrap modal:

1. ✅ Initial donation form renders correctly (amount selection, frequency toggle)
2. ✅ User can select amount and click "Donate"
3. ❌ Classy's payment modal fails to open
4. ❌ Browser console shows: `Failed to construct 'HTMLElement': Illegal constructor`

**Error Stack Trace:**
```
TypeError: Failed to construct 'HTMLElement': Illegal constructor
    at ModalView @ 105659:2678
    at ModalCtrl._appendView @ 105659:2497
    at ModalCtrl._subscribeToOpenEvent @ 105659:2459
    at Modal.open @ 105659:2594
    at onSubmit @ 105659:7
```

The error occurs in `ModalView` when the SDK attempts to construct its payment modal.

---

## Questions for Classy

### 1. Is this a known limitation?
Does Classy's embedded SDK have documented limitations when used inside other modal frameworks (Bootstrap, Foundation, etc.)?

### 2. What causes the HTMLElement constructor error?
The error `Failed to construct 'HTMLElement': Illegal constructor` typically occurs with custom elements / web components. Is the Classy SDK using custom elements that have registration conflicts when nested inside other modals?

### 3. Is there an alternative embed mode?
- **Inline mode:** Payment form renders inline instead of opening a modal?
- **Redirect mode:** User redirected to Classy-hosted page for payment?
- **iframe mode:** Full form in an iframe that handles its own modals?

### 4. Are there configuration options we're missing?
Is there a parameter or setting that would make the SDK work inside existing modals? For example:
- `data-modal="false"`
- `data-inline="true"`
- Different embed script URL

### 5. Can the SDK detect and adapt?
Could the SDK detect it's already inside a modal context and render the payment form inline instead of opening another modal?

### 6. What's the recommended architecture?
For sites with many funds displayed in a list/grid with quick-donate functionality, what does Classy recommend?
- One embed per fund on archive pages?
- Redirect to individual fund pages?
- Different SDK integration approach?

### 7. Are there upcoming SDK changes?
Is there a roadmap item or planned update that would address modal nesting compatibility?

---

## Technical Details to Share

**Our Environment:**
- WordPress 6.x
- Bootstrap 4.6
- Classy WP Plugin (Org ID: 105659)
- Master Campaign: 764694
- Component ID: mKAgOmLtRHVGFGh_eaqM6

**Embed Code Used:**
```html
<div id="mKAgOmLtRHVGFGh_eaqM6" classy="764694"></div>
```

**SDK Scripts Loading:**
```
https://giving.classy.org/embedded/api/sdk/js/105659
```

**Console Messages Before Error:**
```
[SDK] Sending User ID to the app: 473466221649287
[SDK] GA4 or GTM is already available. Skipping fetch for analytics settings.
105659:2629 not open
104060:2629 not open
```

The "not open" messages repeat multiple times before the constructor error.

---

## Our Current Workaround

We've disabled modals on archive pages and now link directly to individual fund pages where the embed works correctly.

**User flow change:**
- Before: Click fund → Modal opens → Donate in modal
- After: Click fund → Goes to fund page → Donate on page

This works but adds an extra click for users browsing the fund list.

---

## Ideal Outcome from Meeting

1. Confirmation whether modal nesting is supported or a known limitation
2. Alternative implementation approach if available
3. Timeline for any planned fixes if this is a known issue
4. Best practices documentation for similar use cases

---

*Prepared for Classy technical meeting - 2026-01-29*
