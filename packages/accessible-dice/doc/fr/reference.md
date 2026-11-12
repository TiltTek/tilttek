<!-- phil-dev source-sha256=82d00483c0d41c5da439180f0b41f5fb3091283f71927b31e04d503d4a70db29 -->
# Référence

| Type | Rôle |
|---|---|
| `TilttekAccessibleDiceBundle` | Le bundle : sa configuration et ses services |
| `Application\AccessibleDice` | Les dés offerts et l'expression d'un choix |
| `Application\DieChoice` | Un dé offert : son nom, sa notation, son libellé et ses faces |
| `Application\FaceDescriber` | Les descriptions des faces des dés personnalisés, et leurs libellés |
| `Application\FaceDescription` | Une face en mots : son libellé, sa description, sa forme et sa valeur |
| `Application\RollAnnouncer` | Un jet dans la phrase qu'un lecteur d'écran annonce |
| `Application\Words` | Les mots, par le traducteur ou en anglais |
| `Application\Politeness` | `polite` ou `assertive` |
| `Application\Exception\InvalidChoice` | Un choix que le formulaire n'offre pas |
| `Infrastructure\Twig\AccessibleDiceExtension` | `accessible_dice()`, `accessible_dice_announcement()` et `accessible_dice_faces()` |
| `Infrastructure\DependencyInjection\AccessibleDiceServices` | Enregistre les services |

| Service | Public |
|---|---|
| `Tilttek\AccessibleDice\Application\Words` | non |
| `Tilttek\AccessibleDice\Application\FaceDescriber` | oui |
| `Tilttek\AccessibleDice\Application\RollAnnouncer` | oui |
| `Tilttek\AccessibleDice\Application\AccessibleDice` | oui |
| `Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension` | oui, avec Twig |

| Ressources | Ce qu'elles sont |
|---|---|
| `assets/src/dice_controller.js` | Le contrôleur `tilttek--accessible-dice--dice` |
| `assets/src/lib/dice.js` | L'expression d'un choix et les phrases des jets |
| `assets/styles/accessible-dice.css` | La feuille de style : les cibles, le focus, les formes et le mouvement |
