<!-- phil-dev source-sha256=a9d688a6af4b390f85d5cde4359da547330846ad8de2eb19ddaab937bd516217 -->
# Gabarits

| Twig | Donne |
|---|---|
| `expression\|dice_notation(locale)` | L'expression dans la notation d'une langue ; une expression invalide telle quelle |
| `expression\|dice_dialect('roll20')` | Les commandes du dialecte d'une table, ou null quand le dialecte ne peut pas l'écrire |
| `dice_roll(expression, locale)` | Un `PoolResult` |
| `dice_roll_detail(result, member, locale)` | Le détail d'un lancer, en HTML |
| `dice_text(message)` | Un mot du détail, passé par le traducteur |

## Le détail d'un lancer

`dice_roll_detail()` produit le rendu de `@TilttekDice/roll_detail.html.twig`. Pour chaque instruction, il affiche sa
notation et son total dans un `<output>`. Il affiche le texte ou la liste obtenus dans un
`<output class="tilttek-dice-text">` : chaque texte est traduit dans le domaine configuré, comme clé de traduction, et
échappé comme tout texte. Il affiche les faces comptées pour les dés personnalisés (`2 × Star`) et chaque groupe de dés
en liste avec chaque dé. Cela inclut les groupes à l'intérieur des fonctions, des branches et des groupements, les dés
d'un nombre ou de faces lancés d'abord, les listes de valeurs et les dés jugés à nouveau par `@`.

Chaque dé montre sa face en texte : le nombre, ou le libellé d'une face personnalisée. Une face qui a une forme montre
la forme (`tilttek-dice-shape tilttek-dice-shape-star`, cachée aux lecteurs d'écran) à côté de son libellé, et sa
couleur dans la variable CSS `--tilttek-dice-face-color` : jamais la couleur seule. Les états d'un dé sont des mots,
entre parenthèses, en plus des classes :

| Classe | Mot |
|---|---|
| `is-dropped` | dropped |
| `is-rerolled` | rerolled |
| `is-exploded` | exploded |
| `is-success`, `is-failure` | success, failure |
| `is-from-reroll`, `is-from-explosion` | — |

Avec un membre, chaque groupe porte l'apparence de ses dés :

| Crochet | Contient |
|---|---|
| `data-dice-appearance` | Le nom du jeu |
| `data-dice-material` | Le matériau, s'il y en a un |
| `data-dice-texture` | La clé de texture, s'il y en a une : la feuille de style de l'application la associe à une image |
| `--tilttek-dice-color` | La couleur des dés, s'il y en a une |
| `--tilttek-dice-ink` | La couleur des nombres : l'encre de l'apparence ou, sur un dé coloré sans encre, le noir ou le blanc, selon celui qui contraste le plus avec la couleur, à 4,5:1 au moins |

```css
.tilttek-dice-set[data-dice-appearance] .tilttek-dice-die {
    background: var(--tilttek-dice-color, Canvas);
    color: var(--tilttek-dice-ink, CanvasText);
}
.tilttek-dice-set[data-dice-texture="dice/marble"] .tilttek-dice-die { background-image: url(/images/dice/marble.webp); }
```

Le bundle n'est fourni avec aucune feuille de style ; les classes sont des crochets pour celle de l'application. Une
texture dessinée sous les nombres ne les garde lisibles que si ses couleurs restent proches de `--tilttek-dice-color`.

Les mots (`dropped`, `rerolled`, `exploded`, `success`, `failure`) et les libellés des faces sont traduits dans le
domaine configuré quand le traducteur est installé ; sans traduction, ils s'affichent tels qu'écrits.

Le gabarit échappe lui-même ses textes pour le HTML, dans un bloc `autoescape 'html'`, même là où l'application
désactive l'échappement automatique : `dice_roll_detail()` déclare sa sortie comme du HTML sûr, et les textes d'un
lancer viennent de ce que tapent les membres. Remplacez le gabarit comme celui de tout bundle, dans
`templates/bundles/TilttekDiceBundle/roll_detail.html.twig`, en gardant ce bloc.
