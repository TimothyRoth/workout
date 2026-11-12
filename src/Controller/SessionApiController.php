<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use App\Http\RequestInput;
use App\Http\UserError;
use App\Repository\ExerciseRepository;
use App\Repository\LogRepository;
use App\Repository\SetRepository;
use App\Repository\WorkoutRepository;
use App\Training\LoggedSessionParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** The JSON API of the training session (js/session.js) and of drag-and-drop ordering (js/reorder.js). */
#[AsController]
final class SessionApiController
{
    public function __construct(private readonly WorkoutRepository $workouts)
    {
    }

    #[Route('/api/workout/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function plan(int $id, LogRepository $logs): JsonResponse
    {
        $workout = $this->requireWorkout($id);

        return Json::response([
            'status' => 'success',
            'workout' => $workout,
            'last' => $logs->lastPerformance($workout['id']),
            'records' => $logs->records(),
        ]);
    }

    #[Route('/api/workout/log', methods: ['POST'])]
    public function log(RequestInput $input, LogRepository $logs, SetRepository $sets): JsonResponse
    {
        $workout = $this->requireWorkout($input->int('workout_id'));
        $duration = $input->int('duration', 0, 24 * 60);
        $exercises = LoggedSessionParser::exercises($input->raw('exercises'));

        $id = $logs->add($workout['id'], $duration, $exercises, $input->text('notes', 1000));

        // "Plan aktualisieren" in the session summary: today's reps and loads become the new plan.
        $planUpdated = 0;
        if (filter_var($input->raw('update_plan') ?? false, \FILTER_VALIDATE_BOOLEAN)) {
            $planUpdated = $sets->applyPerformance($workout['id'], $exercises);
        }

        return Json::response(['status' => 'success', 'id' => $id, 'plan_updated' => $planUpdated]);
    }

    /** Drag-and-drop order: {"type": "exercises"|"sets", "parent_id": n, "ids": [...]}. */
    #[Route('/api/reorder', methods: ['POST'])]
    public function reorder(RequestInput $input, ExerciseRepository $exercises, SetRepository $sets): JsonResponse
    {
        $type = $input->string('type', 20);
        $parentId = $input->int('parent_id');
        $ids = $input->raw('ids');

        if (!\is_array($ids) || [] === $ids || \count($ids) > 200
            || $ids !== array_filter($ids, static fn ($id) => \is_int($id) ? $id > 0 : false !== filter_var($id, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) {
            throw UserError::badRequest('Feld „ids“ muss eine Liste von IDs sein.');
        }

        $ok = match ($type) {
            'exercises' => $exercises->reorder($this->requireWorkout($parentId)['id'], $ids),
            'sets' => $sets->reorder(($exercises->find($parentId) ?? throw UserError::notFound('Übung nicht gefunden.'))['id'], $ids),
            default => throw UserError::badRequest('Feld „type“ hat einen ungültigen Wert.'),
        };
        if (!$ok) {
            throw UserError::badRequest('Die Reihenfolge passt nicht zu den gespeicherten Einträgen. Bitte Seite neu laden.');
        }

        return Json::response(['status' => 'success']);
    }

    private function requireWorkout(int $id): array
    {
        return $this->workouts->findPlan($id) ?? throw UserError::notFound('Workout nicht gefunden.');
    }
}
