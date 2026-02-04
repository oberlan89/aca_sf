<?php

namespace App\Controller;

use App\Entity\Unit;
use App\Form\UnitType;
use App\Repository\UnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/unit')]
final class UnitController extends AbstractController
{
    #[Route('', name: 'app_unit_index', methods: ['GET'])]

    public function index(UnitRepository $unitRepository, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $visibleUnits = $unitRepository->findVisibleForUser($user);

        // Build "visible tree" (skipping non-generating and skipping units outside visibility)
        [$childrenByVisibleParent] = $this->buildVisibleTree($visibleUnits);

        $topUnits = $childrenByVisibleParent[null] ?? [];

        return $this->render('unit/index.html.twig', [
            'units' => $topUnits,
        ]);
    }

    #[Route('/new', name: 'app_unit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
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
        return $this->render('unit/show.html.twig', [
            'unit' => $unit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_unit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Unit $unit, EntityManagerInterface $entityManager): Response
    {
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
    public function delete(Request $request, Unit $unit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$unit->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($unit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_unit_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/children', name: 'app_unit_children', methods: ['GET'])]

    public function children(Unit $unit, UnitRepository $unitRepository, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $visibleUnits = $unitRepository->findVisibleForUser($user);

        [$childrenByVisibleParent] = $this->buildVisibleTree($visibleUnits);

        $children = $childrenByVisibleParent[$unit->getId()] ?? [];

        // Return a fragment for your JS fetch()
        return $this->render('unit/_children.html.twig', [
            'children' => $children,
        ]);
    }

    /**
     * @param Unit[] $visibleUnits (already filtered to isGenerating=true AND role visibility)
     * @return array{0: array<int|null, Unit[]>}
     */
    private function buildVisibleTree(array $visibleUnits): array
    {
        $visibleById = [];
        foreach ($visibleUnits as $u) {
            $visibleById[$u->getId()] = $u;
        }

        $childrenByVisibleParent = [];

        foreach ($visibleUnits as $u) {
            // climb until we find a visible ancestor
            $p = $u->getParent();
            while ($p !== null && !isset($visibleById[$p->getId()])) {
                $p = $p->getParent();
            }

            $parentId = $p?->getId(); // null => top-level
            $childrenByVisibleParent[$parentId][] = $u;
        }

        // Optional: sort each bucket by name (or by code)
        foreach ($childrenByVisibleParent as $pid => $bucket) {
            usort($bucket, fn(Unit $a, Unit $b) => strcmp($a->getName(), $b->getName()));
            $childrenByVisibleParent[$pid] = $bucket;
        }

        return [$childrenByVisibleParent];
    }

}
