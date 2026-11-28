<!-- phil-dev source-sha256=2de43923740f9d174ff58aabde0469678d90d08a42b2dd25598eed6efb9ba12d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Valeur par défaut |
|---|---|---|
| `Contract\PartyStore` | Où sont gardés les groupes, les membres, les bourses, le butin et les notes | `Store\InMemoryPartyStore` dans les tests |
| `Contract\PartyObserver` | Ce qu'il faut faire d'un changement d'un groupe | Aucune |
| `Limits` | Ses bornes sur les membres par groupe, les groupes par membre, les entrées de butin et les notes par groupe | 12, 10, 500 et 200 |
| `Composition\RoleCatalogue` | Les rôles d'après lesquels une composition est lue, tirés d'un système ou construits à la main, éventuellement aucun | `RoleCatalogue::none()` pour un jeu sans rôles |
| `Psr\Clock\ClockInterface` | Le moment de chaque acte | — |

## Écrire un dépôt de groupes

Un `PartyStore` nomme les groupes et les membres par leurs références écrites `kind:identifier`, et le butin et les
notes par leurs clés dans leur groupe. Il garde :

- les groupes, par référence — `party()`, `saveParty()` et `removeParty()`, qui retire avec le groupe ses membres, sa
  bourse, son butin et ses notes ;
- les membres, une entrée par groupe et par membre — `member()`, `saveMember()`, qui remplace l'entrée précédente,
  `removeMember()`, `countMembers()`, ainsi que `members()` et `membershipsOf()`, le plus ancien arrivé en premier ;
- la bourse de chaque groupe — `purse()`, un `Purse` vide quand rien n'a été enregistré, et `savePurse()` ;
- le butin, une entrée par groupe et par clé — `lootItem()`, `saveLoot()`, qui remplace l'entrée de même clé,
  `removeLoot()`, `countLoot()`, et `loot()`, le plus ancien ajouté en premier ;
- les notes, une par groupe et par clé — `note()`, `saveNote()`, `removeNote()`, `countNotes()`, `notes()`, la plus
  ancienne écrite en premier, et `notesBy($author)`, les notes qu'un membre a écrites dans tous les groupes.

Les moments identiques se lisent dans l'ordre des octets de la référence du groupe, puis de la référence du membre ou
de la clé. La bibliothèque vérifie les limites d'un groupe avant d'écrire, et le solde d'une bourse avant de retirer :
une application qui sert des requêtes concurrentes exécute chaque acte de `Parties` dans une transaction, ou sous un
verrou sur le groupe, pour que deux retraits ne dépensent jamais les mêmes pièces et que deux personnages ne prennent
jamais ensemble la dernière place.

## Observateurs

Un `PartyObserver` reçoit un `PartyChanged` après chaque acte, une fois que le dépôt l'a écrit. L'événement ne contient
que des identifiants — le groupe, le membre concerné, le sujet (une devise, une clé de butin ou une clé de note), la
référence de qui agit — avec le `Change` et le moment ; les montants, les noms et les textes n'y figurent jamais. Un
observateur relaie l'événement — une notification, un message sur un bus — et ne garde aucun état entre deux appels.

| `Change` | Membre | Sujet |
|---|---|---|
| `Formed`, `Updated`, `Disbanded` | — | — |
| `Joined`, `Left`, `RolesAssigned`, `StandingChanged`, `Forgotten` | Le membre | — |
| `Deposited`, `Withdrawn`, `Distributed` | — | La devise |
| `LootAdded`, `LootGiven`, `LootRemoved` | Le porteur, ou aucun pour la réserve | La clé du butin |
| `NoteWritten`, `NoteEdited`, `NoteRemoved` | L'auteur, ou aucun une fois effacé | La clé de la note |
