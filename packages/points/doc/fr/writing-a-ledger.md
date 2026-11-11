<!-- phil-dev source-sha256=90bd24fb84a5b296d07b501ba8335b5eea90c2702915133b21a58a57f1dd3331 -->
# Écrire un registre

Une application qui garde ses points dans son propre stockage implémente `Tilttek\Points\Ledger\Ledger` :

| Méthode | Contrat |
|---|---|
| `append(Movement, DateTimeImmutable $at): Recorded` | Écrit le mouvement à la position suivante, ou retourne l'entrée déjà présente sous sa clé (`new` faux) ; la même clé pour un autre mouvement lève `MovementConflict`. Deux processus qui écrivent la même clé en même temps ne l'écrivent qu'une fois. |
| `appendCovered(Movement, DateTimeImmutable $at): Recorded` | Comme `append()`, mais seulement quand le solde du compte débité couvre le montant, vérifié et écrit en une seule étape ; sinon `InsufficientPoints`. Un doublon retourne l'entrée sans vérifier de nouveau. |
| `find(MovementKey): ?LedgerEntry` | L'entrée de la clé. |
| `causedBy(string $cause): list<LedgerEntry>` | Les entrées d'une clé d'activité, par position. |
| `balance(Account, string $currency): int` | Les crédits moins les débits du compte dans la devise. |
| `net(Account $to, Account $from, string $currency, ?since, ?until): int` | Ce que `to` a reçu de `from` moins ce qu'il lui a rendu, sur les moments de `since`, inclus, à `until`, exclu. |
| `statement(Account, ?string $currency, int $after, int $limit): list<LedgerEntry>` | Les entrées du compte après une position, par position ; `Reading::check()` refuse une position négative ou une limite sous 1. |
| `membersCreditedBefore(string $currency, DateTimeImmutable $cutoff): list<Account>` | Les comptes des membres crédités à la date limite ou avant, chacun une fois, triés. |
| `forgetDerived(?DateTimeImmutable $since): int` | Supprime les mouvements gagnés, renversés et expirés à partir de `since`, ou tous. |
| `eraseMember(ResourceReference): Erasure` | Supprime les mouvements du membre avec les comptes propres au registre, et écrit `erased:member` à la place du membre dans les autres. |

Les positions ne font que croître ; elles n'ont pas à se suivre. Le cas de test
`Tilttek\Points\Tests\Unit\Ledger\LedgerTestCase` énonce le contrat en tests : étendez-le, construisez votre registre
dans `ledger()`, et il s'exécute contre votre stockage.
