<?php

namespace Ellire\Command;

class GetProfileCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('get-profile')
            ->setDescription('Get the profile used for the current deployment');
    }

    protected function executeCommand()
    {
        $profile = $this->getApplication()->getMacroResolver()->getProfile();
        $this->output->writeln("The current profile is <info>$profile</info>");

    }
}