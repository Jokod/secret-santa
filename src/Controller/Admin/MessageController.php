<?php

namespace App\Controller\Admin;

use App\Entity\Participant;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/messages')]
final class MessageController extends AbstractController
{
    #[Route('', name: 'admin_messages', methods: ['GET'])]
    public function index(MessageRepository $messages): Response
    {
        return $this->render('admin/messages/index.html.twig', [
            'threads' => $messages->findGroupedThreads(),
        ]);
    }

    #[Route('/{santaId}/{targetId}', name: 'admin_messages_show', methods: ['GET'], requirements: ['santaId' => '\d+', 'targetId' => '\d+'])]
    public function show(
        int $santaId,
        int $targetId,
        ParticipantRepository $participants,
        MessageRepository $messages,
        EntityManagerInterface $em,
    ): Response {
        $santa = $participants->find($santaId);
        $target = $participants->find($targetId);
        if (!$santa instanceof Participant || !$target instanceof Participant) {
            throw new NotFoundHttpException('Conversation introuvable.');
        }

        $thread = $messages->findThread($santa, $target);
        if ($thread === []) {
            throw new NotFoundHttpException('Conversation introuvable.');
        }

        foreach ($thread as $message) {
            if (!$message->isRead()) {
                $message->setIsRead(true);
            }
        }
        $em->flush();

        return $this->render('admin/messages/show.html.twig', [
            'santa' => $santa,
            'target' => $target,
            'thread' => $thread,
        ]);
    }
}
