<!-- phil-dev source-sha256=6f923440878d09dab1b41501feeef0414380f42c24b5cb9a29d23d9fab837d14 -->
# Écrire un stockage

`TableSafety` garde ses données par quatre contrats, sous `Tilttek\TableSafety\Contract`. La bibliothèque fournit un
stockage en mémoire de chacun, pour les tests ; `tilttek/table-safety-bundle` fournit un stockage Doctrine DBAL de
chacun.

| Contrat | Garde | Stockage en mémoire |
|---|---|---|
| `TableStore` | les `TableSettings` de chaque table | `InMemoryTableStore` |
| `ParticipationStore` | la `Participation` de chaque participant à chaque table | `InMemoryParticipationStore` |
| `SignalStore` | les `Signal` de chaque table | `InMemorySignalStore` |
| `FeedbackStore` | le `Feedback` de chaque participant pour chaque séance | `InMemoryFeedbackStore` |

Ce que fait chaque implémentation :

- `save()` remplace l'enregistrement de la même table et du même participant — et de la même séance, pour les étoiles et
  les souhaits.
- Un `ParticipationStore` garde `Participation::$firstAnsweredAt` et `$updatedAt` tels qu'on les lui donne et les rend
  tous deux : les sommaires montrés aux gens prennent les participations dans l'ordre de leur première réponse.
- `ofTable()` et `ofSession()` listent les enregistrements dans un ordre qui ne dit pas qui a répondu en premier, comme
  l'ordre des références des participants.
- `SignalStore::recent()` liste les signaux dans l'ordre où ils ont été levés, les derniers dans la limite, ou ceux qui
  ont été levés strictement après un moment ; deux signaux de la même seconde gardent leur ordre.
- `SignalStore::lastCheckIn()` donne le dernier point d'étape de la table suivi de chaque réponse levée après lui,
  dans le même ordre, sans limite : le décompte d'un point d'étape a besoin de toutes, quel que soit le nombre de
  signaux levés depuis. Il ne donne rien pour une table sans point d'étape.
- `SignalStore::acknowledge()` marque un signal une seule fois et le retourne, ou retourne null.
- `eraseParticipant()` et `forgetParticipant()` retournent le nombre d'enregistrements qu'elles ont changés, et ne
  changent rien la deuxième fois.
- Un signal anonyme est stocké sans auteur.

Les tests de la bibliothèque tiennent ces comportements dans `tests/Unit/Store/StoresTestCase.php` : un stockage qui les
réussit réussit ceux de `TableSafety`.
