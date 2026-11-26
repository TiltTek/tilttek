<!-- phil-dev source-sha256=860c8f5daed63bb861816d8d4f4d08435b8a16d2397ce77ee644774a7cd487cf -->
# À la table

`SessionPreparer` est le service des préparations. Il lit dans le dépôt, modifie, garde, puis dit à l'observateur ce qui
s'est passé, chaque fait une fois. Il ne garde aucun état entre les appels.

Chaque appel qui modifie une préparation prend le membre qui le demande, `$by` : seul le maître de jeu de la préparation
la modifie, et tout autre est refusé par `PreparationRefused`. Un appel qui ne change rien — un secret déjà révélé
marqué révélé, une préparation prête marquée prête — retourne la préparation telle qu'elle est, ne garde rien et
n'annonce rien.

## Créer et modifier

`create()` garde une nouvelle préparation : un brouillon à la version 0, dont aucune préparation n'a l'identifiant. Ses
parties partent comme elles sont écrites — chaque secret caché, chaque indice non trouvé, chaque scène planifiée,
chaque élément non coché — quoi que dise la préparation donnée.

`change($preparation, $by)` remplace les parties d'une préparation par un changement fait sur la version lue. Le maître
de jeu peut la remettre à un autre membre ; elle reste dans sa campagne. La séance, le statut et les faits de la table
restent ce qu'ils étaient, et une partie ajoutée part comme dans `create()` : chacun se modifie par sa propre méthode,
pour que l'observateur en soit informé. Un changement fait sur une version qui n'est plus celle du dépôt lève
`PreparationConflict` : relisez la préparation et refaites le changement.

```php
$preparation = $preparer->preparation('prep-12');

$preparer->change(
    $preparation
        ->withScene(new Scene('belfry', 'The belfry', characters: ['mara']))
        ->moveScene('belfry', 'docks')
        ->withoutNote('old-idea'),
    'member-1',
);
```

## Lier à une séance

`link($id, $session, $by)` lie la préparation à une séance par son identifiant, ou à aucune avec null. Une préparation
peut être écrite avant que sa séance soit planifiée, et déplacée à une autre séance quand la date change.
`PreparationQuery` lit les préparations d'une séance.

## Les faits de la table

| Méthode | Marque |
|---|---|
| `markScene($id, $scene, $status, $by)` | une scène jouée, sautée, ou de nouveau planifiée |
| `markSecret($id, $secret, $revealed, $by)` | un secret révélé, ou de nouveau caché |
| `markClue($id, $secret, $clue, $found, $by)` | un indice trouvé, ou non ; le secret reste comme il est |
| `markItem($id, $item, $checked, $by)` | un élément de la liste de contrôle coché, ou non |

Une clé qu'aucune partie n'a lève `InvalidPreparation`, même quand la valeur demandée est le départ de la partie.

## Prête, fermée, rouverte

Une préparation est un `Draft` tant qu'elle est écrite. `ready()` la marque `Ready` une fois chaque élément exigé de sa
liste de contrôle coché ; sinon elle est refusée, et le message liste les clés des éléments manquants. `close()` la
marque `Closed`, depuis un brouillon ou prête, une fois la séance terminée : rien ne modifie une préparation fermée
sauf `reopen()`, qui la ramène à un brouillon, et `delete()`. `reopen()` ramène aussi une préparation prête à un
brouillon.

## Reporter

`carryOver($from, $into, $by)` reporte dans une préparation ce qu'une autre de la même campagne a laissé :

- ses secrets non révélés, avec leurs indices tels qu'ils sont ;
- ses scènes encore planifiées, avec les personnages et les lieux qu'elles mettent en scène.

Une partie dont la préparation contient déjà la clé est laissée de côté, pour que reporter de nouveau n'ajoute que ce
qui est nouveau ; quand rien n'est nouveau, la préparation est retournée telle qu'elle est. Le maître de jeu est celui
des deux ; la préparation d'où l'on reporte peut être fermée, celle où l'on reporte ne le peut pas. Les parties
reportées gardent leur ordre, après celles de la préparation.

## Lire et supprimer

`preparation($id)` donne une préparation, ou lève `PreparationNotFound`. `preparations($query, $limit)` donne les
préparations qu'une `PreparationQuery` retient, de 1 à 1000 au plus, par identifiant :

```php
use Tilttek\SessionPrep\PreparationQuery;
use Tilttek\SessionPrep\PreparationStatus;

$preparer->preparations(new PreparationQuery(session: 'session-31'));
$preparer->preparations(new PreparationQuery(campaign: 'campaign-7', status: PreparationStatus::Ready), 20);
```

Un critère laissé null retient toutes les préparations. Une application montre les préparations d'un maître de jeu à
lui seul : le service vérifie qui modifie une préparation, et l'application vérifie qui en lit une.

`delete($id, $by)` supprime une préparation, quel que soit son statut.
