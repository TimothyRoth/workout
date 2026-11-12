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

final class WorkoutController extends AbstractController
{
    use SeeOther;

    public function __construct(private readonly WorkoutRepository $workouts)
    {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('workout/index.html.twig', ['title' => 'Workouts', 'workouts' => $this->workouts->findAllWithStats()]);
    }

    #[Route('/workout', name: 'app_workout', methods: ['GET'])]
    public function show(RequestInput $input, ExerciseRepository $exercises): Response
    {
        $workout = $this->requireWorkout($input->int('workout_id'));

        return $this->render('workout/show.html.twig', [
            'title' => $workout['name'],
            'workout' => $workout,
            'exercises_open' => \count($workout['exercises']) <= 2, // short workouts start expanded
            'exercise_names' => $exercises->names(),
        ]);
    }

    #[Route('/addWorkout', methods: ['POST'])]
    public function add(RequestInput $input): Response
    {
        $this->workouts->add($input->string('workout_name'));

        return $this->seeOther('/');
    }

    #[Route('/editWorkout', methods: ['POST'])]
    public function edit(RequestInput $input): Response
    {
        $workout = $this->requireWorkout($input->int('workout_id'));
        $this->workouts->rename($workout['id'], $input->string('workout_name'));

        return $this->seeOther('/');
    }

    #[Route('/deleteWorkout', methods: ['POST'])]
    public function delete(RequestInput $input): Response
    {
        $this->workouts->delete($this->requireWorkout($input->int('workout_id'))['id']);

        return $this->seeOther('/');
    }

    #[Route('/duplicateWorkout', methods: ['POST'])]
    public function duplicate(RequestInput $input): Response
    {
        $copy = $this->workouts->duplicate($this->requireWorkout($input->int('workout_id'))['id']);

        return $this->seeOther('/workout', ['workout_id' => $copy]);
    }

    private function requireWorkout(int $id): array
    {
        return $this->workouts->findPlan($id) ?? throw UserError::notFound('Workout nicht gefunden.');
    }
}
