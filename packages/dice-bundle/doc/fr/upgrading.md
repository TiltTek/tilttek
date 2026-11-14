<!-- phil-dev source-sha256=b4f6239962d5f510d3cdf440c8b6ce67aca21756dab59528d6f611b3e172b64b -->
# Mise à niveau

## De 0.1 à 0.2

### La table du journal

La table `tilttek_dice_roll` a une colonne `context` nulle, indexée avec `rolled_at`. Générez la migration :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

ou écrivez-la à la main. Sur MySQL, MariaDB et PostgreSQL :

```sql
ALTER TABLE tilttek_dice_roll ADD context VARCHAR(320) DEFAULT NULL;
CREATE INDEX tilttek_dice_roll_context ON tilttek_dice_roll (context, rolled_at);
```

Les lignes déjà gardées n'ont pas de contexte ; rien d'autre ne change en elles, et elles se rejouent comme avant.

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

### L'apparence des dés d'un membre

`DieAppearance` prend `ink` et `texture` après `image`, par leur nom. Une apparence qui ne donnait ni l'une ni l'autre
fonctionne sans changement. Le détail d'un lancer écrit `--tilttek-dice-ink` à côté de `--tilttek-dice-color` sur un
groupe coloré (le noir ou le blanc, selon ce qui se lit le mieux, quand l'apparence ne donne pas d'encre) et
`data-dice-texture` quand il y a une texture. Une feuille de style qui écrivait une couleur fixe pour les nombres des
dés colorés lit plutôt `var(--tilttek-dice-ink)` ; un `roll_detail.html.twig` remplacé ajoute les deux crochets à
partir du gabarit du bundle.

L'apparence dans la vue d'un lancer, `DiceExtension::view()`, a aussi les clés `ink` et `texture`.
