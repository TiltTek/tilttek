<!-- phil-dev source-sha256=77deace25fcb99f63ce31105ce3e5e73ad5e5871b62ce00ce47d8f8e8f212a49 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, le bundle enregistre le module `tilttek_badges`, à la fois exportateur et
effaceur. La personne concernée 44 est le membre `member:44` — la sorte est `privacy.member_kind`.

**L'export** énumère les attributions du membre, les badges qu'il montre, sa vitrine, son titre et les applications
qu'il a autorisées à lire ses badges ; rien quand le membre n'a rien de cela.

**L'effacement** supprime les attributions du membre, les contributions de son activité, son profil et ses
consentements :

| Table | Effet |
|---|---|
| `tilttek_badges_award` | Supprimée |
| `tilttek_badges_contribution` | Supprimée |
| `tilttek_badges_profile` | Supprimée |
| `tilttek_badges_grant` | Supprimée |

L'activité elle-même est effacée par le module propre à `tilttek/activity-bundle`.
