<?php

namespace Ellire\Macro;

use Ellire\Template\Finder\TemplateFinderInterface;
use Ellire\Template\Processor\TemplateProcessorInterface;

class MacroExtractor
{
    /**
     * @var TemplateFinderInterface
     */
    private $templateFinder;

    /**
     * @var TemplateProcessorInterface
     */
    private $processor;

    public function __construct(TemplateFinderInterface $templateFinder, TemplateProcessorInterface $processor)
    {
        $this->templateFinder = $templateFinder;
        $this->processor = $processor;
    }

    public function findAllMacrosNeededInTemplates()
    {
        $neededMacros = array();
        foreach ($this->templateFinder->getFiles() as $file) {
            $macrosInCurrentFile = $this->processor->loadTemplate($file)->getAllKnownMacros();
            $neededMacros = array_unique(array_merge($neededMacros, $macrosInCurrentFile));
        }

        return $neededMacros;
    }
}