# Accessibility

The library draws nothing; the pages that show a game — the team's screen, the hint screen, the leaderboard — keep it
usable by everyone.

## The countdown

- The time left is text, "42 minutes 10 seconds left", in a `<time>` element, never a dial alone.
- It is not announced every second: a polite live region says the time left at chosen marks — every ten minutes, then
  each minute of the last five —, and the screen reader of a player who asks reads it at once.
- Nothing flashes, and no colour alone says that time runs short; the last minutes are said in words too. Under
  `prefers-reduced-motion: reduce`, the countdown changes without animation.
- A pause is said in words, "Paused by the host", not only by a frozen clock.

## The hint screen

- A hint pushed by the host, or a message, is announced in a polite live region, as a whole sentence, with its penalty
  when it has one: "Hint for the candle: look at the top. Two minutes added."
- The text is large, with a contrast ratio of 4.5:1 at least, and stays on screen until the host clears it, so that each
  player reads it at their own pace.
- A sound that announces a hint plays only when the room chooses it, and the text says the same.

## The team's screen

- Asking for a hint is a button with its own label — "Ask for a hint for the candle, two minutes added" —, so the cost
  is known before the press.
- The answer field and the outcome of a guess follow the rules of `tilttek/puzzle-builder`.

## The leaderboard

- The leaderboard is a table with a caption and headers — position, team, final time, hints —, readable without a
  script; the hints are beside the time, not hidden in a tooltip.
- An escape is celebrated with the celebration component of PHIL UX, which respects `prefers-reduced-motion`.
