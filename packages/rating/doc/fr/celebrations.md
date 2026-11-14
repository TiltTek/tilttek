<!-- phil-dev source-sha256=570ea7d974a836613e1fda88399687eb5e45b47f43e3616c0bb2b54c9e4f8bd9 -->
# Célébrations

Une cote qui monte est un moment à célébrer, et une nouvelle cote record l'est encore plus. La bibliothèque dit quand ;
la page le joue, avec le composant de célébration du kit PHIL UX ou autre chose.

## Les événements

| Événement | Quand | Porte |
|---|---|---|
| `Event\RatingChanged` | Un résultat coté est gardé, pour chacun de ses membres | `game`, `member`, `result` |
| `Event\PeakReached` | Après `RatingChanged`, quand le score du membre dépasse le plus haut qu'il a atteint, une fois qu'il a joué les parties cotées que demande `celebrateAfter` | `game`, `member`, `result` |

Chaque événement ne porte que des identifiants, jamais une cote : un écouteur lit la cote dans le dépôt de stockage, et
ne la montre qu'au membre, sauf si le membre l'a rendue publique. Un résultat amical et une relecture n'envoient rien.
`celebrateAfter`, à 5 par défaut, évite que les premières parties d'un membre — celles où une cote bouge le plus — soient
célébrées chaque fois.

Un événement est envoyé au moins une fois : celui dont l'écouteur a échoué est envoyé de nouveau quand le résultat est
enregistré de nouveau ou remis, voir [Enregistrer des résultats](recording-results.md#les-événements). Un écouteur qui
joue une célébration ou donne quelque chose le fait une fois par `key()`.

## Avec le kit PHIL UX

Un écouteur garde le moment pour la prochaine page du membre — dans la session, ou en notification —, et la page joue
une fois la célébration du kit :

```twig
{% if rating_peak %}
    <twig:Celebration message="{{ 'rating.peak'|trans }}" effect="fireworks" :autoplay="true" />
{% endif %}
```

La bibliothèque n'ajoute aucun composant à elle et ne dépend d'aucun kit : les mots et le moment sont ceux de
l'application.

## Accessibilité

La célébration du kit dit son message dans une région vivante polie, ne dessine rien là où le mouvement est réduit, ne
joue aucun son et dure quelques secondes au plus. La célébration d'une cote n'est montrée qu'à son membre ; une page
publique ne célèbre rien d'une cote privée.
