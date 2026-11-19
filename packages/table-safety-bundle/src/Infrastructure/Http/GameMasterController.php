<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\TableSafety\Exception\TableSafetyException;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafetyBundle\Infrastructure\Form\TableSettingsType;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\SignalSubscriptions;

/**
 * `GET|POST /table-safety/{table}/game-master`: the game master's screen — every signal, discreet ones included, each
 * to mark as seen; the answers to the last check-in; what the table agreed and its pact; the stars and wishes of a
 * session (`?session=…`); and the table's settings. Nothing on it says who gave an answer or raised an anonymous
 * signal.
 */
final readonly class GameMasterController
{
    public const string SCREEN = 'tilttek_table_safety_game_master';

    public function __construct(
        private Screens $screens,
        private TableSafety $safety,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private Views $views,
        private SignalSubscriptions $subscriptions,
    ) {
    }

    public function __invoke(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->assertGameMaster($reference);
        $settings = $this->safety->settings($reference);
        $form = $this->forms->createNamed('table_settings', TableSettingsType::class, TableSettingsType::data($settings));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->safety->configure($reference, TableSettingsType::settings($settings, $data));

                return new RedirectResponse($this->urls->generate(self::SCREEN, ['table' => $reference->toString(), 'saved' => 1]), Response::HTTP_SEE_OTHER);
            } catch (TableSafetyException $tableSafetyException) {
                $form->addError(new FormError($tableSafetyException->getMessage()));
            }
        }

        $session = $request->query->getString('session');

        try {
            Feedback::assertSession($session);
            $feedback = $this->safety->feedback($reference, $session);
        } catch (TableSafetyException) {
            $session = '';
            $feedback = null;
        }

        return $this->screens->render('@TilttekTableSafety/game_master.html.twig', [
            'table' => $reference->toString(),
            'settings_form' => $form->createView(),
            'saved' => $request->query->has('saved'),
            'signal_kinds' => $this->views->signalKinds($reference, true),
            'signals' => array_reverse($this->safety->signals($reference, true)),
            'check_in' => $this->safety->checkIn($reference),
            'agreement' => $this->views->agreement($reference, $request->getLocale(), true),
            'pact' => $this->views->pact($reference),
            'session' => $session,
            'feedback' => $feedback,
            'live' => $this->views->live($reference, true, $this->subscriptions->subscribe($reference, true)),
        ], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
