# Screens

## The routes

| Route | Method and path | Who |
|---|---|---|
| `tilttek_table_safety_table` | `GET /table-safety/{table}` | a participant |
| `tilttek_table_safety_raise` | `POST /table-safety/{table}/signals` | a participant |
| `tilttek_table_safety_signals` | `GET /table-safety/{table}/signals?after=…` | a participant |
| `tilttek_table_safety_acknowledge` | `POST /table-safety/{table}/signals/{id}/acknowledge` | the game master |
| `tilttek_table_safety_answers` | `GET` and `POST /table-safety/{table}/answers` | a participant |
| `tilttek_table_safety_session_zero` | `GET` and `POST /table-safety/{table}/session-zero` | a participant |
| `tilttek_table_safety_feedback` | `GET` and `POST /table-safety/{table}/feedback/{session}` | a participant |
| `tilttek_table_safety_cards` | `GET /table-safety/{table}/cards` | a participant |
| `tilttek_table_safety_cards_pdf` | `GET /table-safety/{table}/cards.pdf` | a participant, with `phil/paged-media` |
| `tilttek_table_safety_game_master` | `GET` and `POST /table-safety/{table}/game-master` | the game master |

`{table}` is a resource reference such as `table:7`. A viewer the application's `TableAccess` does not seat at the
table is refused with 403; every answer is private and never cached. The posts carry a CSRF token; a form refused by
the library is shown again with its reason and the status 422.

## The table during play

`table.html.twig` shows a button per signal the viewer may raise — the check-in for the game master alone, its answers
while a check-in is open —, each with a sentence that says what it does; the signals the whole table sees; and what the
table agreed. A signal raised from the page returns to it with 303; from a script asking for JSON, it answers 201 and
the signal.

What the table agreed, in `_agreement.html.twig`, never says who gave which answer, and depends on the viewer. The game
master sees the protective list: every line, veil and level of the checklist from the first answer, so that they keep
them out of the game at once. The players see the summary shown to people, `TableAgreement::shown()`, in whole groups
of the library's answers threshold, 3 participants by default: under it, they read only that there are not enough
answers yet, and that the game master already sees every line and veil.

## A participant's answers

`answers.html.twig` holds the lines and veils and the consent checklist, one group of radio buttons per theme, grouped
by category, then the table's own themes. `session_zero.html.twig` shows the table's questions, with a sentence and a
link that send a need of safety to the lines and veils and the consent checklist, which the game master sees from the
first answer, while the answers of the session zero wait for a group of participants; `feedback.html.twig`
two text areas, one star or one wish per line. Each page says who sees the answers.

## The game master's screen

`game_master.html.twig` shows every signal — discreet ones marked as such —, each with a button to mark it as seen; the
answers to the last check-in, with a warning when someone asked to slow down or stop; what the table agreed; the pact;
the stars and wishes of a session; and the settings form — tools, named signals, who sees the signals, the table's
themes, the template of the session zero and the game master's own questions.

## The printable cards

A table played in person may prefer cards on paper. `cards.html.twig` shows, for the tools the table uses, the X-card —
a large X with a sentence that says what it does —, the sheet of the lines and veils — a box for a line and one for a
veil beside each theme — and the sheet of the consent checklist — a green, a yellow and a red box beside each theme.
The themes are the table's: the catalogue's by category, then those the table added. The sheets are blank, for a
participant to fill by hand; nothing of anyone's answers is printed. The page needs no JavaScript; each card is a
section with the class `tilttek-table-safety__card`, so that the application's print stylesheet starts each on a new
page:

```css
@media print {
    .tilttek-table-safety--cards .tilttek-table-safety__card { break-after: page; }
    .tilttek-table-safety--cards nav, .tilttek-table-safety__print-help { display: none; }
}
```

With `phil/paged-media` installed, `cards.pdf` gives the same cards as a PDF, on Letter pages, in Helvetica, with the
labels in the viewer's language; the page links to it. A table that uses none of the three tools has no card, and its
PDF is not found.

## The forms

| Form type | Data |
|---|---|
| `ThemeAnswersType` | the answer of each theme, by category; `ThemeAnswers` turns it into `LinesAndVeils` or `ConsentChecklist` |
| `SessionZeroType` | the answers by question key, for `Template::answer()` |
| `StarsAndWishesType` | two texts, one item per line |
| `SafetyToolsType` | a list of `SafetyTool`, never required: for the announcement of a table |
| `TableSettingsType` | the settings; `TableSettingsType::settings()` turns them into `TableSettings` |

An application uses `SafetyToolsType` in its own form of a table's announcement: naming the tools is recommended there,
never required.

## The kit's recipes

The templates draw with `Card`, `Badge`, `Button` and `Alert`; `table_safety_kit('Card:Title')` names a recipe as the
application installed it. `table_safety_tools()` lists the tools for a legend or an announcement.

## The Stimulus controller

`tilttek--table-safety--signals` sits on the list of signals. It sends the forms marked `data-tilttek-table-safety-signal`
in the background, so that raising a signal neither reloads the page nor loses its place; subscribes to the hub when
there is one; reads from the feed the signals missed while the connection was lost; adds each new signal once, and
updates one marked as seen. Without it, every form works as a plain form.

## Accessibility

- Each signal is a native button, reached and pressed with the keyboard, with a sentence that says what it does.
- Each new signal is announced in a polite live region, by its kind and never by who raised it.
- Every section has a heading; the answers are radio buttons and checkboxes with their labels.
- The confirmation of a saved form is announced as a status.
- On the printable cards, each sheet is a table with a caption per category and headers for its themes and boxes;
  each box is named by its answer.

## Translations

The texts are in the domain `tilttek_table_safety`, in English and French, as ICU messages; the built-in questions of
the session zero are `session_zero.questions.<key>` and their options `session_zero.options.<question>.<option>`.
