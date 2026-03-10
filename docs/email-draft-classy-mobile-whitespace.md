# Email Draft: Classy Mobile Checkout Whitespace Issue

**To:** Luke Dringoli (ldringoli@gofundme.com), Jon Bierma (jbierma@gofundme.com)
**From:** Chad
**Subject:** Mobile checkout issue — "Cover transaction fees" screen unusable on iPhone (Ticket #18229900)

---

Hi Luke and Jon,

We're running into a mobile usability issue with the Classy embedded checkout flow on our staging site. Our client has had multiple testers get completely stuck during the donation process on iPhone, and we've now received two separate video recordings showing the same problem.

**Staging URL:** https://frederickc2stg.wpenginepowered.com
(Navigate to any fund page — e.g., "Dr. George and Carolyn Smith Fund" — and tap Give Now to start a donation)

**Campaign ID:** 764694
**Component ID:** mKAgOmLtRHVGFGh_eaqM6

## The Issue

When a donor reaches the **"Cover transaction fees"** step in the checkout flow on mobile Safari (iPhone), there is a large amount of whitespace between the fee content (the $5.00 amount and "Thank you for covering the fees!" checkbox) and the **Continue button** at the bottom of the modal.

On an iPhone, this gap is large enough that the Continue button is either:
- Barely visible at the very bottom edge of the screen, or
- Pushed completely off-screen when Safari's bottom navigation toolbar appears

The donor cannot scroll within the checkout modal to reach the Continue button — swiping causes the background page to scroll instead of the modal content. The result is that the donor **gets stuck on this screen and cannot complete the donation.**

## What We're Seeing

The "Cover transaction fees" screen content only takes up about 40% of the modal height:
- "Cover transaction fees" heading
- $5.00 USD amount with icon
- "Please consider covering transaction fees" text
- "Thank you for covering the fees!" checkbox

The remaining ~60% of the modal is empty whitespace before the Continue button. On desktop this is just cosmetic, but on mobile it makes the step impassable because:
1. The Continue button lands below the visible viewport
2. Mobile Safari's dynamic toolbar (address bar + bottom nav) further shrinks the visible area
3. Touch/scroll events inside the modal don't scroll the modal content — they scroll the background page instead

## Videos

We have two client-recorded videos showing the problem. I'll share these via our DropBox, but here's what each shows:

1. **"Mobile Stick round 2.mp4"** (1 min, 5 sec) — A tester walks through the full donation flow on iPhone. She selects $100, chooses Debit/Credit, enters card details, and then gets to "Cover transaction fees" where she gets completely stuck. She tries repeatedly to scroll to the Continue button but can't reach it. She eventually gives up and swipes away from the browser.

2. **"sticky mobile.mov"** (short clip) — An earlier test showing similar mobile checkout layout issues during the checkout flow.

## What Would Help

1. **Is there a compact or mobile-optimized layout option** for the embedded checkout flow that reduces the whitespace on screens with minimal content (like the "Cover transaction fees" step)?

2. **Can the "Cover transaction fees" step be configured to display inline** with the payment method selection rather than as a separate full-screen step? Combining it with the previous step would eliminate the problem entirely.

3. **Is there a known fix for the scroll containment issue** where touch events pass through the checkout modal to the background page on mobile Safari?

4. **Can we disable the "Cover transaction fees" step** in Campaign Studio as a temporary workaround until the layout is fixed? If so, where is that setting?

We're targeting a production launch soon and this is currently blocking mobile donations. Any guidance you can provide would be much appreciated.

Thanks,
Chad

---

## Videos to Attach

Share these from DropBox with Luke and Jon:

| Video | Why |
|-------|-----|
| **Mobile Stick round 2.mp4** | Primary — clearly shows the "Cover transaction fees" stuck behavior end-to-end |
| **sticky mobile.mov** | Supporting — shows earlier round of mobile checkout issues |

**Do NOT share these** (unrelated to Classy):
- "Popup reminder blocks forward progress of donation.MOV" — This is about the site's own "Make a difference!" popup, not Classy's checkout
- "In a fund page but defaulted to General.MOV" — Designation defaulting issue (already resolved)
- "safari doesn't load.mov" — Safari loading issue (separate problem)
