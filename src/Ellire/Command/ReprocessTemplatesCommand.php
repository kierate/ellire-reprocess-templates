<?php

namespace Ellire\Command;

use InvalidArgumentException;
use Ellire\FileGenerator;
use Symfony\Component\Console\Input\InputOption;

class ReprocessTemplatesCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('reprocess-templates')
            ->setDescription('Reprocess all template files based on your Ellire config and environment variables')
            ->addOption(
                'macro',
                'm',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Override any macro values (in macroname=macrovalue format)'
            );
    }

    protected function executeCommand()
    {
        $app = $this->getApplication();

        //start by compiling a list of macros from config files, the environment and any input overrides provided
        $resolver = $app->getMacroResolver();
        $resolver->setMacroOverrides($this->getMacroOverridesFromInput());
        $resolver->resolveRawMacroValuesFromConfigAndEnvironment();

        $this->outputConfigAndEnvSummary();
        $this->outputRawMacroValues();

        $this->exitIfNoTemplatesFound();
        $this->outputTemplateFileList();

        //the template files might use some macros that we don't know about yet
        //so extract those from the files first and once we have a full list reprocess the values as well
        //to make sure we replace any sub-macros and conditionals
        $resolver->processAllMacroValues(
            $app->getTemplateStringProcessor(),
            $app->getMacroExtractor()->findAllMacrosNeededInTemplates()
        );

        $this->outputProcessedMacroValues();

        //at this point all macros are resolved (as much as they can be) so just (re)generate the files
        //based on the templates and the resolved macro values
        $app->getFileGenerator()->generateFilesFromTemplatesAndMacroValues();

        $this->outputFileMacrosSummary();
        $this->outputFormattedFileChangesSummary();
    }

    private function getMacroOverridesFromInput()
    {
        $overrides = array();
        foreach ($this->input->getOption('macro') as $overridePair) {
            if (!preg_match('/^(.+)=(.+)$/', $overridePair, $matches)) {
                throw new InvalidArgumentException("The macro '$overridePair' is not in the key=value format");
            }

            $overrides[$matches[1]] = $matches[2];
        }

        return $overrides;
    }

    private function outputConfigAndEnvSummary()
    {
        if (!$this->output->isDebug()) {
            return;
        }

        $this->outputHeader('Config files used');
        $this->outputSimpleList($this->getApplication()->getMacroResolver()->getConfigFilesUsed());

        $this->outputHeader('Environment variables used');

        $variablesUsed = $this->getApplication()->getMacroResolver()->getEnvVariablesUsed();
        if (empty($variablesUsed)) {
            $this->output->writeln(' No macros defined via environment ');
            $this->output->writeln('');
            return;
        }

        $this->outputTwoColumnList($variablesUsed, array('Macro name', 'Environment variable name'));
    }

    private function outputRawMacroValues()
    {
        if (!$this->output->isDebug()) {
            return;
        }

        $this->outputHeader('Load raw macro values from config and environment');
        $this->outputMacros($this->getApplication()->getMacroResolver()->getRawMacroValues());
    }

    private function outputTemplateFileList()
    {
        if (!$this->output->isVerbose()) {
            return;
        }

        $rawMacros = $this->getApplication()->getMacroResolver()->getRawMacroValues();
        $this->outputHeader('Find all .' . $rawMacros['dist_file_extension'] . ' files');
        $this->outputSimpleList($this->getApplication()->getTemplateFinder()->getFiles());
    }

    private function outputProcessedMacroValues()
    {
        if (!$this->output->isDebug()) {
            return;
        }

        $this->outputHeader('Resolve all macro values');
        $this->outputMacros($this->getApplication()->getMacroResolver()->getProcessedMacroValues());
    }

    private function outputMacros($macros)
    {
        $this->outputTwoColumnList($macros, array('Macro name', 'Macro value'));
    }

    private function outputFileMacrosSummary()
    {
        if (!$this->output->isVerbose()) {
            return;
        }
        
        $summary = $this->getApplication()->getFileGenerator()->getFileMacrosSummary();

        $rawMacros = $this->getApplication()->getMacroResolver()->getRawMacroValues();
        $this->outputHeader('Find all macros needed (and missing) in .' . $rawMacros['dist_file_extension'] . ' files');
        $this->outputTable($this->formatFileMacrosSummaryForTable($summary), array('File', 'Macro name'));
    }

    private function outputFormattedFileChangesSummary()
    {
        $this->outputHeader('Generating files from templates');

        $summary = $this->getApplication()->getFileGenerator()->getFileChangesSummary();
        foreach ($summary as $file => $outcome) {
            $tag = $outcome == FileGenerator::OUTCOME_CHANGED ? 'info' : 'comment';
            $prefix = strtoupper($outcome);

            $this->output->writeln(" <$tag>[$prefix] $file</$tag>");
        }
        $this->output->writeln('');
    }

    private function exitIfNoTemplatesFound()
    {
        if (empty($this->getApplication()->getTemplateFinder()->getFiles())) {
            $this->outputHeader('Generating files from templates');
            $this->output->writeln(' No template files found');
            $this->output->writeln('');
            exit;
        }
    }
}