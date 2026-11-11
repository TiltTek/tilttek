<!-- phil-dev source-sha256=fe51cd8448317b4828f19150ba8ce6011638d21b3097d8e261d25b013db02fe5 -->
# L'objet du jour

```yaml
tilttek_collections:
    timezone: America/Toronto
    daily:
        enabled: true
        verbs: ['page.viewed', 'member.signed_in']
        sets: [crew]
        dates: { '10-31': pumpkin }
        computed: [{ rule: easter, item: egg }]
```

Avec `enabled`, la projection `collections.daily` est étiquetée pour `tilttek/activity-bundle` : la première activité
d'un membre dans une journée, parmi les `verbs`, lui remet l'objet de ce jour. Faites-la rattraper et reconstruisez-la
avec les commandes du journal :

```sh
bin/console tilttek:activity:catch-up collections.daily
bin/console tilttek:activity:rebuild collections.daily --since=2026-11-01
```

L'objet d'un jour est celui qui est gardé pour lui, sinon celui de la première date fixe, sinon celui de la première
date calculée — `computed` prend les règles de `phil/almanac` et le nombre de jours que reste l'objet —, sinon un
tirage pondéré par la rareté parmi les objets de `sets`, à partir du jour et du `salt` secret.

`tilttek:collections:daily` génère le calendrier à l'avance et l'affiche. Corrigez un jour avec
`DailyCalendar::choose(day, item)` et rendez-le au calendrier avec `release(day)`. Dans un gabarit,
`tilttek_collections_today()` renvoie l'objet d'aujourd'hui et `@TilttekCollections/daily.html.twig` l'affiche.
