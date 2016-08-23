<?php

namespace Ellire\Template;

use Twig_Template;

abstract class TwigTemplate extends Twig_Template implements TemplateInterface
{
    private $knownMacros = array();
    private $providedMacros = array();
    private $missingMacros = null;

    public function render(array $context)
    {
        $this->providedMacros = array_keys($context);

        return parent::render($context);
    }

    public function setAllKnownMacros(array $variables)
    {
        $this->knownMacros = $variables;
    }

    public function getAllKnownMacros()
    {
        return $this->knownMacros;
    }

    public function getMissingMacros()
    {
        if (!isset($this->missingMacros)) {
            $this->missingMacros = array_diff($this->knownMacros, $this->providedMacros);
        }
        
        return $this->missingMacros;
    }

    public function hasMissingMacros()
    {
        return count($this->getMissingMacros()) > 0;
    }
}