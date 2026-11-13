<!-- phil-dev source-sha256=fcf38addc69690b032abc1902b52d38c12cbd0743d39201bb93e8920924211fc -->
# Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `tilttek:game-engine:setup [--dump-sql]` | Crée les tables des journaux sur leur connexion quand elles manquent, ou en montre le SQL |
| `tilttek:game-engine:verify <table> [--commitment=…]` | Rejoue le journal d'une table, contre l'engagement sur son germe quand on en donne un |

`setup` est enregistrée avec le magasin `doctrine` ; elle compare le schéma de la connexion avec les tables des
journaux et n'exécute que ce qui manque, de sorte que l'exécuter deux fois ne change rien.

`verify` charge le journal d'une table, le rejoue avec son jeu, et montre le jeu et sa version, le nombre de coups, la
phase, l'issue et l'engagement sur le germe. Quand on lui donne l'engagement que les joueurs ont vu, elle vérifie
d'abord que le germe du journal est celui de l'engagement. Un journal qui ne se rejoue plus, ou dont le germe n'est pas
celui de l'engagement, fait échouer la commande.
