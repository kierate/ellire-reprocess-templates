<?php

namespace Ellire\Command;

class InstallDefaultSystemConfigCommand extends BaseCommand
{
    private $systemConfigFileInstalled = false;
    private $userConfigFileInstalled = false;

    protected function configure()
    {
        $this
            ->setName('install-default-config')
            ->setDescription('Install the default system config file');
    }

    protected function executeCommand()
    {
        if (!$this->getApplication()->isGloballyInstalled()) {
            $this->outputNotRunningGloballyError();
            return;
        }

        $this->installDefaultSystemConfig();
        $this->installDefaultUserConfig();

        $this->outputSummary();
    }

    private function outputNotRunningGloballyError()
    {
        $this->output->writeln("<error>This command should be executed using the global Ellire version</error>");
        $this->output->writeln("");
        $this->output->writeln("Try again using: ellire " . $this->getName());
        $this->output->writeln("");
        $this->output->writeln("If you have not yet installed ellire globally then run:");
        $this->output->writeln("composer global require ellire/reprocess-templates");
    }

    private function installDefaultSystemConfig()
    {
        $this->outputHeader('Installing default config for all users of the system');

        $sourceConfigFile = $this->getApplication()->getLocalPath('ellire.json');
        $systemConfigFile = $this->getApplication()->getSystemConfigFilePath();

        if (file_exists($systemConfigFile)) {
            $this->systemConfigFileInstalled = true;
            $this->output->writeln("<info>$systemConfigFile is already installed</info>");
            return;
        }

        if (!@copy($sourceConfigFile, $systemConfigFile)) {
            $this->outputSystemConfigInstallationNotice($sourceConfigFile, $systemConfigFile);
            return;
        }

        $this->systemConfigFileInstalled = true;
        $this->output->writeln("<info>$systemConfigFile successfully installed, adjust it as needed for this machine</info>");
    }

    private function outputSystemConfigInstallationNotice($sourceConfigFile, $systemConfigFile)
    {
        $this->output->writeln("<comment>Cannot write to $systemConfigFile</comment>");
        $this->output->writeln("");
        $this->output->writeln("It is recommended to have a system-wide config file if you will use ellire across multiple user accounts");
        $this->output->writeln("To install it manually run the following (as a privileged user or via sudo):");
        $this->output->writeln("cp $sourceConfigFile $systemConfigFile");
    }

    private function installDefaultUserConfig()
    {
        $this->outputHeader('Installing default config for the current user');

        $userConfigFile = $this->getApplication()->getUserConfigFilePath();
        $userConfigDir = dirname($userConfigFile);
        $defaultUserConfig = '{
    "globals": {
        
    }
}';

        if (file_exists($userConfigFile)) {
            $this->userConfigFileInstalled = true;
            $this->output->writeln("<info>$userConfigFile is already installed</info>");
            return;
        }

        if (!file_exists($userConfigDir)) {
            if (!@mkdir($userConfigDir)) {
                $this->outputUserConfigInstallationNotice("Cannot create $userConfigDir", $userConfigFile, $defaultUserConfig);
                return;
            }

            $this->output->writeln("<info>Successfully created $userConfigDir</info>");
        }

        if (!@file_put_contents($userConfigFile, $defaultUserConfig)) {
            $this->outputUserConfigInstallationNotice("Cannot write to $userConfigFile", $userConfigFile, $defaultUserConfig);
            return;
        }

        $this->userConfigFileInstalled = true;
        $this->output->writeln("<info>$userConfigFile successfully installed, adjust it as needed for this user account</info>");
    }

    private function outputUserConfigInstallationNotice($notice, $userConfigFile, $defaultUserConfig)
    {
        $this->output->writeln("<comment>$notice</comment>");
        $this->output->writeln("");
        $this->output->writeln("To install it manually run the following:");
        $this->output->writeln("cat > $userConfigFile \<\<EOL
$defaultUserConfig
EOL");
    }

    private function outputSummary()
    {
        $this->outputHeader('Summary');

        if ($this->systemConfigFileInstalled && $this->userConfigFileInstalled) {
            $this->output->writeln("<info>Both configuration files exist. Now simply adjust them as needed.</info>");
        } elseif ($this->systemConfigFileInstalled && !$this->userConfigFileInstalled) {
            $this->output->writeln("<info>System-wide config installed. Consider adding user-level configuration for the current user.</info>");
        } elseif (!$this->systemConfigFileInstalled && $this->userConfigFileInstalled) {
            $this->output->writeln("<comment>Only user-level configuration installed. Adjust it as needed.</comment>");
            $this->output->writeln("<comment>For any systems/servers using multiple user accounts it is recommended to install the system-wide config.</comment>");
        } else {
            $this->output->writeln("<error>Both system and user config is missing.</error>");
            $this->output->writeln("<error>You should at least install one of them and set the correct 'profile' macro for the machine and/or user</error>");
        }
    }
}