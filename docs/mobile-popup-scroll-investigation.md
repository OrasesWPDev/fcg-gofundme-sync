# Mobile Popup Scroll Lock Investigation

**Date Started:** 2026-02-24
**Status:** In Progress — not yet resolved

---

## Issue Description

On mobile, a popup that says **"Make a difference!"** appears and blocks scrolling. After closing the popup, the page **still cannot be scrolled** — the scroll lock persists until the page is force-reloaded.

---

## Investigation So Far

### What We Know

- The popup title is "Make a difference!"
- It appears on mobile
- Closing it does not restore scroll
- Force-reloading the page restores scroll

### Where the Popup Is NOT

Searched theme PHP/JS files — the text "Make a difference!" does not appear in any theme template or source JavaScript file. It is not in:
- `fund-form.php`
- `single-funds.php`
- `fund-modal.php`
- `src/js/main.js`
- `src/js/cart.js`
- `src/js/app.js`

This means the popup content is almost certainly stored in the **WordPress database** (created via a plugin or the WP admin).

### Plugins Installed (Candidates)

Checked the full plugin list on staging. No dedicated popup plugin found (e.g., Popup Maker, OptinMonster). The most likely sources are:

| Plugin | Why it might be the source |
|--------|---------------------------|
| `cookie-notice` | Displays notices/banners — checked, its message is "We Value Your Privacy" — **not this popup** |
| `insert-headers-and-footers` | Could inject a custom popup script — **not yet checked** |
| `gravityforms` | Could be a Gravity Forms popup/lightbox — **not yet checked** |
| WordPress Customizer / Widget | Could be a custom widget or customizer setting — **not yet checked** |

### Scroll Lock Root Cause (Suspected)

Even once the popup source is identified, the scroll lock after closing is a separate concern. The most likely cause is **Bootstrap's `modal-open` CSS class** being left on `<body>` after the popup closes.

Bootstrap modals work by adding `overflow: hidden` to `<body>` via the `modal-open` class when open, and removing it on close. On mobile (especially iOS Safari), this class is sometimes not removed properly due to how iOS handles fixed positioning and touch scroll events.

**Suspected fix once popup is identified:**
Listen for the Bootstrap `hidden.bs.modal` event and manually restore scroll:
```javascript
$(document).on('hidden.bs.modal', '.modal', function () {
    if ($('.modal.show').length === 0) {
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
    }
});
```

---

## Next Steps

1. **Find the popup source** — SSH into staging and:
   - Check `insert-headers-and-footers` plugin settings via WP-CLI:
     ```bash
     wp option get ihaf_plugin_options
     ```
   - Search WordPress database for "Make a difference":
     ```bash
     wp db search "Make a difference" wp_posts wp_options
     ```
   - Check WordPress widgets:
     ```bash
     wp option get sidebars_widgets
     ```

2. **Identify the scroll lock mechanism** — Once popup source is found, inspect whether it uses Bootstrap modals or a custom overlay with `overflow: hidden`.

3. **Apply fix** — Either:
   - Fix the Bootstrap `modal-open` class cleanup (if Bootstrap modal)
   - Or add CSS/JS to restore `overflow` on close (if custom popup)

4. **Test on mobile** — Verify scroll works after closing popup on iOS and Android.

---

## Files to Potentially Change

| File | Why |
|------|-----|
| `community-foundation/functions.php` | Add JS fix for scroll restoration on modal close |
| `community-foundation/src/js/main.js` | Add scroll fix if theme JS is the source |
| Unknown popup source file | Fix or configure the popup itself |

---

*Last Updated: 2026-02-24*
