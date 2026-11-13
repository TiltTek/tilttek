# Export

## Text

`Export\TextExport::render($list, $catalogue, $validation = null)` writes the list as plain text, to read, paste in a
message or print: its name, its catalogue with the version and the points, then each detachment with its units, models,
options and points, then the violations when a validation is given.

```text
Ember Wardens
Ember Vale Skirmish 2026.1 — 427 / 500 points

Detachment 1: Wardens (Vanguard) — 347 points
  - Captain (1 model) — 75 points
      Bow ×1
      Banner ×1
  - Scouts (6 models) — 72 points
      Long rifle ×1
```

A unit or an option the catalogue does not hold shows its code.

## HTML

`Export\HtmlExport::render($list, $catalogue, $validation = null)` writes the same content as a fragment of HTML: an
`<article class="army-list">` with a `<header>`, one `<section class="army-list__detachment">` for each detachment, its
units in a `<ul class="army-list__units">`, and the violations in a `<section class="army-list__violations">`. Every
value is escaped — names, codes and messages —, with no script and no style: the application places the fragment in its
page and prints it with its own style sheet.

```html
<article class="army-list">
<header><h1>Ember Wardens</h1><p>Ember Vale Skirmish 2026.1 — 427 / 500 points</p></header>
<section class="army-list__detachment"><h2>Detachment 1: Wardens (Vanguard) — 347 points</h2>
<ul class="army-list__units">
<li><span class="army-list__unit">Captain (1 model)</span> — <span class="army-list__points">75 points</span><ul class="army-list__options"><li>Bow ×1</li><li>Banner ×1</li></ul></li>
</ul></section>
</article>
```

A PDF is the HTML printed by the application, or converted by the tool it uses for its other documents.
