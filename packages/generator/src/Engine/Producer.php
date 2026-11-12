<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Closure;
use InvalidArgumentException;
use LogicException;
use Random\Randomizer;
use Stringable;
use Throwable;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Generator\Definition\Body;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\EntryList;
use Tilttek\Generator\Definition\Field;
use Tilttek\Generator\Definition\GenerateField;
use Tilttek\Generator\Definition\GeneratorDefinition;
use Tilttek\Generator\Definition\GraphBody;
use Tilttek\Generator\Definition\GridBody;
use Tilttek\Generator\Definition\MapLayout;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Definition\ObjectBody;
use Tilttek\Generator\Definition\Template;
use Tilttek\Generator\Definition\TemplateField;
use Tilttek\Generator\Definition\TextBody;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Exception\ExhaustedList;
use Tilttek\Generator\Exception\GenerationFailed;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Exception\UnknownTag;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Grammar;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Timer;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\NodeTraverser;
use Twig\Source;

/**
 * Makes the results of a catalog: compiles its templates in a sandboxed Twig environment, checks them, and renders
 * them with the functions that pick, generate and roll and the filters of the grammars.
 *
 * @internal
 */
final class Producer
{
    /**
     * The most tag filters whose entries a list keeps.
     */
    public const int POOLS_PER_LIST = 64;

    private readonly Environment $twig;

    /**
     * @var array<string, string>
     */
    private readonly array $sources;

    private ?Run $run = null;

    /**
     * @var array<string, list<array{array<string, list<string>>, list<Entry>, list<int>, ?MarkovModel}>> by list, the
     *                                                                                                    tags a pick
     *                                                                                                    filtered on,
     *                                                                                                    the entries
     *                                                                                                    that match
     *                                                                                                    them, their
     *                                                                                                    running
     *                                                                                                    weights and
     *                                                                                                    what a Markov
     *                                                                                                    list learnt
     *                                                                                                    from them
     */
    private array $pools = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Limits $limits,
        private readonly string $locale,
        private readonly Grammars $grammars,
        private readonly DiceRoller $dice,
        private readonly Timer $timer,
        string|false $cache = false,
    ) {
        $this->sources = $this->sources($catalog);
        $this->twig = new Environment(new ArrayLoader($this->sources), ['autoescape' => false, 'strict_variables' => true, 'cache' => $cache]);
        $this->twig->addExtension(new SandboxExtension(new SandboxPolicy(), true));
        $this->twig->addExtension(new GeneratorExtension($this));
    }

    /**
     * Every problem of the catalog: an unknown language, a filter or a generator that does not exist, a dice
     * expression that does not parse, a template that does not compile, leaves the sandbox or names a list, a tag or
     * a generator that does not exist.
     *
     * @return list<Problem>
     */
    public function check(): array
    {
        $problems = [];

        foreach ($this->catalog->generators() as $name => $generator) {
            array_push($problems, ...$this->checkDefinition($name, $generator));
        }

        foreach ($this->sources as $name => $source) {
            array_push($problems, ...$this->checkTemplate($name, $source));
        }

        return $problems;
    }

    /**
     * Compiles every template of a checked catalog, so that the first draw of a template does not spend its time limit
     * compiling it.
     */
    public function compile(): void
    {
        foreach (array_keys($this->sources) as $name) {
            $this->twig->load($name);
        }
    }

    public function draw(string $name, Draw $draw, Randomizer $randomizer): DrawResult
    {
        $definition = $this->catalog->generator($name);

        if ($draw->count > $this->limits->results) {
            throw LimitExceeded::of('results per draw', $this->limits->results);
        }

        // The time of the whole draw counts from here: every result, and every attempt of a unique draw.
        $started = $this->timer->now();

        if ($definition->hasState()) {
            return $this->remember($definition, $draw, $randomizer, $started);
        }

        $make = fn (): mixed => $this->once($randomizer, fn (): mixed => $this->produce($definition), $started);

        return new DrawResult($name, $draw->unique ? $this->distinct($draw->count, $make) : array_map(static fn (): mixed => $make(), range(1, $draw->count)), $draw->count);
    }

    /**
     * `pick('list')`, `pick('list', 2)`, `pick('list', where: {gender: 'f'})`, `pick('list', not: [a, b])`: one entry,
     * or several without duplicates, chosen by weight among those whose tags match and whose text is not excluded.
     *
     * @param array<array-key, mixed> $where
     *
     * @return Entry|list<Entry>
     */
    public function pick(string $list, ?int $count = null, array $where = [], mixed $not = []): Entry|array
    {
        $run = $this->run();
        $entries = $this->catalog->list($list);
        $tags = $this->where($entries, $where);
        $excluded = $this->texts($not);
        [, $candidates, $running, $model] = $this->pool($entries, $tags);

        if ($model instanceof MarkovModel) {
            return $this->invent($list, $model, $tags, $count, $excluded);
        }

        if (null === $count && [] === $excluded && [] !== $candidates) {
            $run->tick();

            return $candidates[$this->choose($running, $run->randomizer)];
        }

        $candidates = array_values(array_filter($candidates, static fn (Entry $entry): bool => !\in_array($entry->text, $excluded, true)));
        $picked = [];

        while (\count($picked) < ($count ?? 1) && [] !== $candidates) {
            $run->tick();
            $entry = $candidates[$this->weighted(array_map(static fn (Entry $entry): int => $entry->weight, $candidates), $run->randomizer)];
            $picked[] = $entry;
            $candidates = array_values(array_filter($candidates, static fn (Entry $candidate): bool => $candidate->text !== $entry->text));
        }

        if (null !== $count) {
            return $picked;
        }

        return $picked[0] ?? throw ExhaustedList::named($list);
    }

    /**
     * `generate('npc')`, `generate('dish', 3)`: another generator's result, or several without duplicates.
     */
    public function generate(string $name, ?int $count = null): mixed
    {
        $definition = $this->catalog->generator($name);

        if (null === $count) {
            return $this->produce($definition);
        }

        return $this->distinct($count, fn (): mixed => $this->produce($definition));
    }

    /**
     * `roll('2d6')`: the total of a roll of `tilttek/dice`, with the draw's randomness; the first total of a pool. A
     * roll of custom dice whose faces have no value has no total, and fails the template.
     */
    public function roll(string $expression): int
    {
        $run = $this->run();
        $run->tick();

        return $this->dice->roll($this->dice->parse($expression), $run->randomizer)->totals()[0] ?? throw new InvalidArgumentException(\sprintf('The roll "%s" has no total.', $expression));
    }

    public function upperFirst(mixed $text): string
    {
        $text = $this->text($text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    public function elide(mixed $text): string
    {
        return $this->grammar()->elide($this->text($text));
    }

    /**
     * `word|article`, `word|article('indefinite')`, `word|article('definite', 'f', true)`: the word with its article,
     * by its `gender` field unless a gender is given, in the plural when asked.
     */
    public function article(mixed $subject, string $kind = 'definite', ?string $gender = null, bool $plural = false): string
    {
        $word = $plural ? $this->plural($subject) : $this->text($subject);
        $gender ??= $subject instanceof Entry ? $subject->grammar('gender') : null;

        return $this->grammar()->article($word, ArticleKind::from($kind), Gender::from($gender ?? 'm'), $plural);
    }

    /**
     * `adjective|agree(noun)`, `adjective|agree('f')`, `adjective|agree('f', true)`: the adjective in the gender and
     * number of a noun entry, or of those given; its `f`, `mp` and `fp` fields first, the language's rules otherwise.
     */
    public function agree(mixed $subject, mixed $with = null, bool $plural = false): string
    {
        $gender = $with instanceof Entry ? $with->grammar('gender') : (null === $with ? null : $this->text($with));
        $plural = $plural || ($with instanceof Entry && 'p' === $with->grammar('number'));
        $feminine = Gender::Feminine === Gender::from($gender ?? 'm');
        $form = $subject instanceof Entry ? $subject->grammar(($feminine ? 'f' : 'm').($plural ? 'p' : '')) : null;

        if (null !== $form) {
            return $form;
        }

        $singular = $this->text($subject);

        if ($feminine) {
            $singular = ($subject instanceof Entry ? $subject->grammar('f') : null) ?? $this->grammar()->feminine($singular);
        }

        return $plural ? $this->grammar()->plural($singular) : $singular;
    }

    /**
     * `word|plural`, `word|plural(count)`: the plural of a word — its `plural` field first — or the word itself when
     * the count takes the singular.
     */
    public function plural(mixed $subject, ?int $count = null): string
    {
        if (null !== $count && !$this->grammar()->isPlural($count)) {
            return $this->text($subject);
        }

        return ($subject instanceof Entry ? $subject->grammar('plural') : null) ?? $this->grammar()->plural($this->text($subject));
    }

    /**
     * The template names of a catalog and their sources: `name:template:0`, `name:var:gender`, `name:field:keeper`,
     * `name:cell`, `name:state:weather:start`, `name:state:weather:next`. A text generator's filters wrap each of its
     * templates.
     *
     * @return array<string, string>
     */
    private function sources(Catalog $catalog): array
    {
        $sources = [];

        foreach ($catalog->generators() as $name => $generator) {
            $sources += $this->stateSources($name, $generator);

            foreach ($generator->vars as $var => $source) {
                $sources[$name.':var:'.$var] = $source;
            }

            $sources += $this->bodySources($name, $generator->body);
        }

        return $sources;
    }

    /**
     * @return array<string, string>
     */
    private function stateSources(string $name, GeneratorDefinition $generator): array
    {
        $sources = [];

        foreach ($generator->state as $value => $state) {
            $sources[$name.':state:'.$value.':start'] = $state->start;

            if (null !== $state->next) {
                $sources[$name.':state:'.$value.':next'] = $state->next;
            }
        }

        return $sources;
    }

    /**
     * @return array<string, string>
     */
    private function bodySources(string $name, Body $body): array
    {
        $sources = [];

        if ($body instanceof TextBody) {
            $filters = implode('|', array_filter($body->filters, static fn (string $filter): bool => 1 === preg_match('/^[a-z_][a-z0-9_]*$/', $filter)));

            foreach ($body->templates as $index => $template) {
                $sources[$name.':template:'.$index] = '' === $filters ? $template->source : '{% apply '.$filters.' %}'.$template->source.'{% endapply %}';
            }
        }

        if ($body instanceof ObjectBody) {
            foreach ($body->fields as $field => $value) {
                if ($value instanceof TemplateField) {
                    $sources[$name.':field:'.$field] = $value->source;
                }
            }
        }

        if (($body instanceof GridBody || $body instanceof GraphBody) && $body->cell instanceof TemplateField) {
            $sources[$name.':cell'] = $body->cell->source;
        }

        return $sources;
    }

    /**
     * @return list<Problem>
     */
    private function checkDefinition(string $name, GeneratorDefinition $generator): array
    {
        $problems = [];

        if (null !== $generator->locale && !$this->grammars->has($generator->locale)) {
            $problems[] = new Problem($name, \sprintf('no grammar is known for the language "%s"', $generator->locale));
        }

        $body = $generator->body;
        $fields = $body instanceof ObjectBody ? $body->fields : [];

        if ($body instanceof TextBody) {
            foreach ($body->filters as $filter) {
                if (1 !== preg_match('/^[a-z_][a-z0-9_]*$/', $filter)) {
                    $problems[] = new Problem($name, \sprintf('"%s" is not a filter name', $filter));
                }
            }
        }

        if ($body instanceof GridBody || $body instanceof GraphBody) {
            $fields['cell'] = $body->cell;
        }

        if ($body instanceof GraphBody && \is_string($body->nodes)) {
            try {
                $this->dice->parse($body->nodes);
            } catch (DiceException $exception) {
                $problems[] = new Problem($name, \sprintf('the number of nodes "%s" does not roll: %s', $body->nodes, $exception->getMessage()));
            }
        }

        foreach ($fields as $field => $value) {
            if ($value instanceof GenerateField && !$this->catalog->hasGenerator($value->generator)) {
                $problems[] = new Problem($name.':'.$field, \sprintf('the generator "%s" does not exist', $value->generator));
            }
        }

        return $problems;
    }

    /**
     * @return list<Problem>
     */
    private function checkTemplate(string $name, string $source): array
    {
        $collector = new ReferenceCollector();

        try {
            new NodeTraverser($this->twig, [$collector])->traverse($this->twig->parse($this->twig->tokenize(new Source($source, $name))));
            new SandboxPolicy()->checkSecurity($collector->tags, $collector->filters, $collector->functions, $collector->tests);
        } catch (Error $error) {
            return [new Problem($name, $error->getRawMessage())];
        }

        $problems = $collector->range ? [new Problem($name, 'the range operator ".." is not allowed')] : [];

        foreach ($collector->references as $reference) {
            if ('generate' === $reference['function']) {
                if (!$this->catalog->hasGenerator($reference['name'])) {
                    $problems[] = new Problem($name, \sprintf('the generator "%s" does not exist', $reference['name']));
                }

                continue;
            }

            if (!$this->catalog->hasList($reference['name'])) {
                $problems[] = new Problem($name, \sprintf('the list "%s" does not exist', $reference['name']));

                continue;
            }

            foreach ($reference['tags'] as $tag) {
                if (!$this->catalog->list($reference['name'])->hasTag($tag)) {
                    $problems[] = new Problem($name, \sprintf('no entry of the list "%s" has the tag "%s"', $reference['name'], $tag));
                }
            }
        }

        return $problems;
    }

    private function produce(GeneratorDefinition $definition): mixed
    {
        return $this->make($definition, null)[0];
    }

    /**
     * One result and the state it leaves: the state first, from the previous one, then the variables, then the body.
     *
     * @param array<string, string>|null $previous
     *
     * @return array{mixed, array<string, string>}
     */
    private function make(GeneratorDefinition $definition, ?array $previous): array
    {
        $run = $this->run();
        $run->enter($definition->locale ?? $this->locale);

        try {
            $state = $this->advance($definition, $previous);
            $context = $definition->hasState() ? ['previous' => $previous ?? [], 'state' => $state] : [];

            return [$this->body($definition, $context), $state];
        } finally {
            $run->leave();
        }
    }

    /**
     * The state after one result: each value, in order, given by its start template when the previous state has none,
     * by its next template otherwise, or kept. Its templates see the previous state as `previous`, and as `state` the
     * values given so far over the previous ones.
     *
     * @param array<string, string>|null $previous
     *
     * @return array<string, string>
     */
    private function advance(GeneratorDefinition $definition, ?array $previous): array
    {
        $previous ??= [];
        $current = array_intersect_key($previous, $definition->state);

        foreach ($definition->state as $name => $value) {
            $kind = \array_key_exists($name, $previous) ? (null === $value->next ? null : 'next') : 'start';

            if (null === $kind) {
                continue;
            }

            $text = $this->render($definition->name.':state:'.$name.':'.$kind, ['previous' => $previous, 'state' => $current]);

            if (mb_strlen($text) > Draw::STATE_LENGTH) {
                throw LimitExceeded::of('characters per state value', Draw::STATE_LENGTH);
            }

            $current[$name] = $text;
        }

        return array_replace(array_map(static fn (): string => '', $definition->state), $current);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function body(GeneratorDefinition $definition, array $context): mixed
    {
        foreach (array_keys($definition->vars) as $var) {
            $context[$var] = $this->render($definition->name.':var:'.$var, $context);
        }

        $body = $definition->body;

        if ($body instanceof TextBody) {
            $index = $this->weighted(array_map(static fn (Template $template): int => $template->weight, $body->templates), $this->run()->randomizer);

            return $this->render($definition->name.':template:'.$index, $context);
        }

        if ($body instanceof ObjectBody) {
            $result = [];

            foreach ($body->fields as $name => $field) {
                $result[$name] = $context[$name] = $this->fill($field, $definition->name.':field:'.$name, $context);
            }

            return $result;
        }

        if ($body instanceof GridBody) {
            return $this->grid($definition->name, $body, $context);
        }

        if ($body instanceof GraphBody) {
            return $this->graph($definition->name, $body, $context);
        }

        throw new LogicException(\sprintf('The generator "%s" has a body of an unknown kind.', $definition->name));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function fill(Field $field, string $template, array $context): mixed
    {
        if ($field instanceof GenerateField) {
            return $this->generate($field->generator, $field->count);
        }

        return $this->render($template, $context);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{layout: string, width: int, height: int, cells: list<array<string, mixed>>}
     */
    private function grid(string $name, GridBody $body, array $context): array
    {
        if ($body->width * $body->height > $this->limits->cells) {
            throw LimitExceeded::of('cells per map', $this->limits->cells);
        }

        $cells = [];

        for ($y = 0; $y < $body->height; ++$y) {
            for ($x = 0; $x < $body->width; ++$x) {
                $place = MapLayout::Square === $body->layout ? ['x' => $x, 'y' => $y] : ['col' => $x, 'row' => $y, 'q' => $x - intdiv($y, 2), 'r' => $y];
                $cells[] = $place + ['value' => $this->fill($body->cell, $name.':cell', $place + $context)];
            }
        }

        return ['layout' => $body->layout->value, 'width' => $body->width, 'height' => $body->height, 'cells' => $cells];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{layout: string, nodes: list<array{id: int, value: mixed}>, edges: list<array{int, int}>}
     */
    private function graph(string $name, GraphBody $body, array $context): array
    {
        $count = max(1, \is_int($body->nodes) ? $body->nodes : $this->roll($body->nodes));

        if ($count > $this->limits->cells) {
            throw LimitExceeded::of('cells per map', $this->limits->cells);
        }

        $randomizer = $this->run()->randomizer;
        $nodes = [];
        $edges = [];

        for ($id = 0; $id < $count; ++$id) {
            $nodes[] = ['id' => $id, 'value' => $this->fill($body->cell, $name.':cell', ['index' => $id, 'count' => $count] + $context)];

            if ($id > 0) {
                $edges[($other = $randomizer->getInt(0, $id - 1)).'-'.$id] = [$other, $id];
            }
        }

        $extra = min($body->extraEdges, intdiv($count * ($count - 1), 2) - ($count - 1));

        while ($extra > 0) {
            $this->run()->tick();
            $one = $randomizer->getInt(0, $count - 1);
            $other = $randomizer->getInt(0, $count - 1);
            $pair = [min($one, $other), max($one, $other)];

            if ($pair[0] !== $pair[1] && !isset($edges[$pair[0].'-'.$pair[1]])) {
                $edges[$pair[0].'-'.$pair[1]] = $pair;
                --$extra;
            }
        }

        ksort($edges, \SORT_NATURAL);

        return ['layout' => 'graph', 'nodes' => $nodes, 'edges' => array_values($edges)];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        $this->run()->checkTime();

        try {
            $output = $this->twig->render($template, $context);
        } catch (Error $error) {
            throw $this->unwrap($template, $error);
        }

        return trim(preg_replace('/\s+/u', ' ', $output) ?? $output);
    }

    private function unwrap(string $template, Error $error): Throwable
    {
        for ($cause = $error->getPrevious(); $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof GeneratorException) {
                return $cause;
            }
        }

        return GenerationFailed::in($template, $error->getPrevious() ?? $error);
    }

    /**
     * Makes one result in a run of its own: a long-running process keeps no randomizer from one draw to the next.
     *
     * @template T
     *
     * @param Closure(): T $work
     * @param float        $started when the draw started
     *
     * @return T
     */
    private function once(Randomizer $randomizer, Closure $work, float $started): mixed
    {
        $this->run = new Run($randomizer, $this->limits, $this->timer, $this->locale, $started);

        try {
            return $work();
        } finally {
            $this->run = null;
        }
    }

    /**
     * The draw of a generator with a state: each result starts from the state the one before left — the first from the
     * state the draw brings, or from nothing — and the draw returns the state of its last result. A unique draw drops a
     * result it already has, and the state that result left.
     */
    private function remember(GeneratorDefinition $definition, Draw $draw, Randomizer $randomizer, float $started): DrawResult
    {
        $state = $draw->state;
        $results = [];
        $seen = [];
        $attempts = $draw->unique ? $draw->count * $this->limits->attempts : $draw->count;

        for ($attempt = 0; $attempt < $attempts && \count($results) < $draw->count; ++$attempt) {
            [$result, $next] = $this->once($randomizer, fn (): array => $this->make($definition, $state), $started);

            if ($draw->unique) {
                $key = json_encode($result, \JSON_THROW_ON_ERROR);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
            }

            $results[] = $result;
            $state = $next;
        }

        return new DrawResult($definition->name, $results, $draw->count, $state);
    }

    /**
     * Words a Markov list invents, one or several without duplicates, none of them excluded and — unless the chain
     * allows it — none of them an entry; each attempt counts as a pick. An invented word carries the tags the pick
     * filtered on.
     *
     * @param array<string, list<string>> $tags
     * @param list<string>                $excluded
     *
     * @return Entry|list<Entry>
     */
    private function invent(string $list, MarkovModel $model, array $tags, ?int $count, array $excluded): Entry|array
    {
        $run = $this->run();
        $taken = array_fill_keys(array_map(mb_strtolower(...), $excluded), true);
        $picked = [];

        for ($attempt = 0; $attempt < ($count ?? 1) * MarkovChain::ATTEMPTS && \count($picked) < ($count ?? 1); ++$attempt) {
            $run->tick();
            $word = $model->word($run->randomizer);
            $key = mb_strtolower((string) $word);

            if (null === $word || isset($taken[$key]) || (!$model->chain->allowEntries && $model->knows($key))) {
                continue;
            }

            $taken[$key] = true;
            $picked[] = new Entry($word, 1, [], $tags);
        }

        if (null !== $count) {
            return $picked;
        }

        return $picked[0] ?? throw ExhaustedList::invented($list, MarkovChain::ATTEMPTS);
    }

    /**
     * Results made one after the other until there are enough different ones, or until the attempts the limits allow
     * are spent.
     *
     * @param Closure(): mixed $make
     *
     * @return list<mixed>
     */
    private function distinct(int $count, Closure $make): array
    {
        $results = [];

        for ($attempt = 0; $attempt < $count * $this->limits->attempts && \count($results) < $count; ++$attempt) {
            $result = $make();
            $results[json_encode($result, \JSON_THROW_ON_ERROR)] = $result;
        }

        return array_values($results);
    }

    /**
     * An index chosen by weight; a single choice takes no randomness.
     *
     * @param list<int> $weights
     */
    private function weighted(array $weights, Randomizer $randomizer): int
    {
        if (1 === \count($weights)) {
            return 0;
        }

        $roll = $randomizer->getInt(1, array_sum($weights));

        foreach ($weights as $index => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $index;
            }
        }

        throw new LogicException('A weighted choice always finds an index.');
    }

    /**
     * The entries of a list whose tags match, their running weights and, for a Markov list, what its chain learnt from
     * them: computed at the first pick that filters the
     * list on these tags, and kept, since a catalog does not change. A list keeps at most `POOLS_PER_LIST` of them, so
     * that a long-running process does not grow with tags drawn at random; the others are computed at each pick.
     *
     * @param array<string, list<string>> $tags
     *
     * @return array{array<string, list<string>>, list<Entry>, list<int>, ?MarkovModel}
     */
    private function pool(EntryList $list, array $tags): array
    {
        $pool = array_find($this->pools[$list->name] ?? [], static fn (array $pool): bool => $pool[0] === $tags);

        if (null === $pool) {
            $candidates = array_values(array_filter($list->entries, static fn (Entry $entry): bool => $entry->matches($tags)));
            $running = [];
            $total = 0;

            foreach ($candidates as $entry) {
                $running[] = $total += $entry->weight;
            }

            $pool = [$tags, $candidates, $running, $list->markov instanceof MarkovChain && [] !== $candidates ? MarkovModel::learn($list->markov, $candidates) : null];

            if (\count($this->pools[$list->name] ?? []) < self::POOLS_PER_LIST) {
                $this->pools[$list->name][] = $pool;
            }
        }

        return $pool;
    }

    /**
     * The index an entry is chosen at by weight, from the running weights: the same index, for the same roll, as
     * `weighted()` finds by walking the weights.
     *
     * @param list<int> $running
     */
    private function choose(array $running, Randomizer $randomizer): int
    {
        if (1 === \count($running)) {
            return 0;
        }

        $high = \count($running) - 1;
        $roll = $randomizer->getInt(1, $running[$high]);
        $low = 0;

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($running[$middle] < $roll) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * @param array<array-key, mixed> $where
     *
     * @return array<string, list<string>>
     */
    private function where(EntryList $list, array $where): array
    {
        $tags = [];

        foreach ($where as $tag => $values) {
            $tag = (string) $tag;

            if (!$list->hasTag($tag)) {
                throw UnknownTag::in($list->name, $tag);
            }

            $tags[$tag] = $this->texts($values);
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    private function texts(mixed $values): array
    {
        $texts = [];
        $values = \is_array($values) ? $values : [$values];
        array_walk_recursive($values, function (mixed $value) use (&$texts): void {
            $texts[] = $this->text($value);
        });

        return $texts;
    }

    private function text(mixed $value): string
    {
        if (\is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(\sprintf('A text is expected, not %s.', get_debug_type($value)));
    }

    private function grammar(): Grammar
    {
        return $this->grammars->for($this->run()->locale());
    }

    private function run(): Run
    {
        return $this->run ?? throw new LogicException('The generator functions are called during a draw only.');
    }
}
