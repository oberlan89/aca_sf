<?php

namespace App\Controller;

use App\Entity\Servant;
use App\Form\ServantType;
use App\Repository\ServantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/servant')]
final class ServantController extends AbstractController
{
    #[Route(name: 'app_servant_index', methods: ['GET'])]
    public function index(ServantRepository $servantRepository): Response
    {
        return $this->render('servant/index.html.twig', [
            'servants' => $servantRepository->findWithGeneratingUnits(),
        ]);
    }

    #[Route('/new', name: 'app_servant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $servant = new Servant();
        $form = $this->createForm(ServantType::class, $servant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($servant);
            $entityManager->flush();

            return $this->redirectToRoute('app_servant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('servant/new.html.twig', [
            'servant' => $servant,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_servant_show', methods: ['GET'])]
    public function show(Servant $servant): Response
    {
        return $this->render('servant/show.html.twig', [
            'servant' => $servant,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_servant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Servant $servant, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServantType::class, $servant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_servant_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('servant/edit.html.twig', [
            'servant' => $servant,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_servant_delete', methods: ['POST'])]
    public function delete(Request $request, Servant $servant, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$servant->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($servant);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_servant_index', [], Response::HTTP_SEE_OTHER);
    }
}
