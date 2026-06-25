# Coding practices

How we keep the code clean, readable and small. The short version is: less is
more, reuse before you build, and write code a person can read top to bottom.

## Less is more

The best code is the code you didn't write. Before adding anything, check it
isn't already there or isn't actually needed.

- Solve the problem in front of you, not the five you imagine coming later.
- Delete dead code rather than leaving it "just in case".
- Fewer moving parts means fewer bugs and less to hold in your head.

## Reuse before you build

Never write something that already exists. This is the big one.

- Need a helper, a component or a style? Search for it first.
- If it exists, use it. If it nearly fits, extend it rather than cloning it.
- If it genuinely doesn't exist, build it once, in the shared place, matching
  the style of what's around it. Then everyone reuses that.

This is DRY in practice. One button, one date formatter, one way to do a
thing. Duplication is where inconsistency and drift creep in.

## Readable over clever

Code is read far more than it's written. Optimise for the person reading it.

- Prefer a plain, line by line approach inside a method over cramming logic
  into a dense one liner. A few named steps read like a description of what's
  happening and mostly document themselves.
- Avoid clever lambda or chain soup when a simple loop or a couple of
  variables would be clearer.
- Avoid regex unless it's genuinely the simplest option, and when you do use
  it, leave a short comment on what it matches and why.
- Name things for what they are. A good name removes the need for a comment.

Example, prefer this:

```ts
function fullName(user: User): string {
    const first = user.firstName.trim();
    const last = user.lastName.trim();
    return `${first} ${last}`;
}
```

Over this:

```ts
const fullName = (u: User) => [u.firstName, u.lastName].map((s) => s.trim()).join(' ');
```

Both work. The first one you can read without stopping to decode it.

## Keep it simple, but don't be terse for its own sake

Simple and short are not the same thing. We want simple. Squeezing a function
down until it's hard to follow isn't simpler, it's just smaller. If making
something shorter makes it harder to read, don't.

## SOLID, lightly

Lean on SOLID where it earns its keep, not as ceremony.

- One job per function, class or component. If you're describing it with
  "and", it's probably doing too much.
- Depend on small, clear interfaces rather than reaching into the guts of
  other things.
- Don't reach for patterns and abstractions until there's a real second case
  asking for them. Premature abstraction costs more than a bit of waiting.

## TypeScript

The frontend is TypeScript, Preact components in `.tsx` files. The types are
there to catch mistakes early and document intent, so use them properly.

- Type every component's props. A small `type` alias above the component is
  the norm here. Reach for `interface` only when you actually need declaration
  merging, which is rare.
- Don't use `any`. If a type is genuinely unknown, use `unknown` and narrow it.
  An `any` quietly switches the checks off for everything it touches.
- Let inference do the obvious work. Annotate the things that matter, props,
  function returns where it aids reading, exported types. Don't restate a type
  the compiler already knows.
- Type-only imports use `import type`, so they're clearly not runtime code and
  get stripped cleanly.
- Run `npm run type-check` (`tsc --noEmit`) before you call something done. It
  must pass with no errors, the same as tests.
- Share a type rather than redeclaring it. If two places describe the same
  shape, export the type from one and import it. Same DRY rule as the code.

## Frontend is component first

The UI is built from components, and pages mostly just wire them together.

- A page should read like a list of components with a little glue, not a pile
  of one off markup. This keeps the UI consistent and the pages short.
- Before building UI, check the components folder. If the piece exists, use
  it. If not, build it as a proper component matching the design system, then
  use that.
- Keep the DOM flat. Avoid wrapper on wrapper and cards inside cards. A
  shallow tree is easier to style, read and keep performant.
- Components own their own styles. Pages only handle basic layout. See the
  design system doc for where styles live.

## Scaffold with artisan

On the backend, reach for `php artisan make:*` rather than hand writing a new
file. Let the framework lay down the boilerplate, then edit it.

- Models, controllers, requests, migrations, tests and the rest all have a
  `make` command. Use it so the file lands in the right place with the right
  namespace and shape.
- It keeps us matching Laravel's conventions instead of drifting our own.
- Then trim and shape the generated file to the task. The command is the
  starting point, not the finished thing.

## Performance and payload

We care about what the user actually downloads.

- Ship only CSS that's in use. Styles live with the component that uses them,
  so there's no dead weight riding along.
- Keep dependencies few and deliberate. Every package is bundle size and
  maintenance you're signing up for.
- Lean on the platform. A bit of plain CSS or a small Preact component often
  beats pulling in a library.
