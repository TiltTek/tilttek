<!-- phil-dev source-sha256=8d2024b54d2ead0e5168c7c999f76768211c22f6232f1d0dc61e093dde1c2677 -->
# Mise à niveau

## De 0.1 à 0.2

### La table du journal

La table `tilttek_dice_roll` a une colonne `context` nulle, indexée avec `rolled_at`, et une colonne `delivered` : si
le `RollRecorded` du lancer a été émis. Générez la migration :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

ou écrivez-la à la main. Sur MySQL, MariaDB et PostgreSQL :

```sql
ALTER TABLE tilttek_dice_roll ADD context VARCHAR(320) DEFAULT NULL;
CREATE INDEX tilttek_dice_roll_context ON tilttek_dice_roll (context, rolled_at);
ALTER TABLE tilttek_dice_roll ADD delivered BOOLEAN DEFAULT TRUE NOT NULL;
```

Les lignes déjà gardées n'ont pas de contexte et comptent comme émises ; rien d'autre ne change en elles, et elles se
rejouent comme avant.

### Le port du journal

`RecordedRollRepository` a deux méthodes de plus : `getMany(array $ids): array`, les lancers de ces identifiants dans
l'ordre demandé, chacun une fois, les inconnus sautés, pour au plus `RecordedRollRepository::MANY` identifiants ; et
`ofContext(ResourceReference $context, int $limit): array`, les lancers d'un contexte, le plus récent d'abord. Une
application qui a sa propre implémentation du port les ajoute. Celles du bundle, sur Doctrine et en mémoire, les ont.

### Lancer les dés

`rollReplayable()` prend le contexte d'un lancer, par son nom, après l'arrondi :

```php
$dice->rollReplayable('1d20', $member, context: $table);
```

et `DiceRolls` émet `RollRecorded` après chaque lancer rejouable qu'elle enregistre, quand l'application a un
répartiteur d'événements. `rollsIn()` et `recorded()` lisent le journal par contexte et par identifiants. Un appel qui
passait les quatre premiers arguments fonctionne sans changement.

`rollReplayable()` prend aussi une clé `key`, par son nom, après le contexte : le même appel avec la même clé renvoie
le lancer gardé au lieu de lancer de nouveau. `RollRecorded` est émis au moins une fois : un lancer dont l'écouteur a
échoué garde son événement dû, et le même appel avec sa clé, ou `DiceRolls::deliver()`, l'émet de nouveau. Un écouteur
de `RollRecorded` peut entendre deux fois le même lancer, et doit être idempotent.

### L'apparence des dés d'un membre

`DieAppearance` prend `ink` et `texture` après `image`, par leur nom. Une apparence qui ne donnait ni l'une ni l'autre
fonctionne sans changement. Le détail d'un lancer écrit `--tilttek-dice-ink` à côté de `--tilttek-dice-color` sur un
groupe coloré (le noir ou le blanc, selon ce qui se lit le mieux, quand l'apparence ne donne pas d'encre) et
`data-dice-texture` quand il y a une texture. Une feuille de style qui écrivait une couleur fixe pour les nombres des
dés colorés lit plutôt `var(--tilttek-dice-ink)` ; un `roll_detail.html.twig` remplacé ajoute les deux crochets à
partir du gabarit du bundle.

L'apparence dans la vue d'un lancer, `DiceExtension::view()`, a aussi les clés `ink` et `texture`.

### L'accessibilité du spectateur

`DiceAppearance::appearance()` prend un troisième argument, `?Accessibility $viewer = null` : l'accessibilité du
spectateur, standard ou haut contraste, ou null quand la page ne la connaît pas. L'implémentation du port propre à une
application le déclare, même pour l'ignorer : PHP refuse une implémentation qui retire un argument de son interface. Un
appel à deux arguments fonctionne sans changement. `DiceRolls::appearance()`, `DiceExtension::view()` et
`dice_roll_detail()` le transmettent.
