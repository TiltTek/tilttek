<!-- phil-dev source-sha256=df8e91c080049f5f80cb209110913320636eda9dd7ecd011cade3fb570dfbff2 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\PartyException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Levée quand | Message |
|---|---|---|
| `PartyRefused` | Les règles du groupe refusent l'acte ; `refusal` dit pourquoi | Le message du refus, ci-dessous |
| `UnknownParty` | Aucun groupe ne porte la référence, aucun butin la clé, ou aucune note la clé | `No party is known as "…".`, `The party "…" holds no loot under the key "…".`, `The party "…" holds no note under the key "…".` |
| `InvalidParty` | Un nom, une clé, une devise, le texte d'une note, un montant, une quantité, un rôle, un catalogue de rôles ou une limite est hors des bornes | Ce qui est hors des bornes, et la borne |

`PartyRefused` étend `DomainException`, `UnknownParty` `OutOfBoundsException`, `InvalidParty`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyFormed` | A party already holds this reference. |
| `AlreadyMember` | This member already belongs to the party. |
| `NotMember` | This member does not belong to the party. |
| `Full` | The party holds as many members as allowed. |
| `TooManyParties` | This member belongs to as many parties as allowed. |
| `LootExists` | The party already holds loot under this key. |
| `TooMuchLoot` | The party holds as many loot entries as allowed. |
| `NotEnoughLoot` | The party holds fewer of this loot than asked. |
| `NoteExists` | The party already holds a note under this key. |
| `TooManyNotes` | The party holds as many notes as allowed. |
| `NotEnoughFunds` | The purse holds less of this currency than asked. |
| `NoOneToShare` | No member is there to receive a share. |

Une nouvelle valeur est vérifiée avant les limites et les clés déjà prises : former un groupe, rejoindre, ajouter du
butin ou écrire une note avec un nom, une clé ou un texte invalide lève `InvalidParty`, même quand la référence ou la
clé est déjà prise.

Les exceptions de `phil/resource` et de `tilttek/rpg-systems` parviennent telles quelles à l'appelant quand une
référence est mal formée ou qu'un système est lu.
