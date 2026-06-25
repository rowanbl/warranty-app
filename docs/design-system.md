# Design system

The visual rules and where styles live. The goal is one consistent, clean
look across the whole site, built from a small set of reusable pieces. When in
doubt, reuse what's here rather than inventing something new.

## Principles

- Consistency first. A thing should look and behave the same everywhere it
  appears. One button, one card, one chip.
- Less is more. Fewer styles, fewer sizes, fewer colours. Restraint is the
  look.
- Clean and rounded. Pill-shaped buttons, inputs and chips, softly rounded
  cards, tidy alignment and plenty of breathing room.
- Simple and usable for everyone. Plain structured layouts, clear contrast,
  proper labels. The site should be easy to use for anyone, on any device.

## Where styles live

CSS is split so it's easy to find what you're after, but it still bundles to
one small file.

- `tokens.css` holds every token. Colours, type sizes, spacing, layout
  widths. Change a value here and it flows through everything.
- `base.css` is the reset and the plain type styles like headings.
- `layout.css` is page level structure only. The container, grids, gutters.
- `components/` has one file per component, and each component owns its styles.

Rules of thumb:

- No per page styles beyond basic layout. If you're styling a thing, it's a
  component, so give it a component file.
- Always reach for a token rather than a raw value.
- Before adding a style, check it doesn't already exist.

## Colour

The Warranty Wise palette, taken from the site and the iOS app: navy carries
the design, orange is the accent. Used sparingly, the accent stays the thing
the eye is drawn to.

- `--navy` (`#0A2540`) is the brand colour and the page background. `--navy-dark`
  and `--navy-soft` are the darker and lighter steps for depth.
- `--page` is the navy background, `--surface` is white for cards on top of it.
- `--ink` (`#0A2540`) for text on light surfaces, `--ink-muted` (`#5B6B7C`) for
  secondary text. `--on-dark` and `--on-dark-muted` are the white text levels
  for navy backgrounds.
- `--border` for the thin lines on light cards and inputs, `--border-on-dark`
  for lines on navy.
- `--accent` (`#f26321`, Warranty Wise orange) leads on primary actions and
  highlights. Primary buttons, the wordmark, active states, focus.
- `--accent-strong` (`#d8431f`) is the darker hover shade for accent fills.
- `--teal` (`#03A4AA`) and `--gold` (`#F2B544`) are the secondary accents, used
  for the occasional highlight. Keep them rare.

Always check contrast. White on the orange clears AA only at the large, bold
button size, so keep orange fills to buttons and big elements, not small text.
Whenever you put text on the accent or on navy, check it reads.

## Type

Four sizes, no more. Noto Sans throughout, leaning bold for weight and
presence.

- `.heading` for page titles, weight 800.
- `.subheading` for section titles, weight 700.
- Body is the default, weight 400.
- `--text-small` for chips and little labels.

If you feel you need a fifth size, you probably need to reuse one of these
four instead.

## Layout and alignment

Keep everything on the same grid so the eye has clean lines to follow.

- One container width (`--container-max`) and one gutter (`--gutter`),
  everywhere. Don't set bespoke max widths per page.
- Use the spacing scale (`--space-*`) for margins and padding. Even rhythm
  comes from reusing the same steps.
- Line things up. Consistent gutters and a shared grid do most of the work.

## Components

- Pill-shaped controls. Buttons, inputs and chips use the full pill radius
  (`--radius`). Cards use the softer `--radius-card`. Nothing has sharp corners.
- Cards are flat with a single 1px border. No cards inside cards, and keep
  stacked padding to a minimum so the DOM stays shallow. The only depth we
  allow is a soft shadow on hover, and only when the whole card is clickable
  (`.card-interactive`).
- No gradient backgrounds on cards, or anywhere really. Flat colour only.
- If a card has an image background it runs edge to edge, no padding around
  the image. The text inside still sits on the normal card padding scale, so
  it lines up with every other card. That means one inner content layer with
  the standard padding, and no extra nesting beyond it.
- Buttons use the one `.btn` component. Primary is the accent fill, the
  `.btn-secondary` outline is for the quieter action beside it. If a new
  control is needed, like a toggle, design it to match. Square, same weights,
  same accent use, same spacing.
- Expander button (`expander` prop on `Button`). An extension of the core
  button with a hidden arrow on the right. The label sits centred, and on
  hover the text and arrow slide 15px apart while the arrow fades in. It
  works on both the primary and secondary buttons, and the arrow always takes
  the text colour. Use it for a forward or continue action, sparingly, it's a
  little bit of personality not the default.

## Spacing and padding

- Use the spacing scale for all padding, and keep it consistent across like
  things. Two cards should have the same padding, not whatever felt right.
- Avoid nested padding. A padded box inside a padded box inside a padded box
  is how alignment drifts. Pad once, at the sensible level.

## Structure and copy

- Lead with a clear heading. No eyebrow text, the little label sitting above a
  title. Just a plain, well sized heading and go.
- Keep layouts plain and structured. Simple rules applied consistently beat
  clever one off arrangements.

## Motion

A little movement adds personality, but it has to feel like one system and it
must never get in the way.

- Animate things logically, where the motion explains what just happened. A
  panel opening, an arrow appearing. Don't animate for the sake of it.
- Use the shared motion tokens, `--ease` and `--dur`, so everything moves on
  the same curve and timing. Soft easing that still feels snappy.
- Keep it jank free. Animate transforms and opacity, not layout. If something
  needs to grow, reveal or move, clip it or use a transform rather than letting
  text reflow mid animation.
- When a panel needs to grow to fit its content, measure the real content
  height in JS and animate to that exact value. Never guess an arbitrary
  max height, it either clips or eases wrong.
- Keep an element consistent between its states. A title shouldn't resize or
  jump. If its size or place has to change, do it with a transform so it stays
  one smooth, connected motion with no reflow.

## Brandable by design

The whole look hangs off the tokens, so the brand can shift in one place.

- Colour, the accent, the corner radius, type sizes, spacing and motion all
  live in `tokens.css`. Change the accent there and it flows everywhere.
- Corners use `var(--radius)` for pill controls and `var(--radius-card)` for
  cards. Change those two values and the whole site re-shapes, no hunting
  through components.
- Because components reference tokens rather than raw values, re-skinning is a
  token change, not a rewrite. Keep it that way, never hard code a brand value
  in a component.

## Accessibility

- Every button needs an accessible name. If it has visible text that's enough.
  If it's icon only, give it an `aria-label` that says what it does.
- Decorative icons, like the expander arrow, are marked `aria-hidden` so a
  screen reader doesn't read them out. The text carries the meaning.
- Always respect `prefers-reduced-motion`. When it's set, drop or shorten
  animations so nothing slides, scrolls on a loop or moves about. Every
  component with motion needs to handle this.
- Always check contrast, especially anything on the accent. See the colour
  section.

When you build something new, it should look like it was always part of the
set. Match the existing pieces rather than introducing a new flavour.
