<!-- phil-dev source-sha256=3942b2bbe333953809e5a8dd63423f23facdad90f75dba4f9c71de2672c93040 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Gabarits

Remplacez le gabarit comme pour tout bundle, à `templates/bundles/TilttekAccessibleDiceBundle/dice.html.twig`. Un
remplacement garde l'ensemble de champs et sa légende, les libellés des champs et des boutons, les descriptions des
faces, la région dynamique, et les valeurs et les cibles du contrôleur.

## Styles

La feuille de style, `assets/styles/accessible-dice.css`, règle la taille des cibles par
`--tilttek-accessible-dice-target`, 3rem par défaut, et la couleur d'une face par `--tilttek-accessible-dice-face-color`.
Désactivez-la dans le `controllers.json` de l'application pour styler vous-même les classes :

| Classe | Élément |
|---|---|
| `tilttek-accessible-dice` | Le formulaire ; `is-rolling` pendant qu'il est envoyé, sans demande de mouvement réduit |
| `tilttek-accessible-dice__dice`, `tilttek-accessible-dice__die` | Les dés, un dé |
| `tilttek-accessible-dice__faces`, `tilttek-accessible-dice__face`, `tilttek-accessible-dice__shape--<shape>` | Les faces d'un dé personnalisé, une face, sa forme |
| `tilttek-accessible-dice__number` | Combien, le modificateur |
| `tilttek-accessible-dice__expression`, `tilttek-accessible-dice__speak`, `tilttek-accessible-dice__roll` | L'expression, la case, le lancer |
| `tilttek-accessible-dice__result` | La région dynamique |

## Traductions

Les mots sont dans le domaine `tilttek_accessible_dice`, en anglais et en français ; ajoutez un fichier
`translations/tilttek_accessible_dice.<locale>.yaml` pour une autre langue, avec les clés des fichiers du bundle. Les
libellés des faces et les noms des dés personnalisés passent par `dice_translation_domain`.

## D'autres dés sur la page

Le contrôleur annonce tout événement `tilttek--dice-tower--tower:drop` qui atteint la fenêtre. Une autre source de jets
émet le même événement, avec une `view` — `{name, results: [{expression, total, text, dice: [{label, kept}]}]}` —, une
`entry` telle que la tour de dés la pousse, ou un `text` :

```js
window.dispatchEvent(new CustomEvent('tilttek--dice-tower--tower:drop', { detail: { text: 'Brom rolled 17.' } }));
```
