<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UnitAssignmentRepository;
use App\Repository\UnitRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        UnitRepository $unitRepo,
        UnitAssignmentRepository $uaRepo
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1) Read the search text from the top bar: /?q=something
        $q = trim((string) $request->query->get('q', ''));
        $q = ($q !== '') ? $q : null;

        // 2) Build the query (this is what allows pagination)
        $qb = $unitRepo->createSearchVisibleForUserQueryBuilder($user, $q);

        // 3) Create a Pagerfanta pager
        $pager = new Pagerfanta(new QueryAdapter($qb));
        $pager->setMaxPerPage(25);

        $page = max(1, $request->query->getInt('page', 1));
        try {
            $pager->setCurrentPage($page);
        } catch (OutOfRangeCurrentPageException) {
            $pager->setCurrentPage(1);
        }

        // 4) Keep assignments feature exactly as before
        $assignments = null;
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_ADVISOR', $roles, true) && $user->getServant()) {
            $assignments = $uaRepo->findGeneratingForServant($user->getServant());
        }

        return $this->render('home/index.html.twig', [
            'pager' => $pager,
            'q' => $q,
            'assignments' => $assignments,
        ]);
    }
}
