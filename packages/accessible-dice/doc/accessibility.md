# Accessibility

What the form does, by default, for each way of using it.

## Screen readers

- Each die is a radio choice in a fieldset with a legend, read with its label: "6-sided die", or the name of a custom
  die.
- Each face of a custom die is described in words — "Heads: a circle, worth 1" — and the list of the faces is the
  description of the choice. A face is never told by its colour alone: `tilttek/dice` refuses a face with a colour and
  neither a shape nor an image, and the description says the shape.
- How many dice and the modifier are labelled number fields; the buttons on each side of the count have their own
  label, "One fewer" and "One more", and name the field they change.
- The expression the choice makes is an `output` tied to its fields.
- A result is announced in a `status` region, `aria-live="polite"` and atomic: after what the screen reader is saying,
  as a whole sentence. The region is emptied, then written, so that two equal results are both announced. The roll the
  form was answered with is announced when the page loads, and each roll of a dice tower on the page as it falls.
  `politeness: assertive` announces at once.

## Keyboards and pointers

- Everything is a native control, in the order it is read: the dice by the arrow keys of a radio group, the fields,
  the buttons, the box and the roll.
- Every target is at least 48 by 48 pixels, past the 24 pixels of WCAG 2.2 and the 44 of its enhanced level; the
  focus is outlined.

## Motion and sound

- The roll moves only under `prefers-reduced-motion: no-preference`; for a viewer who asked for reduced motion,
  nothing moves.
- Nothing is read aloud by default. A box, unticked, lets the viewer hear each result through the speech of the
  browser, in the language of the page.

## Without its script

The form sends the choice, and the server builds and rolls its expression: it rolls the same without its script. The
result it is answered with is in the live region of the page.
