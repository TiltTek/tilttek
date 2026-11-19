<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Http;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tilttek\DiceTower\Application\Contract\TableAccess;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\TowerEntry;

/**
 * `GET /dice-tower/{table}/entries?after=12`: the entries of a table's tower after a position — what a viewer missed
 * while disconnected —, or the last ones without `after`, as `{"entries": [...]}`. Only a viewer the application's
 * `TableAccess` allows reads them; the answer is never cached.
 */
final readonly class TowerEntriesController
{
    public const string ROUTE = 'tilttek_dice_tower_entries';

    /** A table's reference in the path: a kind, a colon and an identifier without a slash. */
    public const string TABLE = '[a-z][a-z0-9_.-]{0,63}:[^/]{1,255}';

    public function __construct(
        private DiceTower $tower,
        private TableAccess $access,
    ) {
    }

    public function __invoke(Request $request, string $table): JsonResponse
    {
        try {
            $reference = ResourceReference::fromString($table);
        } catch (InvalidResource $invalidResource) {
            throw new NotFoundHttpException('No such table.', $invalidResource);
        }

        if (!$this->access->canWatch($reference)) {
            throw new AccessDeniedHttpException('This tower is not yours to watch.');
        }

        $after = $request->query->filter('after', null, \FILTER_VALIDATE_INT, ['flags' => \FILTER_NULL_ON_FAILURE, 'options' => ['min_range' => 0]]);
        $entries = $this->tower->entries($reference, \is_int($after) ? $after : null);
        $response = new JsonResponse(['entries' => array_map(static fn (TowerEntry $entry): array => $entry->toArray(), $entries)]);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
