<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Mail\Mailer;
use Echo\Framework\Mail\EmailQueue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mail:queue', description: 'Process pending email jobs')]
class MailQueueCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Processing email queue...');

        try {
            $mailer = new Mailer(
                host: config('mail.host'),
                port: (int) config('mail.port'),
                username: config('mail.username'),
                password: config('mail.password'),
                encryption: config('mail.encryption'),
                fromAddress: config('mail.from_address'),
                fromName: config('mail.from_name'),
            );

            $queue = new EmailQueue($mailer);
            $result = $queue->process();

            $output->writeln(sprintf(
                '<info>Done: %d sent, %d failed</info>',
                $result['sent'],
                $result['failed'],
            ));

            return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
