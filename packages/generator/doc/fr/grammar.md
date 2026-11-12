<!-- phil-dev source-sha256=020dc659135159cf3faebe4601f273ccd56c2824b401268180ea46880576dbc8 -->
# Grammaire

Les filtres `elide`, `article`, `agree` et `plural` suivent la langue du générateur qui en produit le rendu : sa
`locale`, ou la langue par défaut du `Generator`. Un générateur appelé par un autre garde sa propre langue.

Six langues sont intégrées : le français (`fr`), l'anglais (`en`), l'espagnol (`es`), l'allemand (`de`), l'italien
(`it`) et le portugais (`pt`, avec `pt_PT` pour le Portugal). Une locale est cherchée telle qu'écrite, puis sous sa
forme usuelle (`pt-pt` comme `pt_PT`), puis par sa langue : `fr_CA` trouve la grammaire de `fr` quand elle n'a pas la
sienne, et `es_MX` celle de `es`.

| Langue | `elide` | Articles (défini, indéfini, partitif, démonstratif) | Nombres au singulier |
|---|---|---|---|
| Français | élision, formes devant une voyelle, contractions | `le`/`la`/`les`, `un`/`une`/`des`, `du`/`de la`/`des`, `ce`/`cette`/`ces` | 0 et 1 |
| Anglais | `a` ou `an` | `the`, `a`/`an`, `some`, `this`/`these` | 1 |
| Espagnol | `el` devant un a accentué, `del`, `al` | `el`/`la`/`lo`/`los`/`las`, `un`/`una`/`unos`/`unas`, aucun, `este`/`esta`/`estos`/`estas` | 1 |
| Allemand | préposition et article | `der`/`die`/`das`, `ein`/`eine`, aucun, `dieser`/`diese`/`dieses` | 1 |
| Italien | élision, formes selon le son suivant, prépositions articulées | `il`/`lo`/`l'`/`la`/`i`/`gli`/`le`, `un`/`uno`/`una`/`un'`/`dei`/`degli`/`delle`, `del`/`dello`/`della`…, `questo`/`questa`/`questi`/`queste` | 1 |
| Portugais | contractions avec `de`, `em`, `a`, `por` | `o`/`a`/`os`/`as`, `um`/`uma`/`uns`/`umas`, aucun, `este`/`esta`/`estes`/`estas` | 0 et 1 (`pt`), 1 (`pt_PT`) |

Quand une langue n'a pas d'article partitif, ou pas d'article indéfini au pluriel, `article` donne le mot seul. Le
genre neutre (`n`) prend les formes du masculin, sauf en allemand, où il a les siennes, et en espagnol, où l'article
défini du neutre est `lo` (`lo bueno`).

## Garder un mot tel quel

`elide` applique ses règles à chaque paire du texte, même là où la langue ne le ferait pas : un relatif `zu der` en
allemand, `de o` devant un infinitif en portugais. Un mot écrit précédé de `^` (`Grammar::KEEP`) est laissé tel
qu'écrit, tout comme le mot qui le précède : aucune élision, aucune contraction, aucun changement de forme ; `elide`
retire ensuite la marque.

```twig
{{ 'zu ^der Zeit, als'|elide }}             {# zu der Zeit, als #}
{{ 'antes de ^o ver'|elide }}               {# antes de o ver #}
{{ 'le ^onze de la troupe'|elide }}         {# le onze de la troupe #}
```

La marque se place dans un gabarit ou dans le texte d'une entrée. `article` fait l'élision : il retire donc aussi la
marque du mot qu'on lui donne. Un texte qui ne passe jamais par `elide` ni par `article` garde ses marques.

## Français

`elide` produit, sur tout le texte :

- les élisions devant une voyelle ou un h muet : `le`, `la`, `de`, `je`, `me`, `te`, `se`, `ne`, `que`, `jusque`,
  `lorsque`, `puisque`, `quoique` (`la épée` → `l'épée`, `que il` → `qu'il`), et `si` devant `il` et `ils` (`s'il`) ;
- les formes devant une voyelle : `ce` → `cet`, `ma` → `mon`, `ta` → `ton`, `sa` → `son` ;
- puis les contractions : `de le` → `du`, `de les` → `des`, `à le` → `au`, `à les` → `aux`, de sorte que `de le arbre`
  donne `de l'arbre` et `de le héros` donne `du héros`.

Un mot dont le h est aspiré garde son article entier : les mots courants sont intégrés (`hache`, `héros`, `hibou`,
`haut`, `onze`, `yacht`…), et `new French(['hobgobelin'])` ajoute ceux d'une application. Les majuscules sont gardées
(`De le roi` → `Du roi`). L'apostrophe est celle de la machine à écrire, que `phil/typography` peut changer en
apostrophe typographique.

`article` place `le`, `la`, `les` ; `un`, `une`, `des` ; `du`, `de la`, `des` ; `ce`, `cette`, `ces`, selon le
`gender` de l'entrée ou le genre donné, et fait l'élision du résultat :

```twig
{% set weapon = pick('weapon') %}{{ weapon|article }}                {# l'épée #}
{{ weapon|article('indefinite', plural: true) }}                     {# des épées #}
{{ 'eau'|article('partitive', 'f') }}                                {# de l'eau #}
```

`agree` donne un adjectif au genre et au nombre d'une entrée de nom (son `gender`, et `number: p` pour un nom toujours
au pluriel), ou d'un genre et d'un nombre donnés. Les champs `m`, `f`, `mp` et `fp` de l'adjectif passent d'abord ;
sinon, les règles régulières forment le féminin (`-e`, `-eux` → `-euse`, `-eur` → `-euse` mais `meilleure`, `-if` →
`-ive`, `-er` → `-ère`, `-el`, `-eil`, `-en`, `-on`, `-et` doublés) et le pluriel :

```twig
{{ pick('adjective')|agree(weapon) }}       {# ancienne #}
{{ 'beau'|agree('f', true) }}               {# beaues — give `f: belle` and `fp: belles` to irregular adjectives #}
```

`plural` donne le champ `plural` de l'entrée, ou le pluriel régulier (`-s`, `-x` et `-z` inchangés, `-al` → `-aux` mais
`bals`, `-ail` → `-ails` mais `travaux`, `-au` et `-eu` → `-x` mais `pneus`, `-ou` → `-ous` mais `bijoux`), ou le mot
lui-même quand le nombre prend le singulier, soit 0 et 1 en français :

```twig
{{ price }} {{ 'pièce'|plural(price) }}
```

## Anglais

`elide` choisit `a` ou `an` selon le son du mot suivant (`an apple`, `a unicorn`, `an hour`, `a one-eyed`). `article`
place `the`, `a`/`an` (rien au pluriel), `some`, `this` ou `these`. `agree` donne l'adjectif inchangé. `plural` forme
le pluriel régulier (`-es` après `s`, `x`, `z`, `ch` et `sh`, `-ies` après une consonne et `y`) avec les irréguliers
courants (`elves`, `dwarves`, `mice`, `dice`, `people`…) ; seul le nombre 1 prend le singulier.

## Espagnol

`elide` produit, sur tout le texte :

- `la` → `el` et `una` → `un` devant un mot féminin dont la première syllabe est un a accentué (`la agua` →
  `el agua`, `una hacha` → `un hacha`). Les mots courants sont intégrés (`agua`, `águila`, `alma`, `arma`, `hacha`,
  `hada`, `hambre`…), au singulier, et `new Spanish(['ánima'])` ajoute ceux d'une application ;
- puis les contractions `de el` → `del` et `a el` → `al` (`de la agua` → `del agua`). Le pronom `él` est laissé tel
  quel, tout comme un nom qui commence par `El` après une préposition en minuscules (`de El Dorado`).

`article` place les articles du tableau ci-dessus et fait l'élision du résultat (`hacha|article('indefinite', 'f')` →
`un hacha`).

`agree` forme le féminin régulier : `-o` → `-a`, `-or` → `-ora` (mais `mejor`, `mayor`, `superior`… inchangés), `-án`,
`-én`, `-ín`, `-ón` et `-és` → `-ana`, `-ena`, `-ina`, `-ona`, `-esa` sans leur accent écrit (`alemana`, `inglesa`,
mais `cortés` inchangé) ; les autres adjectifs (`grande`, `feliz`, `común`) gardent leur forme.

`plural` ajoute `-s` après une voyelle (`casas`, `cafés`), mais `-es` après un `í` ou un `ú` accentués (`rubíes`). Il
change `-z` en `-ces` (`luces`). Il ajoute `-es` après une consonne, en retirant l'accent écrit de la dernière syllabe
(`canciones`, `autobuses`, mais `países`, dont le i est détaché) et en en ajoutant un quand l'accent tombe sur
l'antépénultième syllabe (`jóvenes`, `exámenes`). Il laisse inchangé un mot de plusieurs syllabes qui finit par un
`-s` ou un `-x` non accentués (`lunes`, `crisis`, `tórax`), tandis qu'un mot d'une syllabe prend `-es` (`meses`,
`dioses`). `carácter`, `régimen`, `espécimen` et `club` sont intégrés comme irréguliers.

```twig
{{ pick('weapon')|article }}                 {# el hacha #}
{{ ('el filo de ' ~ weapon|article)|elide }} {# el filo del hacha #}
{{ 'canción'|plural(count) }}                {# canciones #}
```

## Allemand

`elide` contracte une préposition avec l'article qui la suit : `an dem` → `am`, `an das` → `ans`, `bei dem` → `beim`,
`in dem` → `im`, `in das` → `ins`, `von dem` → `vom`, `zu dem` → `zum`, `zu der` → `zur`. Elle s'applique à chacune de
ces paires dans le texte : un gabarit n'écrit donc ces paires que là où elles sont l'article.

`article` choisit l'article selon le genre et le nombre seulement, au nominatif : `der`, `die`, `das`, `die` ; `ein`,
`eine`, `ein`, et rien au pluriel ; rien pour le partitif ; `dieser`, `diese`, `dieses`, `diese`. Un gabarit qui a
besoin d'un autre cas écrit lui-même l'article, ou garde les formes déclinées dans les champs d'une entrée.

`agree` donne l'adjectif inchangé : sa terminaison dépend de l'article et du cas, que la grammaire ne connaît pas. Une
entrée donne donc ses formes déclinées dans ses champs `f`, `mp` et `fp` quand un gabarit en a besoin.

`plural` suit la terminaison du mot : `-e` → `-en` (`Blumen`), `-in` → `-innen`, `-ung`, `-heit`, `-keit`, `-schaft`,
`-ion`, `-tät`, `-ik` et `-ei` → `-en` ; `-er`, `-el`, `-en`, `-chen` et `-lein` inchangés (`Ritter`, `Würfel`,
`Mädchen`) ; `-nis` et `-us` → `-se` ; `-um` → `-en` (`Museen`) ; `-s` après une autre voyelle (`Autos`) ; `-e` sinon
(`Tage`, `Zwerge`). Les mots irréguliers courants sont intégrés (`Bäume`, `Bücher`, `Füße`, `Häuser`, `Kinder`,
`Männer`, `Schwerter`, `Städte`, `Wölfe`…) ; tout autre va dans le champ `plural` de l'entrée.

```twig
{{ pick('weapon')|article('demonstrative') }}    {# dieses Schwert #}
{{ 'zu dem Turm'|elide }}                        {# zum Turm #}
```

## Italien

`elide` produit, sur tout le texte, d'abord les formes de l'article selon le son du mot suivant :

- `il`, `lo`, `la` → `l'` et `una` → `un'` devant une voyelle ou un h (`l'albero`, `l'hotel`, `un'amica`) ;
- `il` → `lo`, `un` → `uno` et `i` → `gli` devant un s suivi d'une consonne, un z, `gn`, `ps`, `pn`, un x ou un y
  (`lo spettro`, `uno zaino`, `gli gnomi`), et `i` → `gli` devant une voyelle (`gli elmi`) ;

puis les prépositions articulées : `di`, `a`, `da`, `in` et `su` jointes à `il`, `lo`, `l'`, `la`, `i`, `gli` et `le`
(`di il` → `del`, `a lo` → `allo`, `in l'antro` → `nell'antro`, `su gli` → `sugli`, `di le` → `delle`), de sorte que
`di il oro` donne `dell'oro`.

`article` place les articles défini et indéfini sous leur bonne forme ; l'indéfini pluriel et le partitif sont `dei`,
`degli`, `delle`, `del`, `dello`, `dell'`, `della` selon les mêmes règles ; le démonstratif est `questo`, `questa`,
`questi`, `queste`.

`agree` forme le féminin régulier : `-o` → `-a`, `-tore` → `-trice` (`attrice`) ; un adjectif en `-e` ou en `-a` garde
sa forme.

`plural` change `-o` et `-e` en `-i`, `-a` en `-e` ; `-co` et `-go` en `-chi` et `-ghi` (`boschi`, `laghi`), mais
`-ico` en `-ici` (`magici`) ; `-io` en `-i` (`figli`) ; `-ca` et `-ga` en `-che` et `-ghe` ; `-cia` et `-gia` en
`-cie` et `-gie` après une voyelle (`camicie`) et en `-ce` et `-ge` après une consonne (`arance`). Un mot qui finit par
une voyelle accentuée, une consonne, `-i` ou `-ie` est inchangé (`città`, `sport`, `crisi`, `specie`). Les mots
irréguliers courants sont intégrés (`uomini`, `uova`, `dita`, `braccia`, `mani`, `amici`, `nemici`, `problemi`,
`poeti`, `zii`…).

```twig
{{ pick('monster')|article('indefinite', plural: true) }}    {# degli spettri #}
{{ ('la paura di ' ~ monster|article)|elide }}               {# la paura dello spettro #}
```

## Portugais

`elide` contracte, sur tout le texte :

- `de`, `em` et `por` avec `o`, `a`, `os`, `as` : `do`, `da`, `dos`, `das` ; `no`, `na`, `nos`, `nas` ; `pelo`,
  `pela`, `pelos`, `pelas` ;
- `a` avec l'article : `ao`, `aos`, `à`, `às` ;
- `de` et `em` avec les démonstratifs `este`, `esse`, `aquele` et leurs formes, et `isto`, `isso`, `aquilo` (`deste`,
  `nessa`, `daquele`, `nisto`), et `a` avec `aquele` et `aquilo` (`àquele`, `àquilo`).

`article` place les articles du tableau ci-dessus, sans les contracter ; un gabarit contracte en faisant l'élision
d'une préposition suivie de l'article (`('em ' ~ word|article)|elide` → `na torre`).

`agree` forme le féminin régulier : `-o` → `-a`, `-or` → `-ora` (mais `melhor`, `maior`, `superior`… inchangés), `-ão`
→ `-ã` (`alemã`, mais `leoa`, `ladra`, `valentona`), `-ês` → `-esa` (mais `cortês` inchangé), `-eu` → `-eia`
(`europeia`, mais `judia`), `-u` → `-ua` (`crua`) ; les autres adjectifs (`grande`, `feliz`) gardent leur forme.

`plural` ajoute `-s` après une voyelle (`casas`, `mães`). Il change `-ão` en `-ões` (`canções`), avec les mots courants
en `-ãos` et en `-ães` intégrés (`mãos`, `irmãos`, `cristãos`, `pães`, `cães`, `alemães`, `capitães`…), et `-m` en
`-ns` (`homens`). Il ajoute `-es` après `-r` et `-z` (`mares`, `luzes`). Il ajoute `-es` après un `-s` accentué, en
retirant l'accent écrit (`meses`, `portugueses`, mais `países`), et après un mot d'une syllabe (`deuses`) ; il laisse
inchangés un `-s` non accentué et un `-x` (`lápis`, `vírus`, `tórax`). Il change `-al`, `-ul` en `-ais`, `-uis`, et
`-el`, `-ol` en `-éis`, `-óis`, ou en `-eis`, `-ois` quand le mot porte déjà un accent (`papéis`, `níveis`). Il change
enfin `-il` en `-is`, ou en `-eis` dans un mot qui porte un accent (`barris`, `fáceis`).

Le nombre 0 prend le singulier sous `pt`, comme au Brésil ; sous `pt_PT`, construit avec `new Portuguese(zeroIsSingular:
false)`, seul 1 le prend, comme au Portugal.

```twig
{{ ('em ' ~ pick('place')|article)|elide }}    {# na torre #}
{{ 'canção'|plural(count) }}                   {# canções #}
```

## Ajouter une langue

Une langue est une classe qui implémente le port `Grammar`, donnée au `Generator` dans un `Grammars` :

```php
namespace Tilttek\Generator\Grammar;

interface Grammar
{
    public function elide(string $text): string;
    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string;
    public function feminine(string $word): string;
    public function plural(string $word): string;
    public function isPlural(int $count): bool;
}
```

| Méthode | Appelée par | Contrat |
|---|---|---|
| `elide($text)` | le filtre `elide` et les `filters: [elide]` d'un générateur | Le texte entier, avec les élisions et les contractions de la langue faites. Elle garde les espaces et les majuscules qu'elle ne change pas, et rend inchangé un texte sans aucune de ces paires ; une langue qui n'en a aucune rend le texte. Un mot qui commence par `Grammar::KEEP` est laissé tel qu'écrit, tout comme le mot qui le précède ; la marque est retirée. |
| `article($word, $kind, $gender, $plural)` | le filtre `article` | Le mot, déjà au singulier ou au pluriel voulu, précédé de son article, élision déjà faite. `ArticleKind` vaut `Definite`, `Indefinite`, `Partitive` ou `Demonstrative` ; `Gender` vaut `Masculine`, `Feminine` ou `Neuter`, `Masculine` quand l'entrée n'en donne aucun. Une langue sans cet article donne le mot seul. |
| `feminine($word)` | le filtre `agree`, quand l'adjectif n'a pas de champ `f` | Le féminin d'un mot donné au masculin, selon les règles régulières ; le mot lui-même dans une langue qui n'en a pas. |
| `plural($word)` | les filtres `plural` et `agree`, quand l'entrée n'a pas de champ `plural`, `mp` ou `fp` | Le pluriel d'un mot donné au singulier, selon les règles régulières. Les mots irréguliers dont une application a besoin vont dans les champs des entrées plutôt que dans la grammaire. |
| `isPlural($count)` | le filtre `plural` avec un nombre | Si ce nombre prend la forme du pluriel. |

Les filtres donnent à chaque méthode un mot déjà transformé en texte, et lisent les champs propres à l'entrée
(`gender`, `number`, `plural`, `m`, `f`, `mp`, `fp`) avant d'appeler la grammaire : une grammaire ne contient donc que
les règles de sa langue. Elle ne doit pas lever d'exception sur un mot qu'elle ne connaît pas : elle applique sa règle
régulière, ou rend le mot.

Une ébauche pour le néerlandais, dont l'article défini est `de`, ou `het` pour un mot neutre :

```php
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Grammar;

final readonly class Dutch implements Grammar
{
    public function elide(string $text): string
    {
        return $text;
    }

    public function article(string $word, ArticleKind $kind, Gender $gender, bool $plural): string
    {
        $neuter = Gender::Neuter === $gender && !$plural;

        return match ($kind) {
            ArticleKind::Definite => ($neuter ? 'het ' : 'de ').$word,
            ArticleKind::Indefinite => $plural ? $word : 'een '.$word,
            ArticleKind::Partitive => $word,
            ArticleKind::Demonstrative => ($neuter ? 'dit ' : 'deze ').$word,
        };
    }

    public function feminine(string $word): string
    {
        return $word;
    }

    public function plural(string $word): string
    {
        return preg_match('/[aiouy]$/', $word) ? $word."'s" : $word.'en';
    }

    public function isPlural(int $count): bool
    {
        return 1 !== abs($count);
    }
}
```

La grammaire se donne par langue, ou par locale pour une variante régionale, à côté des grammaires intégrées ; une clé
qui nomme une langue intégrée la remplace :

```php
$grammars = new Grammars([
    'nl' => new Dutch(),                           // a new language
    'fr' => new French(['hobgobelin']),            // the built-in French with more aspirated words
    'es' => new Spanish(['ánima']),                // the built-in Spanish with more words with a stressed a
]);
$generator = new Generator($catalog, locale: 'nl', grammars: $grammars);
```

Un générateur dont la `locale` n'a pas de grammaire est une erreur à la vérification du catalogue. Les grammaires
intégrées sont `final` : une grammaire qui ne diffère de l'une d'elles que par quelques règles l'enveloppe et lui
délègue le reste.
