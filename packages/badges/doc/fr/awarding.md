<!-- phil-dev source-sha256=5f666c4a6731a1eb81af298401ec46d3ea773dfceca3f20828d75aa4e0090b12 -->
# Attribuer

## La projection

`BadgeProjection` est une projection de `tilttek/activity` : l'application l'enregistre avec ses autres projections, et
le `Projector` du journal lui remet les entrées qu'elle n'a pas vues, en direct ou par une tâche planifiée.

Pour chaque activité enregistrée, chaque feuille de chaque badge qui l'accepte reçoit une contribution — une seule fois
par badge, chemin et activité, de sorte qu'une entrée remise deux fois n'apporte rien de plus —, et les badges touchés
sont évalués pour le membre :

- un badge dont la cible est atteinte est attribué. Son `Award` porte le numéro 1 et la date de l'activité qui l'a
  atteinte, et sa cause est la clé d'idempotence de l'activité ;
- un badge répétable est attribué de nouveau chaque fois que sa cible est de nouveau atteinte, numéroté 2, 3… ; une
  activité en apporte au plus `BadgeProjection::MAX_AWARDS_PER_ACTIVITY` attributions, 10, ou la borne donnée à la
  projection, et les attributions encore dues viennent avec les activités suivantes du membre pour ce badge, de sorte
  qu'une seule quantité énorme ne peut ni remplir le magasin des attributions ni inonder l'observateur ;
- un badge qui n'est pas répétable et qui est déjà détenu à la main est laissé tel quel.

Pour une annulation, les contributions de l'activité sont retirées et les badges qu'elles touchaient sont évalués de
nouveau : un badge révocable dont la cible n'est plus atteinte est retiré, ses numéros les plus élevés d'abord. Un badge
qui n'est pas révocable est gardé.

## Progression

`Badges::progress($member, 'commenter')` lit une `Progress` : `current`, `target`, `shown()` — la valeur actuelle,
plafonnée à la cible, *7 sur 10* —, `isReached()` et `times()`. `Badges::overview($member)` donne un `Standing` par
badge du catalogue, dans son ordre, avec sa progression et le nombre de fois où le membre le détient. Un badge caché
qu'il n'a pas gagné est omis.

## À la main

`Badges::award($member, 'organiser', 'Ran the convention')` attribue un badge à la main, quel que soit son critère, daté
par l'horloge. Un badge qui n'est pas répétable et qui est déjà détenu n'est pas attribué de nouveau.
`Badges::revoke()` retire les attributions faites à la main. Celles gagnées par l'activité restent, car l'activité les
ferait gagner de nouveau.

Deux attributions à la main faites au même moment lisent les mêmes attributions et choisissent le même numéro ; le
magasin en garde une. L'autre relit les attributions. Pour un badge qui n'est pas répétable, elle renvoie l'attribution
que le magasin a gardée, de sorte que les deux appelants obtiennent la même. Pour un badge répétable, les deux
attributions sont voulues : elle prend le numéro suivant, au plus `Badges::AWARD_ATTEMPTS` fois, 5, et lève
`AwardConflict` quand d'autres attributions ont pris le numéro à chaque tentative.

## Observateurs

Un `AwardObserver` passé à la projection est averti par `awarded()` et `revoked()`, pour aviser le membre ou montrer le
badge sur-le-champ. Une attribution faite à la main est un acte propre à l'application et n'est pas annoncée.

L'observateur est averti au moins une fois. Une attribution gagnée par l'activité est gardée avec son avis dû, et le
magasin la note remise une fois l'observateur averti ; une attribution retirée quitte sur-le-champ les attributions du
membre, mais reste dans le magasin jusqu'à ce que l'observateur soit averti de son retrait, et est supprimée alors.
Quand l'observateur échoue, l'avis reste dû, et il est donné :

- quand la même entrée est remise de nouveau à la projection : la requête répétée sous la même clé d'idempotence, dont
  le journal remet l'entrée aux projections encore en retard sur elle, ou le rattrapage suivant ;
- avant que la prochaine activité ou annulation du membre soit évaluée ;
- par `BadgeProjection::deliver($member)`, qui donne tous les avis dus au membre, les retraits d'abord, et renvoie
  combien il en a réglé. L'avis d'un badge que le catalogue ne contient plus est abandonné sans l'observateur.

Une attribution retirée avant que son propre avis soit donné n'appelle aucun avis. Comme un avis peut être redonné après
un échec, un observateur doit être idempotent : il reconnaît une attribution à son badge, son membre et son numéro, et
ne fait rien pour un avis sur lequel il a déjà agi.

## Quand une règle change

`Projector::rebuild($projection, $since)` oublie les contributions des activités à partir de `since` — toutes quand
`since` est null — et les attributions qui en découlent, puis rejoue le journal sous le nouveau catalogue. Une
attribution gagnée de nouveau avec le même badge, le même membre et le même numéro pendant la reconstruction n'est pas
annoncée de nouveau, sauf si son premier avis n'a jamais été donné. Une attribution que les nouvelles règles ne
donnent plus est retirée sans être annoncée. Les attributions faites à la main sont gardées. Une `Measure` est relue
telle qu'elle est au moment de la reconstruction.
