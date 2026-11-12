<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\RequestInput;
use App\Http\UserError;
use App\Repository\ExerciseRepository;
use App\Repository\WorkoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExerciseController extends AbstractController
{
    use SeeOther;

    public function __construct(private readonly ExerciseRepository $exercises)
    {
    }

    #[Route('/addExercise', methods: ['POST'])]
    public function add(RequestInput $input, WorkoutRepository $workouts): Response
    {
        $workout = $workouts->find($input->int('workout_id')) ?? throw UserError::notFound('Workout nicht gefunden.');
        $id = $this->exercises->add($input->string('exercise_name'), $workout['id'], $input->text('exercise_notes', 500));

        return $this->seeOther('/workout', ['workout_id' => $workout['id']], 'exercise-'.$id);
    }

    #[Route('/editExercise', methods: ['POST'])]
    public function edit(RequestInput $input): Response
    {
        $exercise = $this->requireExercise($input->int('exercise_id'));
        $this->exercises->edit($exercise['id'], $input->string('exercise_name'), $input->text('exercise_notes', 500));

        return $this->seeOther('/workout', ['workout_id' => $exercise['workout_id']], 'exercise-'.$exercise['id']);
    }

    #[Route('/deleteExercise', methods: ['POST'])]
    public function delete(RequestInput $input): Response
    {
        $exercise = $this->requireExercise($input->int('exercise_id'));
        $this->exercises->delete($exercise['id']);

        return $this->seeOther('/workout', ['workout_id' => $exercise['workout_id']]);
    }

    private function requireExercise(int $id): array
    {
        return $this->exercises->find($id) ?? throw UserError::notFound('Übung nicht gefunden.');
    }
}
