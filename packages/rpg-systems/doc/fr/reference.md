<!-- phil-dev source-sha256=d3432a3f5d87e4a82dcdf1d3d32a687b3da568cea5e17bd96eb227efc57422fa -->
# Référence

| Type | Rôle |
|---|---|
| `GameSystem` | Un système : noms, version, provenance, attributs, expressions de dés, termes, variantes de règles, nombres de difficulté, rôles de groupe et sources de ses traductions |
| `Systems` | Les systèmes d'une application, par clé |
| `Attribute` | Un attribut, ses noms, abréviations, descriptions et bornes |
| `DiceExpression` | Un jet dans la notation canonique de `tilttek/dice`, vérifié et normalisé |
| `Term` | Un terme, ses noms, descriptions et sa catégorie |
| `RuleVariant` | Une variante de règles qu'une table peut choisir, ses noms et descriptions |
| `Difficulty` | Des nombres de difficulté : leur méthode, leurs paliers du plus facile et leurs tables de nombres entiers par étiquette |
| `DifficultyBand` | Un palier de nombres de difficulté, ses noms et son propre nombre |
| `PartyRole` | Un rôle qu'un système déclare pour les membres d'un groupe, ses noms et descriptions |
| `Translations` | Un texte par locale, et le texte trouvé pour un lecteur |
| `Localisation` | Les textes d'une langue de plus et la source de la traduction |
| `Provenance` | La source, la licence et l'attribution d'un enregistrement |
| `Licence` | La liste fermée des licences, ce que chacune demande, et la page de son texte |
| `Format\SystemReader` | Lit les documents du format, YAML ou JSON, et charge des fichiers |
| `Format\SystemWriter` | Écrit un système comme document du format |
| `Format\Syntax` | YAML ou JSON, d'après l'extension d'un fichier |
| `Attribution\AttributionRenderer` | Les mentions d'un système ou d'une page, en texte ou en HTML |
| `Import\Importer` | Transforme un extrait en système |
| `Import\ImportProfile` | Comment un extrait devient un système, avec les enregistrements que sa source énonce une fois pour tous ; les profils du SRD 5.1, du SRD 5.2, de Pathfinder Second Edition et de Fate Core, et le même profil pour une autre version de sa source |
| `Import\TableMapping` | Une table d'un extrait et les champs de ses enregistrements |
| `Package\DataPackage` | Le manifeste d'un paquet de données : nom, système, version, source, documents et fichiers de l'éditeur |
| `Package\DataPackageReader` | Lit un paquet de données et vérifie ses documents d'après le manifeste |
| `Package\DataPackageWriter` | Écrit un système comme paquet de données |
| `Package\InstalledDataPackages` | Les paquets de données que Composer a installés, par nom, chargés en systèmes |
| `Package\UpstreamFile` | Un fichier de l'éditeur dont un paquet de données a été importé |
| `Exception\RpgSystemsException` et ses trois exceptions | [Exceptions](exceptions.md) |
