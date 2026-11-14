<!-- phil-dev source-sha256=dfb03e6a346e6eb289e5672b76892af1d86dc3c6c5cd684f6ae34ead80ef7545 -->
# Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `tilttek:game-engine:setup [--dump-sql]` | Crée les tables des journaux sur leur connexion quand elles manquent, ou en montre le SQL |
| `tilttek:game-engine:verify <table> [--commitment=…]` | Rejoue le journal d'une table, contre l'engagement sur son germe quand on en donne un |

`setup` est enregistrée avec le magasin `doctrine` ; elle compare le schéma de la connexion avec les tables des
journaux et n'exécute que ce qui manque, de sorte que l'exécuter deux fois ne change rien.

`verify` charge le journal d'une table par le `Tables` de la bibliothèque, partie en cours comprise, le rejoue avec son
jeu, et montre le jeu et sa version, le nombre de coups, la phase, l'issue et l'engagement sur le germe, jamais le germe
lui-même. Quand on lui donne l'engagement que les joueurs ont vu, elle vérifie d'abord que le germe du journal est celui
de l'engagement. Un journal qui ne se rejoue plus, ou dont le germe n'est pas celui de l'engagement, fait échouer la
commande.
