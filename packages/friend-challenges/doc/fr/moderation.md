<!-- phil-dev source-sha256=6877590e437303016684d68646fd7f473cca3bb6b7f4f53593c019caebee71b0 -->
# Modération

La modération du site reste celle du site : sa file, ses règles, ses avis. La bibliothèque lui envoie les signalements
et agit sur la parole d'un modérateur, par `Contract\Moderation`.

## Signalements

```php
use Tilttek\FriendChallenges\ReportReason;

$challenges->report('01JD3', $cy, ReportReason::Cheating, proof: 'proof-1', note: 'Not her minis.');
```

Tout membre signale un défi, ou l'une de ses preuves, avec une raison choisie dans une liste — `Spam`, `Harassment`,
`Cheating`, `Unsafe`, `Inappropriate`, `Other` — et une note facultative de 500 caractères au plus. La bibliothèque
vérifie que le défi et la preuve existent et passe un `Report` à `Moderation::report()`, avec son heure en UTC ; elle
n'en conserve rien.

## Ce que fait un modérateur

| Mouvement | Depuis | Ce qu'il fait au lot |
|---|---|---|
| `freeze($id, $moderator)` | Ouvert | Le garde détenu ; rien ne bouge — aucun membre ne participe, aucune preuve n'est envoyée ni examinée, le défi n'expire pas |
| `unfreeze($id, $moderator)` | Gelé | Le garde détenu ; le défi est de nouveau ouvert, et expire à la prochaine exécution si son examen est fini |
| `remove($id, $moderator)` | Ouvert ou gelé | Le rend au créateur |
| `removeProof($id, $proof, $moderator)` | Une preuve en attente d'examen, dans un défi ouvert ou gelé | Rien |

Chaque mouvement vérifie d'abord `Moderation::mayModerate()`, et est refusé avec `ChallengeRefused` pour tout autre
membre. Quand les preuves d'un défi sont laissées aux modérateurs, `mayModerate()` dit aussi qui les approuve et les
rejette.

## Garde-fous entre amis

- Un défi est entre amis à moins que son créateur l'ouvre : rien n'est montré aux autres membres sans ce choix.
- Chaque ami est invité une fois, par un seul `MemberInvited` : la bibliothèque ne relance jamais un ami qui n'a pas
  répondu.
- Un ami décline sans que le créateur en soit avisé : le défi montre qui participe.
- Un créateur n'annule pas un défi une fois qu'une preuve est envoyée : les participants gardent leur chance d'obtenir
  le lot.
