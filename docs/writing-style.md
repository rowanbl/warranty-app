# Writing style

How we write everything. Code comments, commit messages, UI copy, docs like
this one. The aim is that it reads like a real person wrote it, not a brand
guideline or a press release.

## Voice

Write plainly and a touch informally. Not chatty, not corporate. Imagine
explaining the thing to a colleague who knows their stuff but hasn't seen this
bit before.

- Short sentences win. If one runs long, split it.
- Say the thing directly. Drop filler like "in order to", "it should be noted
  that", "leverage", "utilise".
- A little personality is fine. Stiff and formal is not the goal.

## British English

Use British spelling throughout. Colour, behaviour, organise, centre,
licence (noun), cancelled, and so on. This applies to code too, so class
names, variables and tokens use the British spelling where it comes up.

## Punctuation

Keep it simple. Most of the time a full stop or a comma is all you need.

- No em dashes. Start a new sentence instead.
- No semicolons in prose. Two sentences, or a comma, does the job.
- Avoid fancy quotes and ellipses. Plain punctuation reads fine.

## Comments

Comments explain why, not what. The code already says what it does, so a
comment that restates it is noise. Use the room to capture the reason, the
trade off, or the gotcha that the code cannot show.

Good:

```js
// Strip the HTML here so user input can't bring in unexpected styles.
const clean = stripTags(input);
```

Not so good:

```js
// Strips the HTML from the text.
const clean = stripTags(input);
```

Keep them short. One line is usually plenty. If a comment is growing into a
paragraph, the code underneath might be the thing that needs simplifying.

## UI copy

Same voice as everything else. Clear, short, human.

- Labels and buttons say what happens. "Save changes", not "Submit".
- Error messages tell the person what went wrong and what to do next.
- No jargon the user wouldn't use themselves.
