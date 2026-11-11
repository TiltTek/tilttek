<!-- phil-dev source-sha256=9063112a290ac93e6b948fed95369b7b9c8c8d43efd5de536424626b496a3c45 -->
# Gabarits

## Fonctions Twig

| Fonction | Retourne |
|---|---|
| `tilttek_progression_standing(subject, track)` | Le `Standing` d'un sujet sur une piste |
| `tilttek_progression_standings(subject)` | Un `Standing` par piste |
| `tilttek_progression_streak(member, streak)` | Le `StreakStatus` de la série d'un membre |
| `tilttek_progression_domain()` | Le domaine de traduction configuré |

Un profil appartient à un membre : ne montrez sa progression aux autres que s'il l'a choisi.

## La barre de progression

```twig
{{ include('@TilttekProgression/bar.html.twig', {standing: tilttek_progression_standing('member:44', 'player')}) }}
```

Elle produit le rendu du nom de la piste (`tracks.<name>` dans le domaine de traduction), du niveau, d'un élément
`<progress>` du début du niveau jusqu'au suivant (plein au dernier niveau) avec son pourcentage en texte, de
l'expérience qui manque encore et de la saison en cours. Les classes commencent par `tilttek-progression-bar` ; le
bundle ne fournit aucun CSS.

## La série

```twig
{{ include('@TilttekProgression/streak.html.twig', {streak: tilttek_progression_streak('member:44', 'daily-play')}) }}
```

Elle produit le rendu du nom de la série (`streaks.<name>`), des séries en cours et meilleure et de la grâce qui reste ;
la classe `is-alive` marque une série en cours.

## Traductions

Le bundle fournit `tilttek_progression.en.yaml` et `tilttek_progression.fr.yaml` avec `level`, `last_level`,
`missing`, `season` et `streak.current`, `streak.best`, `streak.grace`. L'application ajoute `tracks.<name>` et
`streaks.<name>`, et remplace n'importe quelle clé dans ses propres traductions.
