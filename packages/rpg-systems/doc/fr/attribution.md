<!-- phil-dev source-sha256=a34cdd20627960d7f0e7e7caa62078e7f310b8d2c7063787f2fef8feb420ca9b -->
# Sources et attribution

## Les provenances

Une `Provenance` dit d'où vient un enregistrement :

| Champ | Ce qu'il contient |
|---|---|
| `title` | Le titre de la source, obligatoire |
| `licence` | Sa `Licence`, obligatoire |
| `attribution` | La mention qu'une œuvre qui montre le contenu affiche : obligatoire quand la licence demande une attribution |
| `author` | Qui détient la source |
| `url` | Où elle est publiée : une adresse http ou https absolue, lue par `phil/url` |

Chaque enregistrement en porte une, et deux provenances égales partagent une `identity()`, de sorte que chaque source
soit montrée une seule fois. L'attribution est le texte que la source demande, donné par qui énonce la provenance : la
bibliothèque ne l'écrit pas.

## Les licences

La liste des licences est fermée ; un contenu sous toute autre licence est refusé à sa lecture.

| `Licence` | Identifiant | Demande une attribution | Réciproque |
|---|---|---|---|
| `CcBy40` | `CC-BY-4.0` | Oui | Non |
| `CcBy30` | `CC-BY-3.0` | Oui | Non |
| `CcBySa40` | `CC-BY-SA-4.0` | Oui | Oui |
| `Cc0` | `CC0-1.0` | Non | Non |
| `Orc` | `ORC` | Oui | Oui |
| `Owned` | `owned` | Non | Non |

`title()` et `url()` donnent le nom de la licence et la page de son texte ; `Owned`, le contenu propre à l'application,
n'a aucune page. `isReciprocal()` dit si une œuvre qui adapte le contenu le transmet sous la même licence.

## Les mentions d'une page

`AttributionRenderer` donne les mentions qu'une page affiche : une pour chaque source dont la licence demande une
attribution, chacune une seule fois, dans l'ordre où les sources apparaissent d'abord.

```php
$renderer = new AttributionRenderer();

$renderer->text($system);               // one line per source
$renderer->html($system, 'es');         // an HTML list, with the Spanish translation's source
$renderer->sources($system);            // the provenances themselves
```

Une page qui montre quelques enregistrements plutôt qu'un système entier donne leurs provenances :
`$renderer->html([$term->provenance, $attribute->provenance])`. La locale choisit les traductions dont les sources sont
montrées — celles de la locale et de sa langue — ; sans locale, la source de chaque traduction l'est. `all: true` liste
aussi les sources qui ne demandent aucune attribution.

Une ligne contient l'attribution, ou le titre d'une source qui n'en a pas — suivi de son adresse quand elle en a une —,
puis le nom de la licence et la page de son texte :

```text
This work includes material from the System Reference Document 5.2 … (Creative Commons Attribution 4.0 International: https://creativecommons.org/licenses/by/4.0/)
```

La liste HTML est `<ul class="attribution">`, chaque texte échappé, le nom de la licence lié à sa page avec
`rel="license"` ; c'est une chaîne vide quand aucune source ne demande d'attribution, pour qu'une page n'imprime rien.
