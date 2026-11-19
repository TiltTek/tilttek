# Screens

## Routes

| Route | Path | Method | What it does |
|---|---|---|---|
| `tilttek_advent_calendar_screen` | `/advent-calendar/{calendar}` | GET | Every box as the viewer sees it. |
| `tilttek_advent_calendar_open` | `/advent-calendar/{calendar}/boxes/{day}` | POST | Opens a box for the current member. |

An unknown or unpublished calendar answers 404, as does a day past its last box. Opening a box asks for a member and a
valid CSRF token, else it answers 403. A box that may not be opened now — before its day, or after its window — answers
409 with the reason. Every answer is private and never cached.

## The screen

The screen shows the calendar's title, a sentence on its opening policy, and every box as a card of the kit: its day
and date, and by its state —

- **locked**: when it opens;
- **openable**: for a member, a plain form whose button opens it; for a visitor, that it is ready;
- **opened**: its surprise;
- **missed**: until when it could be opened.

A visitor sees the boxes in their state for someone who opened none, and a sentence that asks them to sign in. A box
not opened never carries its surprise, in the HTML or anywhere else.

Without JavaScript, the form of an openable box is sent as is: the box opens and the screen comes back at that box,
`303 See Other`. A refused opening shows the screen again with an alert that says why, status 409.

## The Stimulus controller

`tilttek--advent-calendar--boxes` sends the form of an openable box in the background with `Accept: application/json`.
The server answers the day, whether this request opened the box first, whether to celebrate, and the HTML of the opened
box. The controller puts that box in place of the closed one, moves the focus to its title and says in a polite live
region that it was opened. A refused or failed opening is said in the same region; an answer that does not hold
together falls back to sending the form. The controller animates nothing itself.

## The celebration

With `celebration.enabled`, the screen holds the kit's `Celebration`, and a first opening plays it:

- without JavaScript, the screen the form returns to plays it at once, with the message "Box 3 opened!";
- with the controller, the event `celebrate` is dispatched on the window, which the `Celebration` plays.

A box opened again is never celebrated: the bundle celebrates only the openings `BoxOpened` told during the request,
and forgets them after each request. Where motion is reduced, by the system or by `data-motion="reduce"` on an
ancestor, the `Celebration` draws nothing and only says its message. The animation lasts a few seconds at most, holds
while the tab is hidden, and makes no sound.

## Templates

| Template | What it draws |
|---|---|
| `@TilttekAdventCalendar/calendar.html.twig` | The screen: the title, the policy, the alert of a refusal, the boxes, the live region and the celebration. |
| `@TilttekAdventCalendar/_box.html.twig` | One box as the viewer sees it. |
| `@TilttekAdventCalendar/_surprise.html.twig` | The surprise of an opened box, by its kind. |

An application overrides one under `templates/bundles/TilttekAdventCalendarBundle/` — `_surprise.html.twig` to show
its own names for the badges and contents its surprises refer to.

The function `advent_calendar_kit('Card:Title')` names a component of the kit as the application installed it, in the
subdirectory of its `ux_toolkit` configuration.

## Accessibility

The boxes are an ordered list labelled by the title, each box an `article` labelled by its own title, with its date in
a `time` element. Each button says which box it opens. The focus moves to the opened box, and every opening or refusal
is said in a polite live region. A locked or missed box says when it opens or until when it could be opened, in words,
never by colour alone.

## Translations

The texts are in the domain `tilttek_advent_calendar`, in English and French, with ICU messages: dates are written in
the reader's language.
