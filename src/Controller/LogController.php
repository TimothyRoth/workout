<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\RequestInput;
use App\Http\UserError;
use App\Repository\LogRepository;
use App\Training\Calendar;
use App\Training\History;
use App\Training\LogBadges;
use App\Training\LoggedSessionParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogController extends AbstractController
{
    use SeeOther;

    private const HISTORY_ROWS = 10;

    public function __construct(private readonly LogRepository $logs)
    {
    }

    #[Route('/logs', name: 'app_logs', methods: ['GET'])]
    public function index(): Response
    {
        $logs = $this->logs->findAll();
        $histories = History::byExercise($logs);

        foreach ($logs as &$log) {
            $log['badges'] = LogBadges::for($log);
            foreach ($log['exercises'] as &$exercise) {
                $history = $histories[History::key($log, $exercise)] ?? [];
                $exercise['history_count'] = \count($history);
                $exercise['charts'] = \count($history) > 1 ? array_values(array_filter(History::chartSeries($history))) : [];
                $exercise['history'] = array_map(static fn (array $entry): array => $entry + [
                    'sets_line' => History::setsLine($entry['sets']),
                    'is_current' => $entry['log_id'] === (int) $log['id'],
                ], \array_slice($history, 0, self::HISTORY_ROWS));
            }
            unset($exercise);
        }
        unset($log);

        return $this->render('log/index.html.twig', [
            'title' => 'Logs',
            'logs' => $logs,
            'calendar' => [] === $logs ? null : Calendar::build($logs),
        ]);
    }

    #[Route('/editLog', methods: ['POST'])]
    public function edit(RequestInput $input): Response
    {
        $log = $this->requireLog($input->int('log_id'));
        $duration = $input->int('duration', 0, 24 * 60);
        $notes = $input->text('notes', 1000);
        $this->logs->update($log['id'], $duration, $notes, LoggedSessionParser::corrections($input->raw('sets')));

        return $this->seeOther('/logs', [], 'log-'.$log['id']);
    }

    #[Route('/deleteLog', methods: ['POST'])]
    public function delete(RequestInput $input): Response
    {
        $this->logs->delete($this->requireLog($input->int('log_id'))['id']);

        return $this->seeOther('/logs');
    }

    #[Route('/export.json', methods: ['GET'])]
    public function export(): Response
    {
        return new Response(
            json_encode($this->logs->exportAll(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="workout-export-'.date('Y-m-d').'.json"',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    private function requireLog(int $id): array
    {
        return $this->logs->find($id) ?? throw UserError::notFound('Eintrag nicht gefunden.');
    }
}
