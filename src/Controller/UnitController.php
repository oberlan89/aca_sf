<?php

namespace App\Controller;

use App\Entity\Unit;
use App\Form\UnitType;
use App\Repository\UnitRepository;
use App\Service\UnitTreeBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/unit')]
final class UnitController extends AbstractController
{
    #[Route('', name: 'app_unit_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(UnitRepository $unitRepository, UnitTreeBuilder$treeBuilder, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $visibleUnits = $unitRepository->findVisibleForUser($user);

        // Build "visible tree" (skipping non-generating and skipping units outside visibility)
        [$childrenByVisibleParent] = $treeBuilder->buildVisibleTree($visibleUnits);


        $topUnits = $childrenByVisibleParent[null] ?? [];

        return $this->render('unit/index.html.twig', [
            'units' => $topUnits,
        ]);
    }

    #[Route('/new', name: 'app_unit_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('UNIT_CREATE');

        $unit = new Unit();
        $form = $this->createForm(UnitType::class, $unit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($unit);
            $entityManager->flush();

            return $this->redirectToRoute('app_unit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('unit/new.html.twig', [
            'unit' => $unit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_unit_show', methods: ['GET'])]
    public function show(Unit $unit): Response
    {
        $this->denyAccessUnlessGranted('UNIT_VIEW', $unit);
        return $this->render('unit/show.html.twig', [
            'unit' => $unit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_unit_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Unit $unit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('UNIT_EDIT', $unit);
        $form = $this->createForm(UnitType::class, $unit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_unit_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('unit/edit.html.twig', [
            'unit' => $unit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_unit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Unit $unit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('UNIT_EDIT', $unit);
        if ($this->isCsrfTokenValid('delete'.$unit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($unit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_unit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/children', name: 'app_unit_children', methods: ['GET'])]

    public function children(Unit $unit, UnitRepository $unitRepository, UnitTreeBuilder $treeBuilder, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $visibleUnits = $unitRepository->findVisibleForUser($user);

        [$childrenByVisibleParent] = $treeBuilder->buildVisibleTree($visibleUnits);

        $children = $childrenByVisibleParent[$unit->getId()] ?? [];

        // Return a fragment for your JS fetch()
        return $this->render('unit/_children.html.twig', [
            'children' => $children,
        ]);
    }


}
