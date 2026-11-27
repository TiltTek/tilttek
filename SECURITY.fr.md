<!-- phil-dev source-sha256=0a13018c08bb6eda83e84abd996069a82912ad161555ae397a13c266bbcf43f6 -->
# Politique de sécurité

[English](SECURITY.md)

## Signaler une vulnérabilité

**Ne signalez pas une vulnérabilité dans un ticket, une demande de tirage ou une discussion publics.** Écrivez plutôt à
[security@ph-il.ca](mailto:security@ph-il.ca), avec :

- une description de la vulnérabilité et de ce qu'un attaquant peut en faire ;
- le paquet (`tilttek/<name>`) et la version ou le commit touchés ;
- les étapes pour la reproduire, ou une preuve de concept, si vous en avez une ;
- tout ce qui peut aider d'autre : des journaux, la configuration en cause, un correctif suggéré.

Vous recevrez un accusé de réception dans les 48 heures, puis des nouvelles du correctif à mesure qu'il avance. Gardez
la vulnérabilité confidentielle jusqu'à la publication d'une version corrigée ; nous conviendrons avec vous de la date
de divulgation et vous mentionnerons dans l'avis, à moins que vous préfériez ne pas être nommé.

## Comment une vulnérabilité est corrigée

- Un correctif est visé dans les 7 à 14 jours qui suivent l'accusé de réception, plus tôt pour une vulnérabilité grave.
- Le correctif est publié dans une nouvelle version du paquet, et annoncé dans ses notes de version et dans un avis de
  sécurité GitHub sur ce monodépôt.
- Un commit qui corrige une vulnérabilité commence par `security:`, pour que ceux qui ne peuvent pas mettre à niveau
  tout de suite puissent le trouver et l'appliquer.

## Versions prises en charge

Seule la dernière version de chaque paquet, sur sa branche principale, reçoit les correctifs de sécurité. Les paquets
sont en `0.x` : passez à la dernière version pour obtenir un correctif, ou appliquez vous-même son commit `security:`.

## Dépendances

Une vulnérabilité dans une dépendance se corrige en relevant la contrainte de la dépendance dès qu'une version corrigée
existe. Si vous trouvez une dépendance d'un paquet visée par un avis connu (`composer audit` les signale), vous pouvez
la signaler de la même façon.

Le verrou du monodépôt est audité chaque jour par le workflow `security-audit`
(`.github/workflows/security-audit.yml`) : `composer audit` (Composer 2.11.2 ou plus récent) et `osv-scanner` lisent
`composer.lock`, et chaque avis qu'ils trouvent ouvre un ticket public étiqueté `security-audit`, intitulé
`Security alert: <advisory> in <package>`. Les avis sont déjà publics : ces tickets sont donc l'exception à la règle
ci-dessus. Un avis trouvé par les deux outils ouvre un seul ticket, et un avis déjà signalé n'en ouvre aucun, que son
ticket soit ouvert ou fermé : rouvrez le ticket fermé pour le suivre de nouveau. Le workflow peut aussi être lancé à la
main depuis l'onglet Actions. Pour vérifier un changement avant sa fusion, lancez `composer audit --locked` en local
avec Composer 2.11.2 ou plus récent.
