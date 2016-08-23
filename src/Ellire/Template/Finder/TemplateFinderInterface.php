<?php
namespace Ellire\Template\Finder;

interface TemplateFinderInterface
{
    /**
     * @return array
     */
    public function getFiles();

    /**
     * @return string
     */
    public function getTemplatesDirectory();
}