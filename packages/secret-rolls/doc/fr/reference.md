<!-- phil-dev source-sha256=b9c87798dc50a6d8573706a0bf1aaa6ac498980618bde984bb822248cc3f8271 -->
# Référence

| Type | Rôle |
|---|---|
| `SecretRolls` | Lance hors de vue, révèle, clôt une scène, donne le journal et les jets scellés |
| `HiddenRoll` | Un jet scellé : son ouverture, qui l'a lancé et quand ; écrit et relu en valeurs simples |
| `RollOpening` | Tout ce qu'un jet est : identifiant, scène, position, expression, arrondi, germe, résultat et contexte |
| `RollCommitment` | Le SHA-256 d'une ouverture, comparé en temps constant |
| `RollVerifier` | Vérifie une ouverture avec son engagement et la relance à partir de son germe |
| `Journal\SceneJournal` | Le journal public d'une scène : son état, ses jets retenus, sa vérification et son export JSON |
| `Journal\JournalEntry` | Une entrée du journal, chaînée à la précédente par son empreinte |
| `Journal\EntryKind` | `Committed`, `Revealed` ou `Closed` |
| `Contract\SecretRollStore` | Où les journaux et les jets scellés sont gardés |
| `Contract\JournalPublisher` | Informé de chaque entrée ajoutée |
| `Store\InMemorySecretRollStore` | Le dépôt de stockage en mémoire, pour les tests et les scripts |
| `Store\NoPublisher` | Le publicateur qui ne pousse rien |
| `Exception\SecretRollsException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidSecretRoll` | Une valeur ou un document que la bibliothèque refuse |
| `Exception\SecretRollRefused` | Une étape que le journal d'une scène n'autorise pas |
| `Exception\JournalConflict` | Deux écrivains ont ajouté à un journal en même temps |

`Encoding` et `Instant` sont internes : les vérifications, le JSON canonique et la seule façon dont la bibliothèque
écrit une heure.
