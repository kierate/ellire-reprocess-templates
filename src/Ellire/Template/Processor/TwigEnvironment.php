<?php

namespace Ellire\Template\Processor;

use Ellire\Template\TemplateInterface;
use Twig_Environment;
use Twig_Error;
use Twig_Error_Syntax;
use Exception;

class TwigEnvironment extends Twig_Environment
{
    private $macrosInTemplates = array();

    /**
     * @inheritdoc
     *
     * On top of the usual compilation this function extracts variables from the source.
     *
     * @param string $source The template source code
     * @param string $name   The template name
     *
     * @return string The compiled PHP source code
     *
     * @throws Twig_Error_Syntax When there was an error during tokenizing, parsing or compiling
     */
    public function compileSource($source, $name = null)
    {
        try {
            $parsed = $this->parse($this->tokenize($source, $name));

            $this->macrosInTemplates[$name] = array_unique($this->extractPrintNodeNames($parsed->getNode('body')));

            return $this->compile($parsed);
        } catch (Twig_Error $e) {
            $e->setTemplateFile($name);
            throw $e;
        } catch (Exception $e) {
            throw new Twig_Error_Syntax(sprintf('An exception has been thrown during the compilation of a template ("%s").', $e->getMessage()), -1, $name, $e);
        }
    }

    /**
     * Recursive function to get variable names from the template
     * @param $nodes
     * @param array $macros
     * @return array
     */
    private function extractPrintNodeNames($nodes, $macros = array())
    {
        foreach ($nodes as $node) {
            if (method_exists($node, 'count') && $node->count() > 0) {
                $macros = $this->extractPrintNodeNames($node, $macros);
            } else {
                if (method_exists($node, 'hasAttribute') && $node->hasAttribute('name')) {
                    $macros[] = $node->getAttribute('name');
                }
            }
        }

        return $macros;
    }

    private function getAllMacrosInTemplate($name)
    {
        if (!array_key_exists($name, $this->macrosInTemplates)) {
            return array();
        }

        return $this->macrosInTemplates[$name];
    }

    /**
     * Loads a template by name.
     *
     * @param string $name  The template name
     * @param int    $index The index if it is an embedded template
     *
     * @return Twig_TemplateInterface A template instance representing the given template name
     *
     * @throws Twig_Error_Loader When the template cannot be found
     * @throws Twig_Error_Syntax When an error occurred during compilation
     */
    public function loadTemplate($name, $index = null)
    {
        /** var TemplateInterface $template */
        $template = parent::loadTemplate($name, $index);

        $template->setAllKnownMacros($this->getAllMacrosInTemplate($name));

        return $template;
    }
}