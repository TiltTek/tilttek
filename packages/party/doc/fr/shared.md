<!-- phil-dev source-sha256=7e5c5c082f2ba49789ee98b11ebe2509b399842ad496f286d18e81cb8b1013c0 -->
# Ce que le groupe partage

## La bourse

La bourse contient des pièces entières par devise : des nombres de la plus petite pièce du jeu sous une clé que choisit
l'application — `gp`, `sp`, `credits` —, jamais de l'argent réel. Une clé de devise suit la règle des clés et commence
par une lettre.

```php
$parties->deposit($party, 'gp', 250, $by);   // the purse after the deposit
$parties->withdraw($party, 'gp', 40, $by);   // never more than it holds
$parties->purse($party)->amount('gp');       // 210
```

Un montant va de 1 à 1 000 000 000 000 pièces, et une devise en contient au plus autant ; une bourse contient au plus
20 devises. Un retrait au-delà du solde est refusé avec `Refusal::NotEnoughFunds`. Une devise qui atteint zéro est
retirée ; `amounts()` lit les autres dans l'ordre où elles ont d'abord été déposées.

`Purse` est une valeur : `deposit()` et `withdraw()` donnent une nouvelle bourse, de sorte qu'une application peut
calculer avec elle avant d'écrire quoi que ce soit.

## Parts

`shares()` divise une devise également, en pièces entières, sans rien retirer ; `distribute()` retire les parts de la
bourse et renvoie le même `Split` :

```php
$split = $parties->distribute($party, 'gp');   // among the active members
$split->share;                                 // the coins each receives
$split->remainder;                             // fewer coins than members, kept in the purse
$split->shares();                              // ['character:12' => 70, 'character:13' => 70, ...]

$parties->shares($party, 'gp', [$ada, $bo]);   // among chosen members, active or away, each once
```

Par défaut, les membres actifs reçoivent une part, dans l'ordre du groupe ; une liste donnée à la place nomme des
membres du groupe, actifs ou en absence, chacun compté une fois dans l'ordre donné (`Refusal::NotMember` pour tout
autre). Sans personne pour recevoir une part, `Refusal::NoOneToShare`. Une part inférieure à une pièce laisse tout le
montant dans la bourse.

La bibliothèque ne garde que la bourse du groupe : l'application crédite chaque membre de sa part là où elle garde les
pièces propres des personnages.

## Butin

Les entrées de butin vivent sous des clés que donne l'application, chacune avec un nom de 1 à 100 caractères et une
quantité de 1 à 1 000 000. Une entrée est portée par un membre ou déposée dans la réserve :

```php
$parties->addLoot($party, 'loot.rope', 'Silk rope', 2);          // in the stash
$parties->giveLoot($party, 'loot.rope', $ada);                   // Ada carries it
$parties->giveLoot($party, 'loot.rope', null);                   // back in the stash
$parties->removeLoot($party, 'loot.rope', 1);                    // one used; null when none is left
```

Une clé contient une entrée (`Refusal::LootExists`) ; un groupe contient au plus `Limits::$lootPerParty` entrées
(`Refusal::TooMuchLoot`) ; un porteur appartient au groupe (`Refusal::NotMember`) ; retirer plus que la quantité est
refusé avec `Refusal::NotEnoughLoot`. `loot()` lit les entrées, la plus ancienne ajoutée en premier, les égalités par
clé. Le butin qu'un membre portait retourne à la réserve quand il quitte le groupe ou est effacé.

## Notes

Un membre écrit une note sous une clé, de 1 à 5000 caractères UTF-8, non vide, les sauts de ligne et les tabulations
étant les seuls caractères de contrôle :

```php
$parties->writeNote($party, 'lead.mill', $ada, "The miller lies.\nAsk his daughter.");
$parties->editNote($party, 'lead.mill', 'The miller is innocent.', $by);
$parties->removeNote($party, 'lead.mill', $by);
```

L'auteur appartient au groupe (`Refusal::NotMember`) ; une clé contient une note (`Refusal::NoteExists`) ; un groupe
contient au plus `Limits::$notesPerParty` notes (`Refusal::TooManyNotes`). Une modification garde l'auteur et le moment
de l'écriture, et consigne le moment de la modification. `notes()` lit les notes, la plus ancienne écrite en premier,
les égalités par clé. Une note reste quand son auteur quitte le groupe, et perd son auteur quand ce membre est effacé.
