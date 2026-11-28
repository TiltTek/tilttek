<!-- phil-dev source-sha256=8e0bf2e9d35f735aa6de163bde4fca182c0287ba65b755f6eee416f27e3a5d6b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/rpg-systems` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Translations` : un texte par locale — au plus 64 locales, chaque texte d'au plus 4 000 caractères d'UTF-8 sans
  caractères de contrôle autres que les sauts de ligne et les tabulations —, des locales écrites `fr`, `fr_CA`, `fr-ca`
  ou `es-419` et gardées normalisées, et le texte trouvé pour un lecteur : la locale, sa langue, la locale de repli,
  puis le premier texte.
- `Licence` et `Provenance` : une liste fermée de licences — CC BY 4.0, CC BY 3.0, CC BY-SA 4.0, CC0 1.0, la licence ORC
  et le matériel propre à l'application —, ce que chacune demande et la page de son texte ; le titre, la licence,
  l'attribution, l'auteur et l'adresse d'une source, l'attribution exigée par une licence qui en demande une, l'adresse
  lue par `phil/url`.
- `Attribute`, `DiceExpression` et `Term` : les fiches d'un système, chacune avec une clé en ASCII minuscule à points
  simples, des noms par locale et sa provenance ; les abréviations, les descriptions et les bornes d'un attribut ; un
  jet lu, vérifié et normalisé par `tilttek/dice`, prêt à lancer et écrit dans la notation d'une langue ; les
  descriptions et la catégorie d'un terme.
- `RuleVariant`, `Difficulty`, `DifficultyBand` et `PartyRole` : les variantes de règles qu'une table peut choisir ; des
  nombres de difficulté pour un usage — une méthode, des tranches de la plus facile, chacune avec son propre nombre
  quand elle en a un, et des tables de nombres entiers par libellé sur trois niveaux au plus, jusqu'à 24 tranches et
  10 000 nombres à ±1 000 000 000 près, trouvés par `number()` — ; les rôles qu'un système déclare pour un groupe.
- `GameSystem`, `Systems` et `Localisation` : les noms, la version, la provenance et les fiches d'un système —
  attributs, expressions de dés, termes, variantes de règles, nombres de difficulté et rôles de groupe, au plus 10 000
  en tout —, consultés par clé ou par catégorie dans leur ordre, les sources dont il s'inspire, chacune une fois, et les
  textes d'une langue de plus posés par-dessus avec la source de la traduction, les noms des tranches de difficulté
  compris ; les systèmes d'une application par clé.
- `Format\SystemReader`, `Format\SystemWriter` et `Format\Syntax` : le format de la bibliothèque en YAML ou en JSON —
  `format: tilttek-rpg-systems/1` —, les champs `variants`, `difficulties` et `roles` à côté des attributs, des dés et
  des termes, une source fixée une fois pour un document et indiquée par une fiche là où elle diffère, des textes en
  table de correspondance ou en texte unique dans la locale du document, des documents de localisation posés par-dessus
  leur système, des fichiers chargés en commençant par les systèmes ; un lecteur strict qui refuse un champ inconnu en
  indiquant sa place, le YAML avec objets ou étiquettes, le JSON au-delà d'une profondeur de 64, les documents de plus
  de 2 000 000 d'octets et les listes de plus de 10 000 fiches ; un rédacteur dont les documents se relisent en un
  système égal.
- `Attribution\AttributionRenderer` : les mentions d'un système ou des fiches qu'une page montre, une pour chaque source
  dont la licence demande une attribution, avec les sources des traductions d'une locale, en lignes de texte ou en liste
  HTML échappée dont les licences sont liées avec `rel="license"`.
- `Import\Importer`, `Import\ImportProfile` et `Import\TableMapping` : un extrait d'une source ouverte — des tables de
  fiches en JSON ou en YAML — converti en un système dont chaque fiche porte la source que l'appelant indique, refusé
  quand sa licence n'est pas l'une de celles que le profil accepte ; des profils pour les System Reference Documents 5.1
  et 5.2 sous CC BY 4.0, pour Pathfinder Second Edition sous la licence ORC et pour Fate Core sous CC BY 3.0, avec leurs
  jets nommés et les variantes de règles et nombres de difficulté que donne chaque source — les degrés de difficulté
  typiques des SRD, les deux variantes du SRD 5.1, le budget d'XP d'une rencontre de combat du SRD 5.2, et les quatre
  règles variantes, les DD simples, les DD par niveau et le budget de rencontre de Pathfinder Second Edition —, des
  profils propres à l'application, qui indiquent aussi de telles fiches, et `ImportProfile::withVersion()`, le même
  profil pour la version des fichiers dont un extrait est tiré.
- `Package\DataPackage`, `Package\DataPackageReader`, `Package\DataPackageWriter` et `Package\UpstreamFile` : des
  paquets de données, une source dans une version importée dans le format, avec un manifeste —
  `format: tilttek-rpg-data/1` — qui nomme le système, sa version et sa source, l'empreinte SHA-256 de chaque document
  et les fichiers de l'éditeur dont il a été importé ; un rédacteur du document du système, du manifeste et des mentions
  ; un lecteur qui refuse un document hors du paquet ou modifié après son manifeste, un autre système, une autre version
  ou une autre source, un champ inconnu et un manifeste de plus de 100 000 octets ; des tests qui relisent, strictement,
  les paquets de données du SRD 5.1, du SRD 5.2.1 et de Fate Core importés depuis les fichiers de leurs éditeurs, et de
  Pathfinder Second Edition sous la licence ORC, qui ne nomme aucun fichier amont, avec les variantes de règles et les
  nombres de difficulté de leurs profils.
- `Package\InstalledDataPackages` : les paquets de données que Composer a installés — de type `tilttek-rpg-data` —, ou
  ceux qu'une application liste avec leurs dossiers, par nom dans l'ordre alphabétique, chacun chargé avec le lecteur
  strict et refusé quand il est installé sans dossier ou que son manifeste déclare un autre nom ; leurs systèmes
  ensemble, refusés quand deux partagent une clé. La bibliothèque ne contient aucune donnée de jeu : les fiches de
  chaque source sont dans son paquet de données.
- `Format\SystemReader::provenance()` : une provenance lue depuis une table de correspondance, telle que la contient la
  `source` d'un document.
- Des exceptions sous une seule interface `RpgSystemsException` : `InvalidRecord`, `InvalidDocument` avec la place de
  l'erreur, et `UnknownRecord`.
- Des exemples écrits pour les tests dans `examples/` : un système avec sa variante de règles, ses nombres de difficulté
  et ses rôles de groupe, sa localisation espagnole, et quatre petits extraits.
- Une cible de fuzzing du lecteur et des bancs d'essai de la lecture, de la consultation et du rendu de l'attribution.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
