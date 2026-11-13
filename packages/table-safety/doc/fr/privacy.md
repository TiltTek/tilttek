<!-- phil-dev source-sha256=961d0d0ef01ca3161446ae06f813dc565ce842187bd8e214381c3cd08a0fc4a7 -->
# Vie privée

Les réponses des outils de sécurité disent ce qui blesse une personne. La bibliothèque en garde le minimum, pour leur
table seulement, et ne laisse rien de ce qu'elle résume dire qui a donné quelle réponse.

## Ce qui reste anonyme

- L'entente d'une table, son pacte, son décompte de points d'étape et le sommaire de ses étoiles et de ses souhaits
  comptent et trient les réponses ; aucun ne porte la référence d'un participant.
- Un signal anonyme n'a d'auteur nulle part : ni dans le stockage, ni dans ce que l'observateur diffuse.
- Un signal nominatif n'existe qu'à une table qui a choisi des signaux nominatifs ; une réponse à un point d'étape est
  toujours anonyme.

## Ce qui est gardé

| Gardé | Où | Avec la référence du participant |
|---|---|---|
| Lignes et voiles, liste, réponses à la séance zéro | `ParticipationStore` | oui, pour qu'il puisse les changer |
| Étoiles et souhaits de chaque séance | `FeedbackStore` | oui, pour qu'il puisse les changer |
| Signaux | `SignalStore` | seulement pour un signal nominatif |

Un participant qui quitte une table retire ses réponses de celle-ci avec `TableSafety::leave()`. Les signaux servent
pendant la partie : une application les purge après un temps avec `SignalStore::purge()`.

## Export et effacement

`TableSafety::export()` donne tout ce qui est gardé sur un participant : ses réponses à chaque table, les signaux
nominatifs qu'il a levés, et ses étoiles et ses souhaits. `TableSafety::erase()` supprime ses réponses et ses étoiles et
ses souhaits, et retire son nom des signaux qu'il a levés, qui restent à sa table comme signaux anonymes ; l'exécuter de
nouveau ne change rien. `tilttek/table-safety-bundle` branche les deux sur `phil/privacy-bundle`.
