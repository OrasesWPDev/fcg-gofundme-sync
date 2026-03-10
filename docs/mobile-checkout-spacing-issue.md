# Mobile Checkout Spacing Issue

**Status:** Documented — no action taken yet
**Reported:** 2026-02-26
**Priority:** Low (cosmetic UX, not blocking)

## Problem

On the Classy embedded checkout flow (payment method screen), there is excessive whitespace between the last payment option (Bank Transfer / ACH) and the "Continue" button.

- **iPhone Max (larger screens):** Continue button is visible but far from payment options
- **Regular iPhones (smaller screens):** Continue button is mostly hidden below the fold
- **Scroll behavior:** Attempting to scroll the popup scrolls the background page instead of the checkout modal (related to existing scroll-lock issue documented in `mobile-popup-scroll-investigation.md`)

## Root Cause

The Classy SDK checkout modal layout positions:
- Payment method options at the top
- Continue button anchored to the bottom of the viewport/container
- Empty whitespace in between, likely reserved for additional payment methods (Apple Pay, Google Pay, PayPal, Venmo)

Since only **Debit/Credit** and **Bank Transfer** are currently enabled, most of that space is empty.

## Why We Can't Fix It Directly

The checkout UI is entirely rendered by the **Classy SDK** inside their controlled frame. The plugin/theme only provides the container `<div>` — no direct CSS control over the checkout flow internals.

## Suggested Fixes (in order of likelihood to work)

### 1. Enable Apple Pay / Google Pay in Classy Dashboard
- **Where:** Classy Campaign Studio → Payment Methods
- **Why:** Digital wallet buttons fill the whitespace gap naturally, compressing the layout
- **Bonus:** Client has indicated they may add Apple Pay anyway — this solves two problems at once
- **Impact:** High — directly addresses the gap and improves conversion rates

### 2. Classy Campaign Studio Checkout Customization
- **Where:** Campaign Studio → Design tab, Campaign Studio → Donations tab
- **What to look for:** Checkout flow layout options, payment method ordering, custom CSS injection
- **Note:** Some Classy plans allow custom CSS for embedded giving forms

### 3. Contact Classy Support
- **Contacts:** Luke Dringoli, Jon Bierma
- **Ask about:**
  - Compact mode or mobile-optimized checkout layout setting
  - CSS override options for checkout flow spacing
  - Whether this is a known issue with an SDK fix planned
- **Context:** This is a common complaint with embedded giving on mobile

### 4. Custom CSS Override (last resort)
- Only works if Classy renders in a same-origin iframe or directly in the DOM (not cross-origin)
- Would need to inspect whether the SDK creates an iframe or shadow DOM
- A diagnostic script could be run in the browser console on staging to check:
  ```js
  // Run on a fund page with the Classy embed loaded
  // Check for iframes
  document.querySelectorAll('iframe').forEach(f => {
    console.log('iframe src:', f.src);
    try { console.log('same-origin:', !!f.contentDocument); }
    catch(e) { console.log('cross-origin (cannot access)'); }
  });
  // Check for shadow DOM
  document.querySelectorAll('*').forEach(el => {
    if (el.shadowRoot) console.log('shadow DOM found on:', el.tagName, el.id);
  });
  ```

## Screenshots

- iPhone Max screenshot taken 2026-02-26 showing the gap (via iPhone Mirroring)
- Payment method screen: ~40% of screen height is empty between Bank Transfer option and Continue button

## Related Issues

- `docs/mobile-popup-scroll-investigation.md` — scroll-lock bug (scrolling background instead of modal)
- Phase 9.1.1 mobile issues (#1 Safari freeze, #2 popup blocking, #4 scroll lock)
