<?php

namespace App\Controller\Admin;

use App\Entity\Participant;
use App\Exception\EditionLockedException;
use App\Form\ParticipantType;
use App\Repository\ParticipantRepository;
use App\Service\AppMailer;
use App\Service\EditionLockGuard;
use App\Service\ParticipantTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/participants')]
final class ParticipantController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParticipantRepository $participants,
        private readonly ParticipantTokenGenerator $tokenGenerator,
        private readonly AppMailer $mailer,
        private readonly EditionLockGuard $lockGuard,
    ) {
    }

    #[Route('', name: 'admin_participants', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/participants/index.html.twig', [
            'participants' => $this->participants->findAllOrderedByName(),
            'locked' => $this->lockGuard->isLocked(),
        ]);
    }

    #[Route('/new', name: 'admin_participants_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        try {
            $this->lockGuard->assertMutable();
        } catch (EditionLockedException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_participants');
        }

        $participant = new Participant();
        $form = $this->createForm(ParticipantType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participant->setTokenSecret($this->tokenGenerator->generate());
            $this->em->persist($participant);
            $this->em->flush();
            $this->mailer->sendWelcome($participant);
            $this->addFlash('success', sprintf('%s a été ajouté·e et un email de bienvenue a été envoyé.', $participant->getName()));

            return $this->redirectToRoute('admin_participants');
        }

        return $this->render('admin/participants/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau participant',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_participants_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Participant $participant): Response
    {
        try {
            $this->lockGuard->assertMutable();
        } catch (EditionLockedException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_participants');
        }

        $form = $this->createForm(ParticipantType::class, $participant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Participant mis à jour.');

            return $this->redirectToRoute('admin_participants');
        }

        return $this->render('admin/participants/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier '.$participant->getName(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_participants_delete', methods: ['POST'])]
    public function delete(Request $request, Participant $participant): Response
    {
        if (!$this->isCsrfTokenValid('delete_participant_'.$participant->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_participants');
        }

        if ($participant->isAssigned()) {
            $this->addFlash('danger', 'Impossible de supprimer un participant déjà assigné. Réinitialisez le tirage d’abord.');

            return $this->redirectToRoute('admin_participants');
        }

        try {
            $this->lockGuard->assertMutable();
        } catch (EditionLockedException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_participants');
        }

        $this->em->remove($participant);
        $this->em->flush();
        $this->addFlash('success', 'Participant supprimé.');

        return $this->redirectToRoute('admin_participants');
    }

    #[Route('/{id}/resend-welcome', name: 'admin_participants_resend_welcome', methods: ['POST'])]
    public function resendWelcome(Request $request, Participant $participant): Response
    {
        if (!$this->isCsrfTokenValid('resend_welcome_'.$participant->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_participants');
        }

        $this->mailer->sendWelcome($participant);
        $this->addFlash('success', 'Email de bienvenue renvoyé.');

        return $this->redirectToRoute('admin_participants');
    }
}
