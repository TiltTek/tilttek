# Accessibility

The screens follow the accessibility of `tilttek/escape-room`.

- **The countdown** is text in a `<time>` element, never a dial alone. It is said in a polite live region at chosen
  marks — every ten minutes, then each minute of the last five —, not every second. A pause is said in words, "Paused by
  the host", and the end in a sentence with the final time and the hints.
- **A hint or a message** is shown until the host clears it, so each player reads it at their own pace, and said once in
  a polite live region as a whole sentence, with the penalty a hint added.
- **The console** is a list of forms whose buttons say what they do and to which puzzle — "Push a hint for The candle
  (1 left)" —; it works without a script. What a button did, or why it was refused, is said in a polite live region,
  and the focus stays on the form that was sent.
- **The celebration** of an escape is the PHIL UX kit's `Celebration`: its message said in a polite live region,
  confetti or fireworks drawn only where motion is not reduced, no sound.
- **The leaderboard** is a table with a caption and headers, the hints beside the time.
- **The consent** is a plain form that says where the team's result stands before the button changes it.

The colours, sizes and contrast are those of the kit's recipes; a stylesheet of the application that enlarges the hint
for a screen across the room keeps a contrast ratio of 4.5:1 at least.
