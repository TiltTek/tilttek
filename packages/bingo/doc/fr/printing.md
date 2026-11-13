<!-- phil-dev source-sha256=ea4937bceffccbeb0690c43f7115c5b9eb4b14c6e070b43c20e08e95236e582c -->
# Cartons imprimés

Le bingo d'une séance se joue souvent sur papier : l'organisateur imprime un lot de cartons, les distribue, appelle les
cases et vérifie le carton de celui qui crie. Les cartons imprimés appartiennent à un jeu dont les cases sont
appelées : dans un jeu coché par l'activité, chaque carton appartient à un membre, et aucun n'est imprimé.

## Imprimer un lot

```php
use Tilttek\Bingo\View\PrintedCardView;

$cards = $bingo->print('night-12', 120);                       // list<Card>, held by no player
$views = PrintedCardView::ofBatch($bingo, $bingo->game('night-12'), $cards);
```

`Bingo::print($game, $count)` distribue de 1 à 1 000 cartons d'un coup : leurs numéros de série sont réservés en un seul
changement du jeu, et chaque carton est tiré du secret du serveur comme tout autre, différent de tous les cartons du
jeu ; un numéro de série dont la disposition est déjà prise est sauté pour un nouveau, jusqu'à 32 fois dans le lot. Un
carton imprimé n'a pas de joueur : `isPrinted()` le dit, aucun geste ne le coche, et la réclamation d'un joueur sur lui
est refusée. Le lot est gardé dans le dépôt, de sorte que chaque carton se vérifie plus tard par son numéro de série.

## L'étiquette

Chaque carton imprimé montre une étiquette : son numéro de série et un code de vérification de huit caractères, écrite
`42-7KQ3-M9XA`. `Bingo::label($game, $serial)` la donne sous forme de `SerialLabel` ; `toString()` l'écrit. Le code est
le début d'un HMAC-SHA256 du jeu et du numéro de série sous le secret du serveur, avec une clé à lui, de sorte qu'il ne
dit rien de la disposition ; il est écrit en base 32 de Crockford — chiffres et lettres majuscules sans I, L, O ni U,
qui se lisent de la même façon. Sans le secret, personne ne peut écrire le code d'un numéro de série : un carton dessiné
à la main avec un numéro inventé, ou une photocopie à laquelle on a donné un autre numéro, se distingue aussitôt.

## Vérifier un carton imprimé

```php
$claim = $bingo->verifyLabel('night-12', $typed);              // what the organiser typed
$claim->isWon();
```

`Bingo::verifyLabel()` lit l'étiquette comme l'organisateur la saisit — en majuscules ou en minuscules, avec des espaces
ou des traits d'union entre ses parties, O lu comme 0, I et L comme 1, au plus 32 octets —, refuse un code qui n'est pas
celui que le secret tire pour le numéro de série, puis fait ce que fait `Bingo::verify()` : il tire de nouveau la
disposition à partir du germe, refuse un carton dont la disposition enregistrée diffère, et fait gagner le carton quand
les cases appelées complètent un motif. `SerialLabel::parse()` lit une étiquette de la même façon pour un formulaire ;
tout le reste est refusé avec `InvalidBingo`. Un carton gagne une seule fois : `BingoWon` le nomme avec un joueur nul.

## Le gabarit

`templates/print.html.twig` imprime un lot à partir d'une liste de `View\PrintedCardView` : chaque carton est une
section à part, avec sa grille et son étiquette. Enregistrez le dossier sous un espace de noms de l'application, comme
pour le gabarit du carton ; voir [Célébrations](celebrations.md#le-gabarit-du-carton).

```twig
{% embed '@TilttekBingo/print.html.twig' with {cards: views, labels: {caption: 'bingo.card'|trans, free: 'bingo.free'|trans, check: 'bingo.check'|trans}} %}
    {% block footer %}<p>{{ 'bingo.rules'|trans }}</p>{% endblock %}
{% endembed %}
```

La variable `labels` donne les mots de la page : `caption`, `free` et `check`, en anglais par défaut. Le bloc `footer`,
vide par défaut, est dessiné sous chaque carton : les règles, le prix. La feuille de style de l'application met chaque
carton sur une page à part :

```css
@media print {
    .isBingoPrintedCard { break-after: page; break-inside: avoid; }
}
```

La page est du HTML pour la fenêtre d'impression du navigateur ; une application qui veut un PDF du lot produit le rendu
de la même page avec son propre outil de conversion du HTML en PDF.
