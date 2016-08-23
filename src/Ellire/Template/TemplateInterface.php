<?php

namespace Ellire\Template;

interface TemplateInterface
{
    /**
     * @param array $context
     * @return string
     */
    public function render(array $context);

    /**
     * @param array $variables
     */
    public function setAllKnownMacros(array $variables);

    /**
     * @return array
     */
    public function getAllKnownMacros();

    /**
     * @return array
     */
    public function getMissingMacros();

    /**
     * @return bool
     */
    public function hasMissingMacros();
}
