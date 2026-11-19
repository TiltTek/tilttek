<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Http;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\BoxState;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendar\Exception\BoxNotOpenable;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\Celebrations;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Application\Members;
use Tilttek\AdventCalendarBundle\Application\VisitorBoxes;
use Twig\Environment;

/**
 * A calendar's screen and the opening of its boxes:
 *
 * - `GET /advent-calendar/{calendar}`: every box as the viewer sees it — its day and date, locked, openable, opened
 *   with its surprise, or missed —, each openable box a plain form that works without JavaScript; a visitor sees the
 *   boxes and opens none;
 * - `POST /advent-calendar/{calendar}/boxes/{day}`: opens a box for the member, then returns to the screen at that
 *   box, or answers its JSON and its HTML to the Stimulus controller; a box that may not be opened now answers 409 with
 *   the reason.
 *
 * The first opening of a box is celebrated by the kit's `Celebration` when the configuration enables it. Every answer
 * is private and never cached.
 */
final readonly class CalendarController
{
    public const string SCREEN = 'tilttek_advent_calendar_screen';

    public const string OPEN = 'tilttek_advent_calendar_open';

    public const string TOKEN = 'tilttek_advent_calendar_open';

    /** A calendar's identifier in the path, as the library writes it. */
    public const string CALENDAR = '[a-z0-9][a-z0-9_.-]{0,63}';

    public function __construct(
        private CalendarCatalogue $calendars,
        private AdventCalendar $advent,
        private Members $members,
        private Celebrations $celebrations,
        private ClockInterface $clock,
        private Environment $twig,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private bool $celebrate,
        private string $effect,
    ) {
    }

    public function screen(Request $request, string $calendar): Response
    {
        $entry = $this->entry($calendar);
        $member = $this->members->current();
        $boxes = $member instanceof ResourceReference ? $this->advent->boxes($entry->calendar, $member) : VisitorBoxes::of($entry->calendar, $this->clock->now());
        $opened = $request->query->getInt('opened');
        $celebrated = array_find($boxes, static fn (BoxView $box): bool => $box->day === $opened && BoxState::Opened === $box->state);

        return $this->render($entry, $boxes, $member instanceof ResourceReference, $celebrated);
    }

    public function open(Request $request, string $calendar, int $day): Response
    {
        $entry = $this->entry($calendar);
        $member = $this->members->current() ?? throw new AccessDeniedHttpException('Sign in to open a box.');

        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('The form has expired; reload the page.');
        }

        if ($day > $entry->calendar->days()) {
            throw new NotFoundHttpException('No such box.');
        }

        try {
            $opened = $this->advent->open($entry->calendar, $member, $day);
        } catch (BoxNotOpenable $boxNotOpenable) {
            return $this->refused($request, $entry, $member, $day, $boxNotOpenable);
        }

        $celebrate = $this->celebrations->take($entry->calendar->id, $member->toString(), $day);
        $box = $this->advent->box($entry->calendar, $member, $day);

        if ('json' === $request->getPreferredFormat()) {
            return $this->private(new JsonResponse([
                'day' => $day,
                'first' => $opened->first,
                'celebrate' => $celebrate && $this->celebrate,
                'html' => $this->twig->render('@TilttekAdventCalendar/_box.html.twig', ['calendar' => $entry->calendar, 'box' => $box, 'member' => true]),
            ]));
        }

        $parameters = ['calendar' => $entry->calendar->id] + ($celebrate ? ['opened' => $day] : []);

        return new RedirectResponse($this->urls->generate(self::SCREEN, $parameters).'#tilttek-advent-calendar-box-'.$day, Response::HTTP_SEE_OTHER);
    }

    private function refused(Request $request, CalendarEntry $entry, ResourceReference $member, int $day, BoxNotOpenable $refusal): Response
    {
        $box = $this->advent->box($entry->calendar, $member, $day);

        if ('json' === $request->getPreferredFormat()) {
            return $this->private(new JsonResponse(['day' => $day, 'state' => $box->state->value, 'error' => $refusal->getMessage()], Response::HTTP_CONFLICT));
        }

        return $this->render($entry, $this->advent->boxes($entry->calendar, $member), true, null, $box, Response::HTTP_CONFLICT);
    }

    /**
     * @param list<BoxView> $boxes
     */
    private function render(CalendarEntry $entry, array $boxes, bool $member, ?BoxView $celebrated, ?BoxView $refused = null, int $status = Response::HTTP_OK): Response
    {
        return $this->private(new Response($this->twig->render('@TilttekAdventCalendar/calendar.html.twig', [
            'layout' => $this->layout,
            'calendar' => $entry->calendar,
            'title' => $entry->title,
            'boxes' => $boxes,
            'member' => $member,
            'celebration' => $this->celebrate ? ['effect' => $this->effect, 'box' => $celebrated] : null,
            'refused' => $refused,
        ]), $status));
    }

    private function entry(string $calendar): CalendarEntry
    {
        return $this->calendars->find($calendar) ?? throw new NotFoundHttpException('No such calendar.');
    }

    private function private(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
