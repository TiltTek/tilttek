# Grammar

The filters `elide`, `article`, `agree` and `plural` follow the language of the generator that renders them — its
`locale`, or the default language of the `Generator`. A generator called by another keeps its own language.

Six languages are built in: French (`fr`), English (`en`), Spanish (`es`), German (`de`), Italian (`it`) and
Portuguese (`pt`, with `pt_PT` for Portugal). A locale is looked up as written, then in its usual form (`pt-pt` as
`pt_PT`), then by its language: `fr_CA` finds the grammar of `fr` when it has none of its own, and `es_MX` that of `es`.

| Language | `elide` | Articles (definite, indefinite, partitive, demonstrative) | Singular counts |
|---|---|---|---|
| French | elision, forms before a vowel, contractions | `le`/`la`/`les`, `un`/`une`/`des`, `du`/`de la`/`des`, `ce`/`cette`/`ces` | 0 and 1 |
| English | `a` or `an` | `the`, `a`/`an`, `some`, `this`/`these` | 1 |
| Spanish | `el` before a stressed a, `del`, `al` | `el`/`la`/`lo`/`los`/`las`, `un`/`una`/`unos`/`unas`, none, `este`/`esta`/`estos`/`estas` | 1 |
| German | preposition and article | `der`/`die`/`das`, `ein`/`eine`, none, `dieser`/`diese`/`dieses` | 1 |
| Italian | elision, forms by the next sound, articulated prepositions | `il`/`lo`/`l'`/`la`/`i`/`gli`/`le`, `un`/`uno`/`una`/`un'`/`dei`/`degli`/`delle`, `del`/`dello`/`della`…, `questo`/`questa`/`questi`/`queste` | 1 |
| Portuguese | contractions with `de`, `em`, `a`, `por` | `o`/`a`/`os`/`as`, `um`/`uma`/`uns`/`umas`, none, `este`/`esta`/`estes`/`estas` | 0 and 1 (`pt`), 1 (`pt_PT`) |

Where a language has no partitive article, or no indefinite article in the plural, `article` gives the word alone. A
neuter gender (`n`) takes the masculine forms, except in German, where it has its own, and in Spanish, where the
definite article of the neuter is `lo` (`lo bueno`).

## Keeping a word as written

`elide` applies its rules to every pair of the text, even where the language would not: a relative `zu der` in German,
`de o` before an infinitive in Portuguese. A word written with `^` before it — `Grammar::KEEP` — is left as written,
and so is the word before it: no elision, no contraction, no change of form; `elide` then removes the mark.

```twig
{{ 'zu ^der Zeit, als'|elide }}             {# zu der Zeit, als #}
{{ 'antes de ^o ver'|elide }}               {# antes de o ver #}
{{ 'le ^onze de la troupe'|elide }}         {# le onze de la troupe #}
```

The mark goes in a template or in an entry's text; `article` elides, so it removes the mark of the word it is given
too. A text that never goes through `elide` or `article` keeps its marks.

## French

`elide` makes, over the whole text:

- the elisions before a vowel or a mute h: `le`, `la`, `de`, `je`, `me`, `te`, `se`, `ne`, `que`, `jusque`, `lorsque`,
  `puisque`, `quoique` (`la épée` → `l'épée`, `que il` → `qu'il`), and `si` before `il` and `ils` (`s'il`);
- the forms before a vowel: `ce` → `cet`, `ma` → `mon`, `ta` → `ton`, `sa` → `son`;
- then the contractions: `de le` → `du`, `de les` → `des`, `à le` → `au`, `à les` → `aux`, so that `de le arbre` gives
  `de l'arbre` and `de le héros` gives `du héros`.

A word whose h is aspirated keeps its article whole: the common ones are built in (`hache`, `héros`, `hibou`, `haut`,
`onze`, `yacht`…), and `new French(['hobgobelin'])` adds an application's own. Capitals are kept (`De le roi` → `Du
roi`); the apostrophe is the typewriter one, which `phil/typography` may turn into a typographic one.

`article` puts `le`, `la`, `les`; `un`, `une`, `des`; `du`, `de la`, `des`; `ce`, `cette`, `ces` — by the entry's
`gender`, or the gender given — and elides the result:

```twig
{% set weapon = pick('weapon') %}{{ weapon|article }}                {# l'épée #}
{{ weapon|article('indefinite', plural: true) }}                     {# des épées #}
{{ 'eau'|article('partitive', 'f') }}                                {# de l'eau #}
```

`agree` gives an adjective in the gender and number of a noun entry — its `gender`, and `number: p` for a noun always
plural — or of a gender and a number given. The adjective's `m`, `f`, `mp` and `fp` fields come first; otherwise the
regular rules make the feminine (`-e`, `-eux` → `-euse`, `-eur` → `-euse` but `meilleure`, `-if` → `-ive`, `-er` →
`-ère`, `-el`, `-eil`, `-en`, `-on`, `-et` doubled) and the plural:

```twig
{{ pick('adjective')|agree(weapon) }}       {# ancienne #}
{{ 'beau'|agree('f', true) }}               {# beaues — give `f: belle` and `fp: belles` to irregular adjectives #}
```

`plural` gives the entry's `plural` field, or the regular plural (`-s`, `-x` and `-z` unchanged, `-al` → `-aux` but
`bals`, `-ail` → `-ails` but `travaux`, `-au` and `-eu` → `-x` but `pneus`, `-ou` → `-ous` but `bijoux`), or the word
itself when the count takes the singular — 0 and 1 in French:

```twig
{{ price }} {{ 'pièce'|plural(price) }}
```

## English

`elide` chooses `a` or `an` by the sound of the next word (`an apple`, `a unicorn`, `an hour`, `a one-eyed`); `article`
puts `the`, `a`/`an` — nothing in the plural —, `some`, `this` or `these`; `agree` gives the adjective unchanged; and
`plural` makes the regular plural (`-es` after `s`, `x`, `z`, `ch` and `sh`, `-ies` after a consonant and `y`) with the
common irregular ones (`elves`, `dwarves`, `mice`, `dice`, `people`…), the count 1 alone taking the singular.

## Spanish

`elide` makes, over the whole text:

- `la` → `el` and `una` → `un` before a feminine word whose first syllable is a stressed a (`la agua` → `el agua`,
  `una hacha` → `un hacha`). The common words are built in (`agua`, `águila`, `alma`, `arma`, `hacha`, `hada`,
  `hambre`…), in the singular, and `new Spanish(['ánima'])` adds an application's own;
- then the contractions `de el` → `del` and `a el` → `al` (`de la agua` → `del agua`). The pronoun `él` is left alone,
  and so is a name that starts with `El` after a preposition in lower case (`de El Dorado`).

`article` puts the articles of the table above and elides the result (`hacha|article('indefinite', 'f')` → `un
hacha`).

`agree` makes the regular feminine: `-o` → `-a`, `-or` → `-ora` (but `mejor`, `mayor`, `superior`… unchanged), `-án`,
`-én`, `-ín`, `-ón` and `-és` → `-ana`, `-ena`, `-ina`, `-ona`, `-esa` without their written accent (`alemana`,
`inglesa`, but `cortés` unchanged); other adjectives (`grande`, `feliz`, `común`) keep their form.

`plural` adds `-s` after a vowel (`casas`, `cafés`) but `-es` after a stressed `í` or `ú` (`rubíes`); turns `-z` into
`-ces` (`luces`); adds `-es` after a consonant, dropping the written accent of the last syllable (`canciones`,
`autobuses`, but `países`, whose i stands apart) and adding one when the stress falls on the third syllable from the end
(`jóvenes`, `exámenes`); leaves a word of several syllables ending in an unstressed `-s` or `-x` unchanged (`lunes`,
`crisis`, `tórax`), while a word of one syllable takes `-es` (`meses`, `dioses`). `carácter`, `régimen`, `espécimen` and
`club` are built in as irregular.

```twig
{{ pick('weapon')|article }}                 {# el hacha #}
{{ ('el filo de ' ~ weapon|article)|elide }} {# el filo del hacha #}
{{ 'canción'|plural(count) }}                {# canciones #}
```

## German

`elide` contracts a preposition with the article that follows it: `an dem` → `am`, `an das` → `ans`, `bei dem` →
`beim`, `in dem` → `im`, `in das` → `ins`, `von dem` → `vom`, `zu dem` → `zum`, `zu der` → `zur`. It applies to every
such pair of the text, so a template writes these pairs only where they are the article.

`article` chooses the article by gender and number alone, in the nominative: `der`, `die`, `das`, `die`; `ein`,
`eine`, `ein`, and nothing in the plural; nothing for the partitive; `dieser`, `diese`, `dieses`, `diese`. A template
that needs another case writes the article itself, or keeps the declined forms in an entry's fields.

`agree` gives the adjective unchanged: its ending depends on the article and the case, which the grammar does not know,
so an entry gives its declined forms in its `f`, `mp` and `fp` fields when a template needs them.

`plural` follows the ending of the word: `-e` → `-en` (`Blumen`), `-in` → `-innen`, `-ung`, `-heit`, `-keit`,
`-schaft`, `-ion`, `-tät`, `-ik` and `-ei` → `-en`; `-er`, `-el`, `-en`, `-chen` and `-lein` unchanged (`Ritter`,
`Würfel`, `Mädchen`); `-nis` and `-us` → `-se`; `-um` → `-en` (`Museen`); `-s` after another vowel (`Autos`); `-e`
otherwise (`Tage`, `Zwerge`). The common irregular words are built in (`Bäume`, `Bücher`, `Füße`, `Häuser`, `Kinder`,
`Männer`, `Schwerter`, `Städte`, `Wölfe`…); any other goes in the entry's `plural` field.

```twig
{{ pick('weapon')|article('demonstrative') }}    {# dieses Schwert #}
{{ 'zu dem Turm'|elide }}                        {# zum Turm #}
```

## Italian

`elide` makes, over the whole text, first the forms of the article by the sound of the next word:

- `il`, `lo`, `la` → `l'` and `una` → `un'` before a vowel or an h (`l'albero`, `l'hotel`, `un'amica`);
- `il` → `lo`, `un` → `uno` and `i` → `gli` before an s followed by a consonant, a z, `gn`, `ps`, `pn`, an x or a y
  (`lo spettro`, `uno zaino`, `gli gnomi`), and `i` → `gli` before a vowel (`gli elmi`);

then the articulated prepositions: `di`, `a`, `da`, `in` and `su` joined with `il`, `lo`, `l'`, `la`, `i`, `gli` and
`le` (`di il` → `del`, `a lo` → `allo`, `in l'antro` → `nell'antro`, `su gli` → `sugli`, `di le` → `delle`), so that
`di il oro` gives `dell'oro`.

`article` puts the definite and indefinite articles in their right form; the indefinite plural and the partitive are
`dei`, `degli`, `delle`, `del`, `dello`, `dell'`, `della` by the same rules; the demonstrative is `questo`, `questa`,
`questi`, `queste`.

`agree` makes the regular feminine: `-o` → `-a`, `-tore` → `-trice` (`attrice`); an adjective in `-e` or `-a` keeps its
form.

`plural` turns `-o` and `-e` into `-i`, `-a` into `-e`; `-co` and `-go` into `-chi` and `-ghi` (`boschi`, `laghi`) but
`-ico` into `-ici` (`magici`); `-io` into `-i` (`figli`); `-ca` and `-ga` into `-che` and `-ghe`; `-cia` and `-gia` into
`-cie` and `-gie` after a vowel (`camicie`) and `-ce` and `-ge` after a consonant (`arance`). A word ending in a
stressed vowel, a consonant, `-i` or `-ie` is unchanged (`città`, `sport`, `crisi`, `specie`). The common irregular
words are built in (`uomini`, `uova`, `dita`, `braccia`, `mani`, `amici`, `nemici`, `problemi`, `poeti`, `zii`…).

```twig
{{ pick('monster')|article('indefinite', plural: true) }}    {# degli spettri #}
{{ ('la paura di ' ~ monster|article)|elide }}               {# la paura dello spettro #}
```

## Portuguese

`elide` contracts, over the whole text:

- `de`, `em` and `por` with `o`, `a`, `os`, `as`: `do`, `da`, `dos`, `das`; `no`, `na`, `nos`, `nas`; `pelo`, `pela`,
  `pelos`, `pelas`;
- `a` with the article: `ao`, `aos`, `à`, `às`;
- `de` and `em` with the demonstratives `este`, `esse`, `aquele` and their forms, and `isto`, `isso`, `aquilo`
  (`deste`, `nessa`, `daquele`, `nisto`), and `a` with `aquele` and `aquilo` (`àquele`, `àquilo`).

`article` puts the articles of the table above, without contracting them; a template contracts by eliding a preposition
followed by the article (`('em ' ~ word|article)|elide` → `na torre`).

`agree` makes the regular feminine: `-o` → `-a`, `-or` → `-ora` (but `melhor`, `maior`, `superior`… unchanged), `-ão`
→ `-ã` (`alemã`, but `leoa`, `ladra`, `valentona`), `-ês` → `-esa` (but `cortês` unchanged), `-eu` → `-eia`
(`europeia`, but `judia`), `-u` → `-ua` (`crua`); other adjectives (`grande`, `feliz`) keep their form.

`plural` adds `-s` after a vowel (`casas`, `mães`); turns `-ão` into `-ões` (`canções`), with the common words in
`-ãos` and `-ães` built in (`mãos`, `irmãos`, `cristãos`, `pães`, `cães`, `alemães`, `capitães`…); `-m` into `-ns`
(`homens`); adds `-es` after `-r` and `-z` (`mares`, `luzes`); adds `-es` after a stressed `-s`, dropping the written
accent (`meses`, `portugueses`, but `países`) and after a word of one syllable (`deuses`), and leaves an unstressed
`-s` and an `-x` unchanged (`lápis`, `vírus`, `tórax`); turns `-al`, `-ul` into `-ais`, `-uis`, `-el`, `-ol` into
`-éis`, `-óis` — `-eis`, `-ois` when the word already bears an accent (`papéis`, `níveis`) — and `-il` into `-is`, or
`-eis` in a word that bears an accent (`barris`, `fáceis`).

The count 0 takes the singular under `pt`, as in Brazil; under `pt_PT`, built with `new Portuguese(zeroIsSingular:
false)`, 1 alone takes it, as in Portugal.

```twig
{{ ('em ' ~ pick('place')|article)|elide }}    {# na torre #}
{{ 'canção'|plural(count) }}                   {# canções #}
```

## Adding a language

A language is a class that implements the `Grammar` port, given to the `Generator` in a `Grammars`:

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

| Method | Called by | Contract |
|---|---|---|
| `elide($text)` | the `elide` filter and a generator's `filters: [elide]` | The whole text with the language's elisions and contractions made. It keeps the spaces and the capitals it does not change, and gives back a text without any such pair unchanged; a language without any gives the text back. A word that starts with `Grammar::KEEP` is left as written, and so is the word before it; the mark is removed. |
| `article($word, $kind, $gender, $plural)` | the `article` filter | The word, already in the singular or the plural wanted, with its article before it, already elided. `ArticleKind` is `Definite`, `Indefinite`, `Partitive` or `Demonstrative`; `Gender` is `Masculine`, `Feminine` or `Neuter`, `Masculine` when the entry gives none. A language without such an article gives the word alone. |
| `feminine($word)` | the `agree` filter, when the adjective has no `f` field | The feminine of a word given in the masculine, by the regular rules; the word itself in a language without one. |
| `plural($word)` | the `plural` and `agree` filters, when the entry has no `plural`, `mp` or `fp` field | The plural of a word given in the singular, by the regular rules. The irregular words an application needs go in the entries' fields rather than in the grammar. |
| `isPlural($count)` | the `plural` filter with a count | Whether this count takes the plural form. |

The filters give each method a word already turned into text, and read the entry's own fields (`gender`, `number`,
`plural`, `m`, `f`, `mp`, `fp`) before calling the grammar, so a grammar holds only the rules of its language. It must
not throw on a word it does not know: it applies its regular rule, or gives the word back.

A sketch for Dutch, whose definite article is `de`, or `het` for a neuter word:

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

The grammar is given by language, or by locale for a regional variant, beside the built-in ones; a key that names a
built-in language replaces it:

```php
$grammars = new Grammars([
    'nl' => new Dutch(),                           // a new language
    'fr' => new French(['hobgobelin']),            // the built-in French with more aspirated words
    'es' => new Spanish(['ánima']),                // the built-in Spanish with more words with a stressed a
]);
$generator = new Generator($catalog, locale: 'nl', grammars: $grammars);
```

A generator whose `locale` has no grammar is an error when the catalog is checked. The built-in grammars are
`final`: a grammar that differs from one of them only in a few rules wraps it and delegates the rest.
