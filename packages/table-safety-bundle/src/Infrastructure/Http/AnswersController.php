<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use Psr\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Exception\TableSafetyException;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SessionZeroType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\StarsAndWishesType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswers;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswersType;

/**
 * A participant's own answers, each changed at any moment:
 *
 * - `GET|POST /table-safety/{table}/answers`: their lines and veils and their consent checklist;
 * - `GET|POST /table-safety/{table}/session-zero`: their answers to the table's session zero;
 * - `GET|POST /table-safety/{table}/feedback/{session}`: their stars and wishes after a session.
 *
 * A saved form returns to its page; a refused one is shown again with the reason.
 */
final readonly class AnswersController
{
    public const string ANSWERS = 'tilttek_table_safety_answers';

    public const string SESSION_ZERO = 'tilttek_table_safety_session_zero';

    public const string FEEDBACK = 'tilttek_table_safety_feedback';

    public function __construct(
        private Screens $screens,
        private TableSafety $safety,
        private FormFactoryInterface $forms,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function answers(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $participant = $this->screens->participant($reference);
        $settings = $this->safety->settings($reference);
        $themes = $this->safety->themes($reference);
        $participation = $this->safety->participation($reference, $participant, $this->clock->now());
        $forms = [];

        if ($settings->uses(SafetyTool::LinesAndVeils)) {
            $forms['lines_and_veils'] = $this->forms->createNamed('lines_and_veils', ThemeAnswersType::class, ThemeAnswers::grouped($themes, $participation->linesAndVeils->all()), ['themes' => $themes, 'answer' => Boundary::class, 'locale' => $request->getLocale()]);
        }

        if ($settings->uses(SafetyTool::ConsentChecklist)) {
            $forms['consent'] = $this->forms->createNamed('consent', ThemeAnswersType::class, ThemeAnswers::grouped($themes, $participation->consent->all()), ['themes' => $themes, 'answer' => ConsentLevel::class, 'locale' => $request->getLocale()]);
        }

        foreach ($forms as $name => $form) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid() && $this->saved($form, fn (array $data): mixed => 'consent' === $name
                ? $this->safety->answerConsent($reference, $participant, ThemeAnswers::checklist($data), $this->clock->now())
                : $this->safety->answerLinesAndVeils($reference, $participant, ThemeAnswers::linesAndVeils($data), $this->clock->now()))) {
                return $this->back(self::ANSWERS, $reference->toString());
            }
        }

        return $this->screens->render('@TilttekTableSafety/answers.html.twig', [
            'table' => $reference->toString(),
            'forms' => array_map(static fn (FormInterface $form): mixed => $form->createView(), $forms),
            'saved' => $request->query->has('saved'),
        ], $this->status($forms));
    }

    public function sessionZero(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $participant = $this->screens->participant($reference);
        $settings = $this->safety->settings($reference);

        if (!$settings->uses(SafetyTool::SessionZero)) {
            throw new NotFoundHttpException('This table holds no session zero.');
        }

        $answers = $this->safety->participation($reference, $participant, $this->clock->now())->sessionZero->toArray();
        $form = $this->forms->createNamed('session_zero', SessionZeroType::class, $answers, ['template' => $settings->sessionZero]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->saved($form, fn (array $data): mixed => $this->safety->answerSessionZero($reference, $participant, array_filter($data, static fn (mixed $answer): bool => null !== $answer), $this->clock->now()))) {
            return $this->back(self::SESSION_ZERO, $reference->toString());
        }

        return $this->screens->render('@TilttekTableSafety/session_zero.html.twig', [
            'table' => $reference->toString(),
            'form' => $form->createView(),
            'saved' => $request->query->has('saved'),
        ], $this->status([$form]));
    }

    public function feedback(Request $request, string $table, string $session): Response
    {
        $reference = $this->screens->table($table);
        $participant = $this->screens->participant($reference);

        try {
            Feedback::assertSession($session);
        } catch (InvalidAnswer $invalidAnswer) {
            throw new NotFoundHttpException('No such session.', $invalidAnswer);
        }

        if (!$this->safety->settings($reference)->uses(SafetyTool::StarsAndWishes)) {
            throw new NotFoundHttpException('This table gives no stars and wishes.');
        }

        $form = $this->forms->createNamed('stars_and_wishes', StarsAndWishesType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->saved($form, fn (array $data): mixed => $this->safety->giveFeedback($reference, $session, $participant, StarsAndWishesType::feedback($data), $this->clock->now()))) {
            return $this->back(self::FEEDBACK, $reference->toString(), ['session' => $session]);
        }

        return $this->screens->render('@TilttekTableSafety/feedback.html.twig', [
            'table' => $reference->toString(),
            'session' => $session,
            'form' => $form->createView(),
            'saved' => $request->query->has('saved'),
        ], $this->status([$form]));
    }

    /**
     * Saves the form's data; false, with the reason on the form, when the library refuses it.
     *
     * @template T
     *
     * @param FormInterface<T>                         $form
     * @param callable(array<array-key, mixed>): mixed $save
     */
    private function saved(FormInterface $form, callable $save): bool
    {
        $data = $form->getData();

        try {
            $save(\is_array($data) ? $data : []);
        } catch (TableSafetyException $tableSafetyException) {
            $form->addError(new FormError($tableSafetyException->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function back(string $route, string $table, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, ['table' => $table, ...$parameters, 'saved' => 1]), Response::HTTP_SEE_OTHER);
    }

    /**
     * 422 when a submitted form is shown again, 200 otherwise.
     *
     * @template T
     *
     * @param array<array-key, FormInterface<T>> $forms
     */
    private function status(array $forms): int
    {
        return array_any($forms, static fn (FormInterface $form): bool => $form->isSubmitted()) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;
    }
}
