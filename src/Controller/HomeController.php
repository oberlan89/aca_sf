<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UnitAssignmentRepository;
use App\Repository\UnitRepository;
use App\Service\UnitTreeBuilder;
use Pagerfanta\Adapter\ArrayAdapter;
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
        UnitAssignmentRepository $uaRepo,
        UnitTreeBuilder $treeBuilder,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Search
        $q = trim((string) $request->query->get('q', ''));
        $q = ($q !== '') ? $q : null;

        // Visible units (already filtered by your repository search)
        $units = $unitRepo
            ->createSearchVisibleForUserQueryBuilder($user, $q)
            ->getQuery()
            ->getResult();

        // Roles
        $roles = $user->getRoles();
        $isStaff = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ADVISOR', $roles, true);

        // Page (only once)
        $page = max(1, $request->query->getInt('page', 1));

        // Single-unit redirect (portal users only)
        if (!$isStaff && count($units) === 1 && $q === null && $page === 1) {
            return $this->redirectToRoute('app_unit_show', ['id' => $units[0]->getId()]);
        }

        // Assignments (same as before)
        $assignments = null;
        if (!$isStaff && $user->getServant()) {
            $assignments = $uaRepo->findGeneratingForServant($user->getServant());
        }

        // Build visible tree
        [$childrenByVisibleParent] = $treeBuilder->buildVisibleTree($units);
        $rootUnits = $childrenByVisibleParent[null] ?? [];

        // Pager must paginate ROOT units (this makes page 2 actually change cards)
        $pager = new Pagerfanta(new ArrayAdapter($rootUnits));
        $pager->setMaxPerPage(10);

        try {
            $pager->setCurrentPage($page);
        } catch (OutOfRangeCurrentPageException $e) {
            throw $this->createNotFoundException();
        }

        return $this->render('home/index.html.twig', [
            'units' => $units, // optional; can keep for debugging
            'assignments' => $assignments,

            'childrenByVisibleParent' => $childrenByVisibleParent,
            'isStaff' => $isStaff,
            'q' => $q,

            // IMPORTANT: template should iterate over `pager` for the cards
            'pager' => $pager,

            // optional: if template shows roots count somewhere
            'rootUnits' => $rootUnits,
        ]);
    }

}
