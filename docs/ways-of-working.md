# Ways of working

How an agent should approach a task here, so the work lands right the first
time and the code stays trustworthy.

## Check the business logic before you build

Whenever there's any doubt about how something should behave, ask. Don't guess
at business logic.

- If the expected outcome isn't spelled out, ask the user for it. What should
  happen on the happy path, and what should happen when things go wrong.
- Pin down the edge cases up front. Empty states, limits, what counts as
  invalid, what the user should see.
- A short question now beats building the wrong thing and unpicking it later.

This matters most where the logic is a decision, not a fact. Anything where a
reasonable person could pick two different behaviours is worth confirming.

## Test driven, to spec

Once the expected behaviour is clear, capture it as tests before writing the
code.

- Turn the agreed behaviour into simple, robust tests. Each test states one
  expectation in plain terms.
- Keep tests readable the same way we keep code readable. Clear names, one
  thing each, no clever setup.
- Then write the code until the tests pass, and stop there. The tests are the
  definition of done, so they guard the business logic as the code changes.

This loop only works if step one happened. Tests written against a guess just
lock in the guess. Confirm the behaviour, then test it, then build it.

## Keep changes small and honest

- Make the smallest change that does the job. Smaller diffs are easier to
  review and to get right.
- Report what actually happened. If a test fails, say so and show the output.
  If something was skipped, say that too.
- Leave the codebase a little cleaner than you found it, without sprawling
  past the task you were asked to do.
