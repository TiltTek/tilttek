<!-- phil-dev source-sha256=0127af8303d01ea05d06c42be8893ed47d686a7d8cf9ee748e9603e00d97e057 -->
# Effacement

`Quests::export($member)` lit ce que les quêtes gardent d'un membre : ses contributions, ses inscriptions, les quêtes
qu'il a accomplies lui-même et ses duels.

`Quests::erase($member)` supprime tout cela, et retourne un `Erasure` avec le décompte de chacun :

- **les contributions du membre** : la progression d'une équipe ou de la communauté recule de ce que son activité a
  apporté ;
- **ses inscriptions**, sous n'importe quelle équipe ;
- **les quêtes qu'il a accomplies lui-même**, y compris les duels gagnés ;
- **ses duels**, comme auteur du défi ou comme membre défié : le duel de l'autre côté disparaît avec eux.

Ce qu'une équipe ou la communauté a accompli reste dans le journal des quêtes accomplies : cela n'appartient pas au
membre, et les récompenses ont été données. Le journal d'activité est effacé par `tilttek/activity`.
