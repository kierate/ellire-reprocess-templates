<?php

use Ellire\Template\Processor\TemplateFileProcessor;
use Ellire\Template\Processor\TemplateProcessorInterface;

class TemplateFileProcessorTest extends PHPUnit_Framework_TestCase
{
    public function testImplementsInterface()
    {
        $templateFileProcessor = new TemplateFileProcessor(getcwd(), '<', '>');

        $this->assertInstanceOf(TemplateProcessorInterface::class, $templateFileProcessor);
    }
}