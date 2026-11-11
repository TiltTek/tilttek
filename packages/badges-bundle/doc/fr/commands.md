<!-- phil-dev source-sha256=643f582ab6c58f81dee1db3dfc8c3508240bcb7a47881a3b66b6a3c80a9e49f0 -->
# Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `tilttek:badges:award member:44 organiser --reason="Ran the convention"` | Attribue un badge à la main ; un badge qui n'est pas répétable et qui est déjà détenu n'est pas attribué de nouveau |
| `tilttek:badges:revoke member:44 organiser` | Retire les attributions du badge faites à la main |
| `tilttek:badges:list member:44` | Énumère l'état du membre pour chaque badge : attributions et progression ; les badges cachés non gagnés sont omis |

Une référence de membre ou un code de badge invalide termine la commande sur une erreur et un code de sortie non nul.
