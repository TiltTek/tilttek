<!-- phil-dev source-sha256=aef10832a0f137ef681ef645528f98f7fb2a4f7e053c4f9c79ffc4799654ddfc -->
# Référence

| Type | Rôle |
|---|---|
| `LuckTokens` | Octroie, dépense, passe et rafraîchit des jetons ; donne les soldes et le registre ; efface un membre |
| `TableRules` | Les sortes de jetons d'une table et ses meneurs de jeu ; lues à partir de valeurs simples ou de JSON |
| `TokenKind` | Une sorte de jeton : clé, nom, plafond, portée, rafraîchissement, conservation, octrois, transferts |
| `HolderScope` | `Character` ou `Player` |
| `Refresh` | `Never` ou `EachSession` |
| `GrantPolicy` | `GameMasters` ou `Anyone` |
| `Receipt` | Les entrées qu'une opération a écrites, le solde qu'elle a laissé, et s'il s'agissait d'une reprise |
| `Balance` | Combien de jetons d'une sorte un détenteur a |
| `Ledger\LedgerEntry` | Une entrée du registre : position, opération, type, sorte, détenteur, changement, solde, acteur, heure, raison, session |
| `Ledger\EntryType` | `Granted`, `Spent`, `TransferredOut`, `TransferredIn`, `Refreshed` ou `SessionStarted` |
| `Event\TokensGranted` | Des jetons octroyés |
| `Event\TokensSpent` | Des jetons dépensés |
| `Event\TokensTransferred` | Des jetons passés d'un détenteur à un autre |
| `Event\SessionStarted` | Une session commencée, avec ses rafraîchissements |
| `Event\HolderErased` | Un membre effacé sous un pseudonyme, avec le nombre d'entrées réécrites |
| `Contract\TokenRules` | Les règles des tables |
| `Contract\TokenLedger` | Où les registres sont gardés, avec leurs index ; efface un membre sous un pseudonyme |
| `Contract\HolderAccess` | Si un acteur contrôle les jetons d'un détenteur |
| `Store\InMemoryTokenRules` | Les règles en mémoire |
| `Store\InMemoryTokenLedger` | Les registres en mémoire |
| `Store\SelfAccess` | Chaque détenteur contrôle ses propres jetons |

`Instant` est interne : la seule façon dont la bibliothèque écrit une heure, en UTC.

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\LuckTokensException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidTokenRule` | Une sorte, des règles, un montant, un identifiant, une raison ou une entrée de registre que la bibliothèque refuse |
| `Exception\TokenRefused` | Une opération que les règles n'autorisent pas : une table ou une sorte inconnue, un acteur qui n'en a pas le droit, le plafond, un solde trop bas, un transfert non permis, un identifiant réutilisé, une session déjà commencée, un registre plein |
| `Exception\LedgerConflict` | D'autres écrivains ont tenu le registre occupé trois fois de suite |

Rien n'est écrit quand une exception est lancée. Chaque message dit ce qui était attendu, comme
`member:8 holds 0 "fate"; 1 cannot be spent nor given.`
