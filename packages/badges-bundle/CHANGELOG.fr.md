<!-- phil-dev source-sha256=7734f7f429297fd31aa7ced139470cae7dc178e93e0d873fb43f1e976a7d5ae3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/badges-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.2-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.3.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/translation-contracts` 3.8.

## 0.3.0-alpha - 2026-11-15

Le retour de `BadgeCredentials::issue()` change.

### Modifié

- `BadgeCredentials::issue()` renvoie le `Phil\OpenBadgesBundle\Application\SecuredCredential` de
  `phil/open-badges-bundle` : son contenu, un JSON Web Token compact ou le JSON du document avec sa preuve, son type de
  média et son nom de fichier. `BadgeCredentials::mediaType()` est retirée : le type de média est celui de
  l'attestation.

### Corrigé

- `BadgeCredentials::issue()` émet l'attribution du badge qui porte le numéro le plus élevé, plutôt que la dernière
  attribution par moment : deux attributions d'un badge répétable qui partagent un moment, ou un badge attribué à la
  main avec un moment antérieur à une attribution de l'activité, donnent maintenant l'attestation de la dernière
  attribution que le membre a gagnée.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `DbalAwardStore` implémente les avis d'`AwardStore` : la colonne `notice` de `tilttek_badges_award` contient `awarded`
  pour une attribution dont l'événement est encore dû et `revoked` pour une attribution retirée dont l'événement est
  encore dû. Une table existante reçoit la colonne par la prochaine migration de l'application.
- `DbalAwardStore::withdraw()` marque l'attribution comme retirée en une seule instruction conditionnelle, que son
  propre événement ait été distribué ou non : une attribution retirée pendant qu'un autre processus distribue son
  `BadgeAwarded` laisse toujours un `BadgeRevoked` dû, plutôt que d'être supprimée sans événement à distribuer.
- `BadgeAwarded` et `BadgeRevoked` sont distribués au moins une fois : un écouteur qui a échoué est rappelé quand
  l'entrée est remise de nouveau à la projection, avant la prochaine activité du membre, ou par
  `BadgeProjection::deliver()`. Les écouteurs doivent être idempotents.
- Le bundle requiert `tilttek/badges` `^0.2@dev`, dont `DbalAwardStore` implémente les avis d'`AwardStore`.

### Corrigé

- `BadgesPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et `PersonalDataEraser`, les
  interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent : l'exportation et
  l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore` et `DbalGrantStore`, et `Rows` : les quatre stockages de
  `tilttek/badges` sur Doctrine DBAL, chaque ligne insérée une seule fois par une instruction qui n'écrit rien quand
  elle est déjà là — `ON CONFLICT DO NOTHING`, `INSERT IGNORE`, `INSERT OR IGNORE` —, les dates en UTC.
- `BadgesTables` et `BadgesSchemaListener` : les tables `tilttek_badges_contribution`, `tilttek_badges_award`,
  `tilttek_badges_profile` et `tilttek_badges_grant`, ajoutées au schéma que l'ORM génère pour leur connexion.
- `DispatchingObserver`, `BadgeAwarded` et `BadgeRevoked` : les événements distribués quand l'activité fait gagner un
  badge ou qu'une annulation en retire un.
- `BadgeCredentials` : la dernière attribution du badge d'un membre émise comme attestation Open Badges 3.0 de
  `phil/open-badges`, sa réalisation identifiée par l'URL configurée et traduite dans la langue du membre, sécurisée —
  comme JSON Web Token ou avec une preuve Data Integrity — par l'émetteur que configure `phil/open-badges-bundle`, et le
  type de média de ce qu'elle renvoie.
- `TilttekBadgesBundle` et sa configuration `tilttek_badges` : le stockage, Doctrine ou mémoire, la connexion, le fuseau
  horaire des séries et des périodes, les badges et leurs critères — vérifiés à la compilation du conteneur —, le nom de
  la projection, la taille d'une vitrine, la borne des attributions qu'apporte une activité, le domaine de traduction
  des textes des badges, l'URL de réalisation des attestations Open Badges — refusée quand `phil/open-badges-bundle`
  n'est pas enregistré —, et le type de membre que représente une personne concernée. Les stockages en mémoire sont
  étiquetés `kernel.reset`. La projection oublie sa reprise quand le noyau se réinitialise.
- `BadgesServices` : les stockages, le catalogue, l'évaluateur avec les `Measures` de l'application quand elle les
  définit, `Badges`, et la projection étiquetée `tilttek_activity.projection`.
- `BadgesExtension` et les gabarits `@TilttekBadges/showcase.html.twig` et `@TilttekBadges/overview.html.twig` : les
  fonctions Twig `tilttek_badges_showcase`, `tilttek_badges_overview` et `tilttek_badges_domain`, le titre, la vitrine
  et les badges montrés, et la progression vers chaque badge, traduits dans le domaine configuré.
- `AwardCommand`, `RevokeCommand` et `ListCommand` : `tilttek:badges:award`, `tilttek:badges:revoke` et
  `tilttek:badges:list`.
- `BadgesPrivacy` : le module `tilttek_badges` de `phil/privacy-bundle`, qui exporte les attributions, le profil et les
  consentements d'un membre, et les efface avec les contributions de son activité.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
