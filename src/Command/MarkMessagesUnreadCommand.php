<?php

namespace App\Command;

use App\Repository\MessageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:messages:mark-unread', description: 'Passe tous les messages en non lus')]
final class MarkMessagesUnreadCommand extends Command
{
    public function __construct(
        private readonly MessageRepository $messages,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updated = $this->messages->markAllUnread();

        $io->success(sprintf('%d message(s) passé(s) en non lu.', $updated));

        return Command::SUCCESS;
    }
}
