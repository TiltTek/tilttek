<!-- phil-dev source-sha256=3dfd91d2b8bbb1f57da0d42e30a9515e412c8007e8290acb6c8d4fdbe948864d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Où |
|---|---|---|
| Documents du format | Ses systèmes et leurs localisations, en YAML ou en JSON | [Le format](format.md) |
| `Import\ImportProfile`, `Import\TableMapping` | Un profil qui lit un extrait de forme différente, ou une autre source ouverte | [Importer des sources ouvertes](importing.md#un-profil-à-vous) |
| `Provenance` | La source, la licence et l'attribution de ce qu'elle importe ou écrit | [Sources et attribution](attribution.md) |
| Paquets de données | Une source ouverte dans une version, déjà importée, requise avec Composer sous le type `tilttek-rpg-data`, que `Package\InstalledDataPackages` charge | [Paquets de données](data-packages.md#charger-les-paquets-installés) |
| La méthode des nombres de difficulté | Des nombres propres sous une méthode que lisent ses outils, à côté de `check`, `level-dc`, `budget` et `level` | [Systèmes et enregistrements](systems-and-records.md#les-nombres-de-difficulté) |
| La limite de `Format\SystemReader` | Le document le plus long qu'il lit, en octets | [Le format](format.md#la-lecture) |

La limite de `Package\DataPackageReader`, le manifeste le plus long qu'il lit, est aussi son argument. `Package\InstalledDataPackages` prend ce lecteur, et les paquets et leurs répertoires par nom à la place de ceux que Composer a installés.

Les enregistrements sont des valeurs que l'application peut aussi construire en code. Les services ne gardent aucun
état propre entre deux appels.
