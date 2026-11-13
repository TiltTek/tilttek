<!-- phil-dev source-sha256=c3e7cdbc4c2dec4ff070b1ce49f08baeaa0aa450cc20240768a16132d9ee150d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les saisons dans une base de données | Implémentez `Contract\SeasonCatalogue`, en construisant chaque `Season` depuis vos lignes — `SeasonReader::season()` lit un tableau |
| Lire ce qui est en cours depuis un autre paquet | Dépendez de `Contract\ActiveContent`, jamais de `SeasonalEvents` |
| Répondre pour un autre moment | Donnez à `SeasonalEvents` une autre horloge PSR-20, ou passez le moment à chaque appel |
| Ouvrir une quête avec une saison | Donnez à une quête de `tilttek/quests` un `Bridge\Quests\SeasonQuestSchedule` |
| Lister les objets qu'apporte une saison | Utilisez `Bridge\Collections\SeasonalItems` sur votre catalogue de `tilttek/collections` |
