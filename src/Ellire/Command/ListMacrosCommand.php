<?php

namespace Ellire\Command;

class ListMacrosCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('list-macros')
            ->setDescription('Get a list of all resolved and fully processed macros');
    }

    protected function executeCommand()
    {
        $app = $this->getApplication();

        $resolver = $app->getMacroResolver();
        $rawMacros = $resolver->resolveRawMacroValuesFromConfigAndEnvironment()->getRawMacroValues();

        $this->outputHeader('Load raw macro values from config and environment');
        $this->outputMacros($rawMacros);

        $processedMacros = $resolver->processAllMacroValues(
            $app->getTemplateStringProcessor(),
            $app->getMacroExtractor()->findAllMacrosNeededInTemplates()
        )->getProcessedMacroValues();

        $this->outputHeader('Resolve all macro values');
        $this->outputMacros($processedMacros);
    }

    private function outputMacros($macros)
    {
        $this->outputTwoColumnList($macros, array('Macro name', 'Macro value'));
    }
}