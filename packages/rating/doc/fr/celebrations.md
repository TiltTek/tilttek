<!-- phil-dev source-sha256=735d11891802c31d2c839ad712a89b491c1daec623a3d270c174b73b4b9c00ea -->
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
