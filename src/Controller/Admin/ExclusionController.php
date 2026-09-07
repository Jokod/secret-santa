<?php

namespace App\Controller\Admin;

use App\Entity\Exclusion;
use App\Exception\EditionLockedException;
use App\Form\ExclusionType;
use App\Repository\ExclusionRepository;
use App\Service\EditionLockGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/exclusions')]
final class ExclusionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExclusionRepository $exclusions,
        private readonly EditionLockGuard $lockGuard,
    ) {
    }

    #[Route('', name: 'admin_exclusions', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $exclusion = new Exclusion();
        $form = $this->createForm(ExclusionType::class, $exclusion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->lockGuard->assertMutable();
            } catch (EditionLockedException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('admin_exclusions');
            }

            if ($exclusion->getSource()->getId() === $exclusion->getTarget()->getId()) {
                $this->addFlash('danger', 'Une personne ne peut pas s’exclure elle-même (déjà géré par le tirage).');

                return $this->redirectToRoute('admin_exclusions');
            }

            $existing = $this->exclusions->findOneBy([
                'source' => $exclusion->getSource(),
                'target' => $exclusion->getTarget(),
            ]);
            if ($existing) {
                $this->addFlash('warning', 'Cette exclusion existe déjà.');

                return $this->redirectToRoute('admin_exclusions');
            }

            $this->em->persist($exclusion);

            if ($form->get('mutual')->getData()) {
                $reverse = $this->exclusions->findOneBy([
                    'source' => $exclusion->getTarget(),
                    'target' => $exclusion->getSource(),
                ]);
                if (!$reverse) {
                    $mutual = (new Exclusion())
                        ->setSource($exclusion->getTarget())
                        ->setTarget($exclusion->getSource());
                    $this->em->persist($mutual);
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Exclusion enregistrée.');

            return $this->redirectToRoute('admin_exclusions');
        }

        return $this->render('admin/exclusions/index.html.twig', [
            'exclusions' => $this->exclusions->findAllWithParticipants(),
            'form' => $form,
            'locked' => $this->lockGuard->isLocked(),
            'participantCount' => $this->em->getRepository(\App\Entity\Participant::class)->count([]),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_exclusions_delete', methods: ['POST'])]
    public function delete(Request $request, Exclusion $exclusion): Response
    {
        if (!$this->isCsrfTokenValid('delete_exclusion_'.$exclusion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_exclusions');
        }

        try {
            $this->lockGuard->assertMutable();
        } catch (EditionLockedException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_exclusions');
        }

        $this->em->remove($exclusion);
        $this->em->flush();
        $this->addFlash('success', 'Exclusion supprimée.');

        return $this->redirectToRoute('admin_exclusions');
    }
}
