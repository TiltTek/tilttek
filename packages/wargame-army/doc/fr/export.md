<!-- phil-dev source-sha256=ff54001911a0a59670a2c83785153551a54079da0d94675b96b1e2fe0713e871 -->
# Exportation

## Texte

`Export\TextExport::render($list, $catalogue, $validation = null)` écrit la liste en texte brut, pour la lire, la coller
dans un message ou l'imprimer : son nom, son catalogue avec la version et les points, puis chaque détachement avec ses
unités, ses figurines, ses options et ses points, puis les violations quand une validation est donnée.

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

Une unité ou une option que le catalogue ne contient pas montre son code.

## HTML

`Export\HtmlExport::render($list, $catalogue, $validation = null)` écrit le même contenu en fragment de HTML : un
`<article class="army-list">` avec un `<header>`, une `<section class="army-list__detachment">` pour chaque
détachement, ses unités dans un `<ul class="army-list__units">`, et les violations dans une
`<section class="army-list__violations">`. Chaque valeur est échappée — noms, codes et messages —, sans script et sans
style : l'application place le fragment dans sa page et l'imprime avec sa propre feuille de style.

```html
<article class="army-list">
<header><h1>Ember Wardens</h1><p>Ember Vale Skirmish 2026.1 — 427 / 500 points</p></header>
<section class="army-list__detachment"><h2>Detachment 1: Wardens (Vanguard) — 347 points</h2>
<ul class="army-list__units">
<li><span class="army-list__unit">Captain (1 model)</span> — <span class="army-list__points">75 points</span><ul class="army-list__options"><li>Bow ×1</li><li>Banner ×1</li></ul></li>
</ul></section>
</article>
```

Un PDF est le HTML imprimé par l'application, ou converti par l'outil qu'elle emploie pour ses autres documents.
