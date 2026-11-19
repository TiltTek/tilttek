<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\TableSafety\Exception\NotGameMaster;
use Tilttek\TableSafety\Exception\ToolNotEnabled;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\SignalSubscriptions;

/**
 * The table's screen during play and its signals:
 *
 * - `GET /table-safety/{table}`: what the table agreed, its tools, the signals the table sees, and a button per
 *   signal — plain forms that work without JavaScript;
 * - `POST /table-safety/{table}/signals`: raises a signal, then returns to the screen, or answers the JSON of the signal
 *   to a script;
 * - `GET /table-safety/{table}/signals?after=…`: the signals the viewer sees, after a moment, as JSON;
 * - `POST /table-safety/{table}/signals/{id}/acknowledge`: the game master marks a signal as seen.
 */
final readonly class TableController
{
    public const string SCREEN = 'tilttek_table_safety_table';

    public const string RAISE = 'tilttek_table_safety_raise';

    public const string FEED = 'tilttek_table_safety_signals';

    public const string ACKNOWLEDGE = 'tilttek_table_safety_acknowledge';

    public function __construct(
        private Screens $screens,
        private TableSafety $safety,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private Views $views,
        private SignalSubscriptions $subscriptions,
    ) {
    }

    public function screen(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->participant($reference);
        $gameMaster = $this->screens->isGameMaster($reference);

        return $this->screens->render('@TilttekTableSafety/table.html.twig', [
            'table' => $reference->toString(),
            'game_master' => $gameMaster,
            'tools' => $this->safety->settings($reference)->tools,
            'signal_kinds' => $this->views->signalKinds($reference, $gameMaster),
            'agreement' => $this->views->agreement($reference, $request->getLocale(), $gameMaster),
            'signals' => $this->safety->signals($reference, false),
            'live' => $this->views->live($reference, false, $this->subscriptions->subscribe($reference, false)),
        ]);
    }

    public function raise(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $participant = $this->screens->participant($reference);
        $this->screens->assertToken($request, Screens::SIGNAL_TOKEN);
        $kind = SignalKind::tryFrom($request->request->getString('kind')) ?? throw new BadRequestHttpException('No such signal.');

        try {
            $signal = $this->safety->raise($reference, $participant, $kind, $this->screens->isGameMaster($reference), $this->clock->now());
        } catch (ToolNotEnabled $toolNotEnabled) {
            throw new BadRequestHttpException($toolNotEnabled->getMessage(), $toolNotEnabled);
        } catch (NotGameMaster $notGameMaster) {
            throw new AccessDeniedHttpException($notGameMaster->getMessage(), $notGameMaster);
        }

        if (Screens::wantsJson($request)) {
            return Screens::private(new JsonResponse(['signal' => $signal->toArray()], Response::HTTP_CREATED));
        }

        $route = 'game-master' === $request->request->getString('return') && $this->screens->isGameMaster($reference) ? GameMasterController::SCREEN : self::SCREEN;

        return new RedirectResponse($this->urls->generate($route, ['table' => $reference->toString(), 'raised' => $kind->value]), Response::HTTP_SEE_OTHER);
    }

    public function feed(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->participant($reference);
        $after = $request->query->getString('after');

        try {
            $since = '' === $after ? null : new DateTimeImmutable($after);
        } catch (DateMalformedStringException $dateMalformedStringException) {
            throw new BadRequestHttpException('The moment "after" is not a date.', $dateMalformedStringException);
        }

        $signals = $this->safety->signals($reference, $this->screens->isGameMaster($reference), $since);

        return Screens::private(new JsonResponse(['signals' => array_map(static fn (Signal $signal): array => $signal->toArray(), $signals)]));
    }

    public function acknowledge(Request $request, string $table, string $id): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->assertGameMaster($reference);
        $this->screens->assertToken($request, Screens::ACKNOWLEDGE_TOKEN);

        if (!$this->safety->acknowledge($reference, $id, $this->clock->now())) {
            throw new NotFoundHttpException('No such signal waits to be seen.');
        }

        if (Screens::wantsJson($request)) {
            return Screens::private(new JsonResponse(null, Response::HTTP_NO_CONTENT));
        }

        return new RedirectResponse($this->urls->generate(GameMasterController::SCREEN, ['table' => $reference->toString()]), Response::HTTP_SEE_OTHER);
    }
}
