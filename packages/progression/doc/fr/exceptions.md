<!-- phil-dev source-sha256=c66dfbb2bc7890158a4801dc57090496aa4128f7735cee5c8172d5e4ebcf59cc -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Progression\Exception\ProgressionException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidProgression` | Un nom, une courbe, une règle, un plafond, une saison, un jalon, une grâce, un fuseau horaire ou une date est refusé | `"Player" is not a track name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `UnknownTrack` | Une piste est demandée par un nom qu'aucune ne porte | `No track is named "author".` |
| `UnknownStreak` | Une série est demandée par un nom qu'aucune ne porte | `No streak is named "monthly".` |

Les motifs de verbes et les conditions de contexte d'une règle sont vérifiés par `tilttek/activity`, qui lève sa propre
`InvalidActivity`.
