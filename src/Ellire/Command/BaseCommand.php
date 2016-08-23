<?php

namespace Ellire\Command;

use Ellire\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    abstract protected function executeCommand();

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->executeCommand();
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }


    protected function outputHeader($text)
    {
        $this->output->writeln('');
        $this->output->writeln('<fg=magenta>' . $text . '</>');
        $this->output->writeln('<fg=magenta>' . str_repeat('=', strlen($text)) . '</>');
        $this->output->writeln('');
    }

    protected function outputSimpleList($listItems)
    {
        foreach ($listItems as $item) {
            $this->output->writeln(" $item");
        }
        $this->output->writeln('');
    }

    protected function outputTable(array $rows, array $columnHeaders)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders($columnHeaders)
            ->setRows($rows)
            ->setStyle('compact')
            ->render();

        $this->output->writeln('');
    }

    protected function outputTwoColumnList(array $keyValueData, array $columnHeaders)
    {
        //restructure the array for the table
        $rows = array();
        foreach ($keyValueData as $key => $value) {
            $rows[] = array($key, $value);
        }

        $table = new Table($this->output);
        $table
            ->setHeaders($columnHeaders)
            ->setRows($rows)
            ->setStyle('compact')
            ->render();

        $this->output->writeln('');
    }

    protected function getRowspanCell($cellContent, $rowspan)
    {
        if (is_array($rowspan)) {
            $rowspan = count($rowspan);
        }

        return new TableCell($cellContent, array('rowspan' => $rowspan));
    }

    protected function formatFileMacrosSummaryForTable($summary)
    {
        $rows = array();
        foreach ($summary as $templateFile => $macroData) {
            list($allMacrosInTemplate, $missingMacros) = $macroData;
            foreach ($allMacrosInTemplate as $i => $macroName) {
                if (in_array($macroName, $missingMacros)) {
                    $macroName = "<error>$macroName</error>";
                }

                $templateName = $i == 0 ? $this->getRowspanCell($templateFile, $allMacrosInTemplate) : null;
                $rows[] = array_filter(array($templateName, $macroName));
            }
        }

        return $rows;
    }

}