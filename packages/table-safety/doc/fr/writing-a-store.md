<!-- phil-dev source-sha256=ea8d48a1ca395f8af8d3c1f5c9ba0677f79e5756c47788ae165269b19e5ef2cd -->
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
- `ofTable()` et `ofSession()` listent les enregistrements dans un ordre qui ne dit pas qui a répondu en premier, comme
  l'ordre des références des participants.
- `SignalStore::recent()` liste les signaux dans l'ordre où ils ont été levés, les derniers dans la limite, ou ceux qui
  ont été levés strictement après un moment ; deux signaux de la même seconde gardent leur ordre.
- `SignalStore::acknowledge()` marque un signal une seule fois et le retourne, ou retourne null.
- `eraseParticipant()` et `forgetParticipant()` retournent le nombre d'enregistrements qu'elles ont changés, et ne
  changent rien la deuxième fois.
- Un signal anonyme est stocké sans auteur.

Les tests de la bibliothèque tiennent ces comportements dans `tests/Unit/Store/StoresTestCase.php` : un stockage qui les
réussit réussit ceux de `TableSafety`.
