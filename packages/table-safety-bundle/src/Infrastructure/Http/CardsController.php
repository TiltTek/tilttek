<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafetyBundle\Infrastructure\Print\SafetyCardsPdf;

/**
 * The printable cards of a table — the X-card, the sheet of the lines and veils and that of the consent checklist —,
 * for the tools the table uses, blank, for a participant to fill by hand:
 *
 * - `GET /table-safety/{table}/cards`: a page to print, without JavaScript;
 * - `GET /table-safety/{table}/cards.pdf`: the same cards as a PDF, when `phil/paged-media` is installed.
 */
final readonly class CardsController
{
    public const string CARDS = 'tilttek_table_safety_cards';

    public const string PDF = 'tilttek_table_safety_cards_pdf';

    public function __construct(
        private Screens $screens,
        private Views $views,
        private ClockInterface $clock,
        private ?SafetyCardsPdf $pdf = null,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function cards(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->participant($reference);
        $tools = $this->views->printedTools($reference);

        return $this->screens->render('@TilttekTableSafety/cards.html.twig', [
            'table' => $reference->toString(),
            'game_master' => $this->screens->isGameMaster($reference),
            'tools' => array_map(static fn (SafetyTool $tool): string => $tool->value, $tools),
            'groups' => $this->views->printableThemes($reference, $request->getLocale()),
            'pdf' => $this->pdf instanceof SafetyCardsPdf && [] !== $tools,
        ]);
    }

    public function pdf(Request $request, string $table): Response
    {
        $reference = $this->screens->table($table);
        $this->screens->participant($reference);
        $tools = $this->views->printedTools($reference);

        if (!$this->pdf instanceof SafetyCardsPdf || [] === $tools) {
            throw new NotFoundHttpException('This table has no printable card.');
        }

        $locale = $request->getLocale();
        $body = $this->pdf->render($tools, $this->views->printableThemes($reference, $locale), $this->labels($locale), $this->clock->now());

        return Screens::private(new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="table-safety-cards.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    /**
     * The labels of the PDF in the viewer's locale; English without a translator.
     *
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return [];
        }

        $labels = [];

        foreach (array_keys(SafetyCardsPdf::LABELS) as $key) {
            $labels[$key] = $this->translator->trans('cards.'.$key, [], 'tilttek_table_safety', $locale);
        }

        return $labels;
    }
}
