<?php

namespace Ellire\Command;

class TemplateMacrosCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('template-macros')
            ->setDescription('List all templates and the macros they need');
    }

    protected function executeCommand()
    {
        $app = $this->getApplication();

        $resolver = $app->getMacroResolver();
        $resolver->resolveRawMacroValuesFromConfigAndEnvironment();
        $macros = $resolver->processAllMacroValues(
            $app->getTemplateStringProcessor(),
            $app->getMacroExtractor()->findAllMacrosNeededInTemplates()
        )->getProcessedMacroValues();

        $summary = array();
        foreach ($app->getTemplateFinder()->getFiles() as $templateFilename) {
            $template = $app->getTemplateFileProcessor()->loadTemplate($templateFilename);
            $template->render($macros);
            $summary[$templateFilename] = array($template->getAllKnownMacros(), $template->getMissingMacros());
        }

        $this->output->writeln('');
        $this->outputTable($this->formatFileMacrosSummaryForTable($summary), array('File', 'Macro name'));
    }
}