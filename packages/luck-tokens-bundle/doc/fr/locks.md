<!-- phil-dev source-sha256=98b7e7d795ac5b18bb2784394be44498c3fcac6bf06be10e14fe2846269891c4 -->
# Verrous

Le registre refuse déjà deux écrivains après la même position, et la bibliothèque le relit, trois fois en tout. C'est
suffisant pour la plupart des tables ; quand beaucoup de joueurs dépensent au même moment, les nouvelles tentatives
peuvent s'épuiser et la dernière atteint l'appelant sous forme de `LedgerConflict`. Un verrou Symfony autour de chaque
opération les fait plutôt s'attendre l'un l'autre.

```yaml
framework:
    lock: '%env(LOCK_DSN)%'     # a store every process shares: Redis, a database, flock on one machine

tilttek_luck_tokens:
    lock:
        enabled: true
        scope: holder           # or table
        ttl: 30.0
```

Le bundle enregistre alors `Tilttek\LuckTokensBundle\Application\LockedLuckTokens`, public, avec les opérations de
`LuckTokens` sous les mêmes noms et les mêmes arguments. Injectez-le là où l'application écrit ; lisez les soldes et le
registre par `LuckTokens`, comme avant.

```php
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;

public function __construct(private LockedLuckTokens $tokens) {}

$this->tokens->spend($table, 'fate', $member, 1, $member, $operation, 'Reroll');
```

## Ce que couvre un verrou

| Opération | Portée `holder` | Portée `table` |
|---|---|---|
| `grant()`, `spend()` | le détenteur | la table |
| `transfer()` | les deux détenteurs, dans l'ordre de leurs références | la table |
| `startSession()` | la table | la table |
| `eraseHolder()` | le membre | le membre |

Les clés sont `tilttek_luck_tokens.holder.<reference>` et `tilttek_luck_tokens.table.<reference>`. Un transfert prend les
verrous de ses deux détenteurs toujours dans le même ordre, de sorte que deux transferts entre les mêmes détenteurs,
un dans chaque sens, ne tiennent jamais chacun un verrou en attendant l'autre. Chaque verrou est pris en attendant,
tenu pour la seule opération, et libéré même quand l'opération est refusée.

- `holder` sérialise ce que fait un détenteur — un double clic avec deux identifiants, deux onglets — et laisse les
  autres libres ; deux détenteurs de la même table peuvent encore se rencontrer dans le registre, et la bibliothèque le
  relit.
- `table` sérialise chaque opération d'une table : jamais deux écrivains ne se rencontrent dans son registre, au prix
  de s'attendre l'un l'autre.

La vérification propre au registre reste dans les deux cas : un verrou qui expire — son `ttl` écoulé —, ou un écrivain
qui ne passe pas par `LockedLuckTokens`, rencontre encore `LedgerConflict` et ses nouvelles tentatives. Les tests
exécutent quatre processus qui octroient au même détenteur en même temps sous les verrous de `flock` : aucun n'en
rencontre un autre dans le registre.

Le stockage doit être partagé par chaque processus qui écrit : `flock` et `semaphore` sur une seule machine, Redis,
PostgreSQL ou un stockage PDO entre machines. Le service ne garde rien entre deux appels.
