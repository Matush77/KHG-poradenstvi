# UI polish research notes (2026-11-11)

These notes summarise web research done for this lab. Sources: Refactoring UI, Butterick's Practical Typography, utopia.fyi, Josh Comeau (shadows), Apple HIG, Material 3, Laws of UX, NN/g, WCAG 2.2. Some numbers were confirmed on the source pages and others are standard craft guidance. They are paraphrased, not quoted.

## Spacing
- Use a 4/8 px scale: 4, 8, 12, 16, 24, 32, 48, 64, 96, 128. Avoid values that are not on the scale.
- Space inside a group should be about a half to a third of the space between groups (law of proximity).
- Section padding: 96–128 px on desktop and 48–64 px on phones. Use the same value for every section, and make it fluid with clamp().
- Start with too much whitespace and then remove some. Crowding is what makes a layout look cheap.

## Typography
- Use a type scale ratio of about 1.2 on mobile and 1.25 on desktop, with 6–7 sizes at most. Body text 17–20 px, never below 16.
- Line height: 1.5–1.65 for body text, 1.1–1.25 for display headings, about 1.3 for h3. The larger the text, the smaller the line height.
- Line length: 45–90 characters, with a target of 60–70. Use `max-width: ~65ch` on prose.
- Letter spacing: tighten large headings to −0.01 to −0.02em. Track uppercase labels +0.05 to +0.1em. Leave body text at 0.
- Use 2 families and 2 weights. Pair by contrast, for example a serif for headings with a humanist sans for body text.
- Wrapping: `text-wrap: balance` for headings and `pretty` for paragraphs. For Czech, add non-breaking spaces after one-letter prepositions (k, s, v, z, o, u, a, i).
- In fluid type, clamp() should always include a rem term so that zoom keeps working.

## Colour
- Use 8–10 tinted neutrals and 1–2 brand hues, each with 5–10 predefined shades. Keep the accent to about 10 % of the page.
- A warm, trustworthy palette uses cream instead of pure white, warm dark text instead of black, and one muted accent.
- Body text needs a contrast of at least 4.5:1. Large text and UI components need at least 3:1.
- Shadows: layer 2 shadows, tint them with the background hue, and keep a single light direction. Elements higher up get softer, fainter shadows.

## Details
- Use 2–3 radius values plus a pill shape. Nested elements follow inner radius = outer radius − padding.
- Buttons should be 44–52 px tall, with horizontal padding about 2–2.5 times the vertical padding, at weight 600. Use one primary style per view.
- Motion: 150–250 ms for micro-interactions and 300–500 ms for reveals. Use ease-out, animate only transform and opacity, and respect reduced motion. Use hover effects only on hover-capable devices.
- Focus: use `:focus-visible` with a 2 px outline and 2 px offset.
- Tap targets should be at least 44 px with about 8 px between them. Side gutters on mobile should be 16–24 px.

## Why it feels good
- Aesthetic-usability effect: people perceive pleasant interfaces as easier to use.
- Consistency matters more than any single flourish. Tokens are the mechanism for it.
- Whitespace reads as calm and confident, and crowding reads as anxious.
- For a warm, personal service: serif headings, warm neutrals, restrained rounding, soft shadows, real photos, and slow, subtle motion.
