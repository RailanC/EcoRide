<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email',
)]
class TestEmailCommand extends Command
{
    public function __construct(private MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
            ->from('railansouzaguimaraes@gmail.com')
            ->to('railansouzaguimaraes@gmail.com')
            ->subject('Test Email Symfony')
            ->text('This is a test email from Symfony CLI');

        $this->mailer->send($email);

        $output->writeln('Email sent!');

        return Command::SUCCESS;
    }
}