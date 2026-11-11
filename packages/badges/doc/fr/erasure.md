<!-- phil-dev source-sha256=5a44d9bff9f0a48616b10d2aed4d76ba03870d3e19361935f80e2730d05ad6e0 -->
# Effacement

`Badges::erase($member)` supprime tout ce que la bibliothèque garde sur le membre et renvoie une `Erasure` :

| Champ | Ce qui a été supprimé |
|---|---|
| `awards` | Ses attributions, à la main ou gagnées |
| `contributions` | Ce que son activité a apporté aux critères des badges |
| `grants` | Les consentements qu'il a donnés à d'autres applications |
| `profile` | Si un profil — les badges montrés, la vitrine, le titre — a été supprimé |

L'activité du membre elle-même appartient à `tilttek/activity`, qui l'efface par son journal. Une attestation que
l'application a délivrée pour un badge appartient au membre : elle reste vérifiable là où il la garde.
