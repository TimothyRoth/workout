<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Unit;
use App\Http\RequestInput;
use App\Http\UserError;
use App\Repository\ExerciseRepository;
use App\Repository\SetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SetController extends AbstractController
{
    use SeeOther;

    private const MAX_SETS_PER_ADD = 10;

    public function __construct(
        private readonly SetRepository $sets,
        private readonly ExerciseRepository $exercises,
    ) {
    }

    #[Route('/addSet', methods: ['POST'])]
    public function add(RequestInput $input): Response
    {
        $exercise = $this->requireExercise($input->int('exercise_id'));
        $amount = $input->int('amount', 1, self::MAX_SETS_PER_ADD);
        [$reps, $unit, $load, $rest] = $this->setFields($input);
        $this->sets->addMany($exercise['id'], $amount, $reps, $unit, $load, $rest);

        return $this->backTo($exercise);
    }

    #[Route('/editSet', methods: ['POST'])]
    public function edit(RequestInput $input): Response
    {
        $set = $this->requireSet($input->int('set_id'));
        $exercise = $this->requireExercise($set['exercise_id']);
        [$reps, $unit, $load, $rest] = $this->setFields($input);
        $this->sets->edit($set['id'], $reps, $unit, $load, $rest);

        return $this->backTo($exercise);
    }

    #[Route('/deleteSet', methods: ['POST'])]
    public function delete(RequestInput $input): Response
    {
        $set = $this->requireSet($input->int('set_id'));
        $exercise = $this->requireExercise($set['exercise_id']);
        $this->sets->delete($set['id']);

        return $this->backTo($exercise);
    }

    #[Route('/duplicateSet', methods: ['POST'])]
    public function duplicate(RequestInput $input): Response
    {
        $set = $this->requireSet($input->int('set_id'));
        $exercise = $this->requireExercise($set['exercise_id']);
        $this->sets->duplicate($set['id']);

        return $this->backTo($exercise);
    }

    /**
     * @return array{0: int, 1: Unit, 2: ?float, 3: int} repetitions, unit, load (kg or seconds, null for bodyweight), rest time in seconds
     */
    private function setFields(RequestInput $input): array
    {
        $reps = $input->int('repetitions', 0, 10000);
        /** @var Unit $unit */
        $unit = $input->enum('unit', Unit::class);
        $load = $unit->needsLoad() ? $input->number('load', 0, $unit->maxLoad()) : null;
        $rest = $input->int('rest_time', 0, 3600);

        return [$reps, $unit, $load, $rest];
    }

    private function backTo(array $exercise): Response
    {
        return $this->seeOther('/workout', ['workout_id' => $exercise['workout_id']], 'exercise-'.$exercise['id']);
    }

    private function requireExercise(int $id): array
    {
        return $this->exercises->find($id) ?? throw UserError::notFound('Übung nicht gefunden.');
    }

    private function requireSet(int $id): array
    {
        return $this->sets->find($id) ?? throw UserError::notFound('Satz nicht gefunden.');
    }
}
