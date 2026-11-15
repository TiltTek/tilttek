# Accessibility

The library draws nothing; the page that shows the tokens keeps them usable by everyone.

## Balances

- Each balance is a number in text with the name of its kind, "Inspiration: 1 of 1", never a row of icons alone; icons,
  if any, are hidden from screen readers.
- The cap is said with the balance, so that a player knows when a grant would go over it.
- The ledger is a table with a caption and headers — when, who, what, how many, balance, why —, in the order of its
  positions, readable without a script.

## Changes

- A grant, a spending or a transfer is announced in a polite live region, as a whole sentence: "Ana spent 1 fate
  point; 2 left." The events carry what the sentence needs: the entry, its holder, its change and its balance.
- A refusal is said in words near the control that caused it, from the message of `TokenRefused`, and the focus stays
  on that control.
- Nothing moves under `prefers-reduced-motion: reduce`, and no sound plays unless the viewer asks for it.

## Controls

- Grant, spend and pass are buttons with their own label — "Spend 1 inspiration" —, at least 24 by 24 pixels.
- The identifier of each operation is drawn with the form, so that a second press of the button is a replay, never a
  second spending.
