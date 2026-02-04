<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UnitAssignmentRepository;
use App\Repository\UnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('ROLE_USER')]
    public function index(UnitRepository $unitRepo, UnitAssignmentRepository $uaRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $units = $unitRepo->findVisibleForUser($user);

        // If portal user, show assignment details (staff doesn't need this)
        $assignments = null;
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_ADVISOR', $roles, true) && $user->getServant()) {
            $assignments = $uaRepo->findGeneratingForServant($user->getServant());
        }

        return $this->render('home/index.html.twig', [
            'units' => $units,
            'assignments' => $assignments,
        ]);
    }

}
