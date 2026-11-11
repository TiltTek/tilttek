<!-- phil-dev source-sha256=29b98134a0d89591cb1dfb346bdded77b8defacba012cd0529b28d47a9e4bdff -->
# Commandes

| Commande | Fait |
|---|---|
| `tilttek:collections:daily [--from=Y-m-d] [--until=Y-m-d] [--regenerate]` | Génère le calendrier de l'objet du jour — trente jours à partir d'aujourd'hui par défaut — et affiche l'objet de chaque jour et pourquoi ; un jour déjà généré garde son objet sauf avec `--regenerate`, un jour choisi le garde toujours |
| `tilttek:collections:grant <member> <item> --key=<key> [--source=granted] [--bound]` | Remet à un membre un exemplaire sous une clé, une seule fois |
| `tilttek:collections:album <member>` | Affiche la progression de chaque ensemble et les objets que possède le membre, avec leurs doubles |

Un membre, un objet, une clé, une source ou un jour erroné affiche une erreur et renvoie 1.
