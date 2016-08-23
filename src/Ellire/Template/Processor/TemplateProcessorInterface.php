<?php

namespace Ellire\Template\Processor;

use Ellire\Template\TemplateInterface;

interface TemplateProcessorInterface
{
    /**
     * @param $name
     * @return TemplateInterface
     */
    public function loadTemplate($name);
}