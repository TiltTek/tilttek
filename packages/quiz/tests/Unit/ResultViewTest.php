<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;
use Tilttek\Quiz\View\ResultView;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

#[CoversClass(ResultView::class)]
final class ResultViewTest extends TestCase
{
    public function testAPlayIsShownWithItsScoreOutOfTheMost(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $play = Play::start('p1', $quiz, Player::member('m1'), $now);

        $running = ResultView::ofPlay($play, $quiz);
        self::assertFalse($running->completed);
        self::assertFalse($running->perfect);

        $play->answer($quiz, 'm1', ChoiceAnswer::of('b'), $now);
        $play->answer($quiz, 'm1', new NumberAnswer(1974), $now);

        $view = ResultView::ofPlay($play, $quiz);

        self::assertEquals(new ResultView('trivia', 'p1', 'm1', 300, 300, true, true), $view);
        self::assertNull($view->rank);
    }

    public function testALiveRunIsShownWithItsRank(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $session = LiveSession::open('s1', $quiz, '123456');
        $session->join(Player::member('m1'));
        $session->join(Player::member('m2'));
        $session->next($quiz, $now);
        $session->answer($quiz, 'm2', ChoiceAnswer::of('b'), $now);
        $session->close($quiz, $now);

        self::assertEquals(new ResultView('trivia', 's1', 'm1', 0, 300, false, false, 2), ResultView::ofLive($session, $quiz, 'm1'));
        $session->finish();
        self::assertEquals(new ResultView('trivia', 's1', 'm2', 100, 300, true, false, 1), ResultView::ofLive($session, $quiz, 'm2'));

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The player m9 has not joined the live session s1.');
        ResultView::ofLive($session, $quiz, 'm9');
    }

    public function testALiveRunPlayedByTeamsIsShownWithItsTeam(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $session = LiveSession::open('s1', $quiz, '123456', ['red', 'blue']);
        $session->join(Player::member('m1'), 'red');
        $session->join(Player::member('m2'), 'blue');
        $session->join(Player::member('m3'), 'blue');
        $session->next($quiz, $now);
        $session->answer($quiz, 'm2', ChoiceAnswer::of('b'), $now);
        $session->close($quiz, $now);

        self::assertEquals(new ResultView('trivia', 's1', 'm2', 100, 300, false, false, 1, 'blue', 1, false), ResultView::ofLive($session, $quiz, 'm2'));
        $session->finish();
        self::assertEquals(new ResultView('trivia', 's1', 'm3', 0, 300, true, false, 2, 'blue', 1, true), ResultView::ofLive($session, $quiz, 'm3'));
        self::assertEquals(new ResultView('trivia', 's1', 'm1', 0, 300, true, false, 2, 'red', 2, false), ResultView::ofLive($session, $quiz, 'm1'));
    }

    public function testATeamWithoutAPointDoesNotWin(): void
    {
        $quiz = Quizzes::trivia();
        $session = LiveSession::open('s1', $quiz, '123456', ['red', 'blue']);
        $session->join(Player::member('m1'), 'red');
        $session->finish();

        self::assertEquals(new ResultView('trivia', 's1', 'm1', 0, 300, true, false, 1, 'red', 1, false), ResultView::ofLive($session, $quiz, 'm1'));
    }

    public function testTheTemplateShowsTheTeamAndSaysItsWin(): void
    {
        $html = $this->twig()->render('page.html.twig', ['result' => new ResultView('trivia', 's1', 'm1', 100, 300, true, false, 2, 'blue', 1, true)]);

        self::assertStringContainsString('data-tilttek--quiz--result-team-value="blue"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-team-won-value="true"', $html);
        self::assertStringContainsString('<p class="isQuizTeamRank"><data value="1">1</data></p>', $html);
    }

    public function testTheTemplateCarriesTheControllerAndTheCelebrationBlockOfARunOver(): void
    {
        $twig = $this->twig();
        $html = $twig->render('page.html.twig', ['result' => new ResultView('trivia', 'p1', 'm1', 250, 300, true, true, 3)]);

        self::assertStringContainsString('data-controller="tilttek--quiz--result"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-quiz-value="trivia"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-run-value="p1"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-completed-value="true"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-perfect-value="true"', $html);
        self::assertStringContainsString('<data value="250">250</data> / <data value="300">300</data>', $html);
        self::assertStringContainsString('<p class="isQuizRank"><data value="3">3</data></p>', $html);
        self::assertStringContainsString('<p class="celebrate">Well played</p>', $html);
    }

    public function testARunNotOverHasNoCelebrationAndNoRankWithoutOne(): void
    {
        $html = $this->twig()->render('page.html.twig', ['result' => new ResultView('trivia', 'p"1', 'm1', 0, 300, false, false)]);

        self::assertStringContainsString('data-tilttek--quiz--result-completed-value="false"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-perfect-value="false"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-run-value="p&quot;1"', $html);
        self::assertStringNotContainsString('celebrate', $html);
        self::assertStringNotContainsString('isQuizRank', $html);
        self::assertStringNotContainsString('isQuizTeamRank', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-team-value=""', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-team-won-value="false"', $html);
        self::assertStringContainsString('data-tilttek--quiz--result-practice-value="false"', $html);
    }

    public function testAPracticePlayIsShownAsPractice(): void
    {
        $quiz = Quizzes::trivia();
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $play = Play::start('p2', $quiz, Player::member('m1'), $now, 2);

        $view = ResultView::ofPlay($play, $quiz);
        self::assertTrue($view->practice);
        self::assertFalse(ResultView::ofPlay(Play::start('p1', $quiz, Player::member('m1'), $now), $quiz)->practice);

        $html = $this->twig()->render('page.html.twig', ['result' => $view]);
        self::assertStringContainsString('data-tilttek--quiz--result-practice-value="true"', $html);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'TilttekQuiz');
        $loader = new ChainLoader([$loader, new ArrayLoader([
            'page.html.twig' => "{% extends '@TilttekQuiz/result.html.twig' %}{% block celebration %}<p class=\"celebrate\">Well played</p>{% endblock %}",
        ])]);

        return new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
    }
}
