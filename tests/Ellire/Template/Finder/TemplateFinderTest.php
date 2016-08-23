<?php

use Ellire\Template\Finder\TemplateFinderInterface;
use Ellire\Template\Finder\TemplateFinder;

class TemplateFinderTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $templateFileProcessor = new TemplateFinder(getcwd(), '<', '>');

        $this->assertInstanceOf(TemplateFinderInterface::class, $templateFileProcessor);
    }
}