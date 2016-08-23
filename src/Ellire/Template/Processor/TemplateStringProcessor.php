<?php

namespace Ellire\Template\Processor;

use Ellire\Template\Processor\TwigEnvironment;
use Twig_Loader_Array;

class TemplateStringProcessor extends TemplateFileProcessor
{
    protected function initTemplatingEnvironment()
    {
        $loader = new Twig_Loader_Array($this->macros);
        $this->twig = new TwigEnvironment($loader, $this->getTwigEnvironmentOptions());
        $this->configureTwig($this->macros['macro_opening_string'], $this->macros['macro_closing_string']);
    }
}