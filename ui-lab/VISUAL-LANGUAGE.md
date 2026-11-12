# KHG Poradenství: visual language

This is a measured description of how the site looks. It was written so that Claude, or a designer, can work on the UI without guessing. All numbers are **computed values measured in the browser** on 2026-11-11, not values copied from the CSS. They were taken at 1440 px (desktop) and 375 px (phone) on `index.html`, with spot checks on `blog.html`, one article, `cenik.html`, `kviz.html` and `quiz-overthinking.html`.

Read it in this order: the design intent (§0), the system as it is now (§1–§8), the problems found (§9), the proposed system (§10), and then the change log (§11). The lab tooling is in §12.

Re-measure any page: open it through the lab server and run this in the console:
`(await import('/ui-lab/measure.js')).measure()`

---

## 0. Design intent (what the site should feel like)

- **Mood:** calm, warm, trustworthy, personal. This is a one-person psychotherapy practice. The site should feel like a quiet, well-lit consulting room, not a clinic and not a startup.
- **Means:**
  - Serif display headings (Playfair Display) over a neutral humanist sans body (Inter).
  - Warm cream surfaces.
  - One deep blue as the primary colour and one terracotta accent.
  - Soft shadows and generous space.
  - Slow, small motion.
- **Avoid:**
  - Saturated gradients.
  - Cool grey-blue tints next to warm cream.
  - Heavy 2 px borders.
  - Many different corner radii.
  - Long lines of text.
  - Loud hover jumps.

## 1. Page grid

| Token | Value |
|---|---|
| Container | `max-width: 1200px` content box. Side padding is 32 px at ≥769 px and 16 px at ≤768 px. At 1440 px the content starts at x = 113 and is 1200 px wide, including padding. |
| Breakpoints used | 1100, 968 (main collapse point: navigation becomes a hamburger, hero stacks), 768 (phone layout), 700, 600, 480 |
| Header | Fixed, 89 px tall, logo 65 px tall, white at 98 % opacity with blur |
| Hero top | 140 px desktop and 100 px phone, so it clears the fixed header |
| Implied columns | No explicit 12-column grid. Layouts are ad-hoc `grid-template-columns`: hero 1fr/1fr, about 2fr/1fr, cards `auto-fit minmax(250–280px)`, forced to 4 columns at ≥1100 px. The lab grid overlay assumes 12 / 6 / 4 columns with 32 / 24 / 16 px gutters. |

## 2. Typography

**Note:** the bundled Playfair subset has no lining-figure (`lnum`) feature, so its digits are always old-style (uneven heights). Use Inter for numbers that must line up.

**Families:**
- Headings: `Playfair Display` 700, self-hosted variable font.
- Everything else: `Inter` 300–700.
- Metric-matched fallbacks are defined for both, so the layout does not shift while the fonts load.

**Sizes as rendered (desktop → phone):**

| Role | Desktop | Phone | Font / weight / line-height |
|---|---|---|---|
| Hero h1 `.hero-title` | 64 | 40 | Playfair 700 / 1.10 |
| Section h2 `.section-title` | 48 | 32 | Playfair 700 / 1.20, plus an 80×4 px gradient underline 10 px below |
| Serif h3 (about name, service, publication, CTA) | 32 / 36 / 28 / 24 (mixed) | 24–32 | Playfair 700 / 1.20 |
| Card title h3 (benefit, step, target group) | 20 | 20 | Inter 700 / 1.20 |
| Lead / subtitle | 20–22 | 18–20 | Inter 400 / 1.6–1.7 |
| Body `p` | 17 (global) but 15 / 16 / 18 in components | 16–17 | Inter 400, line-height 1.55 / 1.6 / 1.7 / 1.8 (mixed) |
| Uppercase label | 13, letter-spacing 0.08–0.12 em (3 variants) | 12–13 | Inter 700 |
| Small / meta | 14–15 | 14–15 | Inter 400–500 |
| Button | 16 (`.btn`), 18 (`.btn-hero`, `.btn-large`) | 16–18 | Inter 600 |
| Article body (blog post) | p 16, li 15 | p 16, li 15 | Inter / 1.8 |
| Article h2 | 26 (also 23.2) | 23–26 | Playfair / 1.3 |

**Line length** (characters per line, roughly; the target is 45–75):
- 82ch in About.
- 81ch in Publication.
- 111ch in the "why online" lead.
- 102ch in the ČAP card.
- 89ch in testimonials.
- About 95ch in a blog article (a 1000 px container with 64 px padding at 16 px).

**Colour of text:**
- Headings `--text-dark #2C3E50`.
- Body `--text-medium #5A6C7D`.
- Labels `--text-light #7F8C9A`.
- Accent headings `--primary #2D5A7B`.

## 3. Colour

| Token | Hex | Use |
|---|---|---|
| `--primary` | #2D5A7B | Deep calm blue: headings in cards, hero button, footer, CTA panel |
| `--primary-dark` | #1E3D52 | Gradients, hover |
| `--primary-light` | #4A7B9D | Blob gradient, icon tints |
| `--accent` | #E8927C | Terracotta/salmon: main "Objednat se" buttons, ticks, underline |
| `--accent-light` | #F5B5A5 | Hover, footer link hover |
| `--text-dark` / `-medium` / `-light` | #2C3E50 / #5A6C7D / #7F8C9A | Cool slate greys |
| `--bg-cream` | #FAF8F5 | Warm surface |
| `--bg-light` | #FFFFFF | Main surface |
| `--border-light` | #E8E8E8 | Neutral grey hairline |
| Off-system | #F8F9FB, #F0F7FA, #FFF9E6→#FFEDD5, rgba(74,144,226) | Cool tints in 3 sections, a yellow highlight box, an off-brand blue (#4A90E2) in icon and step gradients |

**Contrast (WCAG):**
- `--primary` with white text: 7.3 : 1. This passes.
- Salmon `--accent` with white text: **2.4 : 1**. This fails, and it is used on every main button.
- `--text-light` on cream: **3.2 : 1**. This fails for the 13 px labels.

## 4. Spacing

**Scale tokens** (all multiples of 8 except 12):
- `--space-xs` 8
- `--space-sm` 16
- `--space-md` 24
- `--space-lg` 32
- `--space-xl` 48
- `--space-2xl` 64
- `--space-3xl` 96

**Rendered rhythm:**
- **Section padding:** 96 / 96 on desktop **and the same 96 / 96 on phone**. Exceptions: testimonials 64 / 48, footer 48 / 24, hero 140 / 96 (100 / 64 on phone).
- **Section header → content:** 96 px (as large as the section padding).
- **Heading → text:**
  - h2 → subtitle 24.
  - h3 → p 24.
  - Hero h1 → p 32.
  - About name → role 8.
- **Card padding:**
  - 48 on benefit, step, technical-info and common-issues cards, also on phone.
  - 32 on target group, featured link and booking option.
  - 28 on credential cards.
  - 64 / 96 on the service panel.
  - 64 on the publication card.
  - **The 48 px card padding on phone leaves a 245 px text column (29ch).**
- **Grid gaps:** 24 / 32 / 48 / 96 px. The hero and about gaps are 96.

## 5. Shape

**Radius** (10 distinct values): 3, 8, 10, 12, 14, 16, 18, 20, 24, 28, plus 50 px pills and 50 % circles, plus the morphing blob on the portrait.

**Borders:**
- 1 px #E8E8E8 on credential and facts elements.
- **2 px** #E8E8E8 on featured links, testimonial cards and booking options.
- 2 px primary on the ČAP card.
- 4 px primary or accent as a left bar on FAQ items, technical info and the highlight box.

## 6. Elevation

There are 13 different shadows. All are single layers, mostly pure black at 4–8 %:
- Small: `0 2px 15px` and `0 4px 20px` with `rgba(0,0,0,.04–.06)`.
- Medium: `0 8px 32px rgba(0,0,0,.08)`, and some tinted with blue (`rgba(45,90,123,.06–.25)`).
- Large: `0 20px 60px` on the portrait and the modal.

## 7. Components (key specs)

- **Buttons:** pill shape, Inter 600, three heights:
  - `.btn` 54 px (14 / 32 padding, 16 px text).
  - `.btn-hero` 65 px (18 / 40, 18 px).
  - `.btn-large` 69 px (20 / 48, 18 px).
  - Hover lifts the button 2–3 px with a coloured shadow.
  - The salmon button changes to dark text on hover.
- **Cards:** white on cream (or white on white), radius 16, soft shadow, sometimes a border.
- **Section title:** centred serif, 80×4 px accent→primary gradient underline.
- **Eyebrow / labels:** 13 px uppercase, 700 weight, tracked. A short accent rule appears before service eyebrows.
- **FAQ:** white rows with a 4 px left bar, question 20 px Inter 700, padding 32 / 48.
- **Carousel:** 44 px round arrow buttons and pill-shaped active dot.
- **Footer:** blue gradient, 3 columns that collapse to 1 at 968 px, white text at 70–90 % opacity.
- **Motion:**
  - `--transition: all .3s cubic-bezier(.4,0,.2,1)` everywhere.
  - Hero fades in from the side (0.8 s).
  - The portrait blob morphs on an 8 s loop.

## 8. Section sequence (homepage, top to bottom)

| Section | Background | Notes |
|---|---|---|
| hero | cream→white gradient | |
| about | white | |
| services | cream | |
| why-online | **cool** #F8F9FB gradient | |
| how-it-works | white | |
| who-for | **cool** gradient | |
| faq | white | |
| featured | **white** | No separation from the FAQ above it |
| testimonials | white→**#F0F7FA** | |
| contact | cream | |
| footer | blue gradient | |

## 9. Findings: what keeps it from feeling polished

Ordered by visual impact.

1. **Phone rhythm is desktop rhythm.** Sections keep 96 px top and bottom and cards keep 48 px padding on a 375 px screen. Text columns shrink to 29–34ch and the page gets about 25 % taller than it needs to be.
2. **Lines too long on desktop.** 80–111 characters per line in About, Publication, the lead text, ČAP, testimonials and articles, against a comfortable 45–75.
3. **Four-column cards that are too narrow.** At ≥1100 px the benefit and target-group cards are 226 / 260 px wide with 48 px padding. That leaves 130–196 px of centred text, wrapping every 2–3 words.
4. **Inconsistent type steps.** Body text uses 4 sizes (15–18) and 4 line-heights (1.55–1.8). Serif h3s use 4 sizes (24–36). On phone the About name (32) equals the section h2 (32), so the hierarchy collapses.
5. **Mixed temperature.** Warm cream sits next to cool #F8F9FB and #F0F7FA tints, an off-brand #4A90E2 blue, and a yellow highlight box. Two white sections in a row (FAQ → References) leave no separation.
6. **Ten radii, thirteen shadows, two border weights.** Each one is fine, but together they read as "assembled", not "designed".
7. **Accessibility:** the main CTA has white text on salmon at 2.4 : 1, and the labels are at 3.2 : 1.
8. **Motion:** `transition: all` animates layout properties. 2–5 px hover jumps feel springy rather than calm.
9. **Section header gap** (96 px) equals the section padding, so headers float away from their own content.
10. **Articles:** 16 px body text with 15 px list items, about 95ch lines, and two different h2 sizes.

## 10. Proposed system (what `ui-lab/polish.css` implements)

**Tokens:**
```
Spacing  4/8 scale (unchanged), plus fluid rhythm:
         --section-y        clamp(56 → 96)
         --section-head-gap clamp(40 → 64)
         --card-pad         clamp(24 → 40)
         --card-pad-sm      clamp(20 → 28)
         --grid-gap         clamp(16 → 32)
Type     body 17 / lh 1.65; lead 17→20; h4 (Inter card title) 20 / 1.3;
         h3 serif 24→30 / 1.25; h2 32→44 / 1.15; h1 40→64 / 1.08, tracking −0.02em;
         labels 13 / 700 / +0.1em uppercase; prose measure 66ch;
         text-wrap: balance (headings) and pretty (paragraphs)
Colour   text-light #66727F (4.5:1), border #E9E4DD (warm), bg-sand #F6F2EC,
         coral #E8927C kept for filled buttons (owner decision),
         tints only from hsl(205 …) brand blue
Radius   10 (small / images), 16 (cards), 24 (panels), pill
Shadow   sm / md / lg, two layers each, tinted hsl(205 40% 25%)
Motion   200–250 ms, cubic-bezier(.2,.8,.2,1), specific properties (not `all`),
         1–2 px hover lift, only on hover-capable devices
```

**Rules of thumb:**
- Space inside a group should be about a third of the space between groups (heading→text 10–16, card→card 16–32, section→section 56–96).
- Never two adjacent sections with the same background. The order is white / cream alternating.
- Card titles use Inter 20 / 700 in primary blue. Panel titles use the Playfair h3 token. Page and section titles use h1 / h2.
- One filled-button colour per view (terracotta) plus the blue hero button. No more than two button sizes.
- Prose never wider than 66ch. Centre only short text (headings, ≤ 2-line subtitles, CTA).

## 11. Change log of the polish layer

| Tag | Change | Before → after |
|---|---|---|
| P1 | Tokens added: fluid spacing, type scale, radius, shadow, motion; `--text` defined (it was undefined in 6 rules) | |
| P2 | Typography unified | Body 15–18 / lh 1.55–1.8 → 17 / 1.65. h2 48 → 44. Serif h3s 24–36 → 24–30. Labels → one recipe. Balance and pretty wrapping |
| P3 | Measure capped | 80–111ch → ≤ 66ch (paragraphs) or a 42rem centred block |
| P4 | Vertical rhythm | Section padding 96 → 56–96 fluid. Header gap 96 → 40–64. Phone page height about 19.9k → 16.9k px |
| P5 | Background alternation | Cool tints removed. Strict white / cream alternation |
| P6 | Cards | One hairline border, tinted layered shadow, fluid padding. Benefit and who-for cards 4 columns → 2×2 at desktop. Icons and step badges re-tinted to brand blue |
| P7 | Radius | 10 values → 10 / 16 / 24 / pill |
| P8 | Buttons | 3 heights → 2 (≈ 48 and 56). Coral #E8927C kept (owner's decision, 2026-11-11; white text stays at 2.4 : 1). 1 px hover lift, hover only on pointer devices, visible focus ring |
| P9 | Details | Highlight box → cream with a 3 px bar. FAQ padding fluid. Lighter header shadow. Tick marks aligned |
| P12 | Blog and quiz overview | Grid 4 → 3 columns (blog) and 2×2 (quizzes). Hero h1 80 → 64 max. 1 px category buttons. Excerpt 15 → 16 px. Calmer hover. Fluid spacing |
| P13 | Quiz pages | **Bug fix:** progress bar hidden under the fixed header on phones. Title 28 → 32 px on phones. One progress-bar design. 1 px options with an inset ring when selected. Results padding 96 → fluid. Material traffic-light result colours mapped to the muted set (sage / blue / ochre / rose). Left-aligned description |
| P14 | Pricing | Label recipe, hairline info boxes, h2 → h3 token, fluid spacing |
| P15 | Benefit cards, contact, hero stats (owner picked A2 / B2 / C2) | Benefit icons → 40×3 px blue-to-coral accent line, left-aligned. Contact icons removed, uppercase label above value, hairlines between rows, no colons ("E-mail"). Hero stats → one line "4 000+ hodin praxe · 100+ doporučení" (no icons, no count-up). Markup parts live in `polish.js` |
| P16 | Quiz symbols, booking window, empty state, list ticks (owner picked D1 / E1 / F1 centred / G2 / H1) | Quiz result symbol → "VÁŠ VÝSLEDEK" label. "10 otázek" pill → line "10 otázek · výsledek ihned". Booking icons → centred cards with ONLINE / OSTRAVA label. 📝 removed, link back to "Vše". ✓ list ticks → 6 px coral dot. No pictograms or emoji are left in the UI apart from arrows and close/prev/next controls |
| P17 | Reviews gap, FAQ motion, tap flash | Review cards stacked in one grid cell (no empty space under short reviews, no page jump). FAQ opens/closes in 220 ms (grid-template-rows 0fr ↔ 1fr, + shrinks into −). `-webkit-tap-highlight-color: transparent` on links and buttons. |
| P18 | Review controls (owner picked K1) | Arrows removed. Dots: filled 7 px #7E8E9C (3.2 : 1), active 20 px blue bar, 44 × 44 px tap area each. Swipe on the card. Auto-advance stops after the first interaction and is off with reduced motion. Lab: `polish.js`; real site: carousel block in `js/script.js` |
| P19 | Hero blob on phones | `blobMorph 8s infinite` on ≤ 768 px (was 2 rounds), only with `prefers-reduced-motion: no-preference`. IntersectionObserver pauses it (`animation-play-state: paused`) while off-screen |
| P10 | Articles | Container 1000 → 832 px, body 16 → 17–18 px (li matched to p), 68ch measure, one h2 size |

**Not changed on purpose:**
- Colours of brand blue and salmon (the salmon is kept for ticks, underlines and accents).
- Fonts.
- Hero layout.
- Portrait blob.
- Any HTML.

Everything is CSS only. To adopt a change, move its block from `polish.css` into the matching place in `css/styles.css` (or another page stylesheet) and bump the `?v=` query on that stylesheet.

**Owner decisions:**
- Keep the coral #E8927C on "Objednat se". Do not replace it with a deeper terracotta (decided 2026-11-11).
- Open: should the 4-column cards stay as 2×2?

A column-by-column before/after table of every change is in `ui-lab/changes.html`.

## 12. Lab tooling (`ui-lab/`, not committed, not deployed)

| File | Purpose |
|---|---|
| `serve.py` | `python3 ui-lab/serve.py`. Serves the site on port 8800 on all interfaces and injects `polish.css` and `lab.js` into every HTML page. `?lab=original` serves the page untouched. |
| `polish.css` | The proposed changes, tagged P1–P10 |
| `lab.js` | Toolbar with these controls: **Original / Polished / Ghost**; **Grid** (12/6/4 columns plus an 8 px baseline); **Measure** (tap an element to see its size, font, colours, padding, margin, radius, and its distance to the neighbouring elements; tap again to select the parent). Shortcuts: O, P, Shift+G, G, I. |
| `measure.js` | The audit script that produced the numbers in this file |

Ghost mode overlays the original page as a semi-transparent layer on top of the polished one. "Align by section" keeps the section you are reading lined up, so changes in spacing don't make the ghost drift. The "difference" blend makes unchanged pixels go black and changed pixels light up.

---

## 13. Icons, imagery and brand marks (audit added 2026-11-11, not changed: an icon redesign was tried in the lab and rejected by the owner on 2026-11-11).

> **Outcome (2026-11-11):** a Lucide icon swap was rejected. The owner then chose typographic replacements instead of new icons: benefit cards get an accent line (A2), the contact block gets uppercase labels and hairlines (B2), the hero stats become one text line (C2). See P15. The direction for any remaining icons is the same: remove or replace with type, don't swap for another pictogram set. P16 (D1 / E1 / F1 / G2 / H1) finished the job: the remaining symbols are arrows (← → ↗) and the close / prev / next controls, which are kept on purpose.

Visual inventories:
- `/ui-lab/icons.html` shows every unique icon at 96, 48 and 24 px.
- `/ui-lab/images.html` shows every illustration and photo.

### Icons: 26 unique, from at least four different sources

| Group | Where | Style | Problem |
|---|---|---|---|
| Benefit icons (4) | "Osobní a online terapie" cards | Custom 48-box line drawings, 2 px stroke | **These are the "AI-looking" ones.** Each one stacks two ideas into one glyph: house + shield + tick, two video tiles + a double arrow, laptop + wifi, map pin + two people. At the rendered 36–48 px they are hard to read. Each sits in an 80 px tinted circle, which is the most common template pattern there is. |
| Hero stats (2) | "+4000 hodin praxe", "+100 doporučení" | Outline clock next to a **filled** star, 32-box | Outline and filled styles are mixed in one row. The star reads as "rating / reviews", which is a stock cliché. The colour is hard-coded `#2D5A7B` instead of `currentColor`. |
| Contact (3) | Contact card | Hand-made 24-box paths | The phone path is wobbly and asymmetric. Colours are hard-coded. These are not from the same set as the booking icons right next to them. |
| UI (close, chevrons, building, monitor, users, clipboard, copy) | Modal, lightbox, booking, quiz | Lucide / Feather style, 24-box, 2 px | Fine. This is the de facto set already. |
| Text glyphs | ✓ in lists, ⚠ ✓ in quiz results, 📝 in the empty blog state | Font or emoji | ⚠ and 📝 turn into colourful emoji on iOS and Android, which looks different on every device. |
| Unused files | `images/blog-icon-*.svg` (4 pastel clip-art: purple brain, teal cross…), `images/FreeSample-Vectorizer-io-logo.svg` (234 KB), `images/logo.png` | | No page references them, but they are still deployed. The blog icons are true clip-art. |

**Stroke weight is inconsistent once size is counted.** Every icon uses `stroke-width: 2`, but in viewBoxes of 24, 32 and 48 drawn at 24–48 px. The rendered line therefore varies from about 1.5 px (benefit, polished) to 2 px (contact), and heavier at large sizes.

**Recommended direction** (needs owner approval):
1. Pick **one** set and draw everything from it. Lucide is already about half the site and the code is free. For a softer, calmer look than Lucide's 2 px, consider Phosphor "Light" or Lucide at `stroke-width: 1.5`.
2. Use **one glyph, one idea**: home, video, map-pin, calendar, and so on. No composite icons.
3. Benefit cards: either a small 24 px icon inline with the title, or no icon at all. Text-only cards with good type usually look more "designed" than generic icons in circles.
4. Hero stats: drop the icons, or use the same outline style. Make the number the hero (large Playfair numeral, small label).
5. Replace the emoji and text glyphs (⚠ 📝) with SVGs from the same set.
6. All icons use `currentColor`, rendered at 20 / 24 px. The stroke is fixed in rendered pixels (`vector-effect: non-scaling-stroke`, or one size per viewBox).
7. Delete the unused SVG and PNG files.

### Illustrations (14 editorial images for blog and quiz pages)
- A coherent set. The palette (navy, coral, cream, dusty blue) matches the site well, and they share one style.
- They do read as AI-generated "flat wellness" art: faceless figures, scribble balls for anxiety, the same soft blobs everywhere. Because they are consistent and on-palette, they are acceptable. If the goal is to look less generic, the strongest options are fewer images (e.g. only on the blog index), or one consistent real-photo treatment.
- The photos (portrait and office) are real and good. They are the most trustworthy assets on the site and deserve more prominence than the illustrations.

### Brand marks
- The **logo** (`logo.webp`, 381×96) uses a **gold #B3A676** and **pure black #000** with its own condensed serif. Neither colour is in the site palette (text is #2C3E50). The logo's serif is also a third typeface next to Playfair and Inter. Options: recolour the logo text to #2C3E50 (and document gold as a brand colour), or bring a touch of the gold into the palette on purpose.
- **Playfair Display + Inter** is one of the most common template pairings, which is part of why the site can feel generic. It is a legitimate, readable choice. Swapping the heading face (e.g. a softer serif such as Fraunces, Newsreader or Source Serif) would change the character the most with the least work. This is a taste decision for the owner.

### Not yet audited
- Mobile navigation menu.
- Cookie bar.
- Quiz question and results screens in their interactive states.
- Booking modal on phone.
- 404 page.
- Focus order.
- Print styles.
- Dark mode (the site has none, and none is needed).
