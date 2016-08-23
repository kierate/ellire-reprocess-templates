<?php

namespace Ellire\Template\Processor;

use Ellire\Template\Processor\TwigEnvironment;
use Ellire\Template\TwigTemplate;
use Twig_Extension_Sandbox;
use Twig_Lexer;
use Twig_Loader_Filesystem;
use Twig_Sandbox_SecurityPolicy;

class TemplateFileProcessor implements TemplateProcessorInterface
{
    protected $twig;
    protected $macros;

    public function __construct(array $macros)
    {
        $this->macros = $macros;
        $this->initTemplatingEnvironment();
    }

    protected function initTemplatingEnvironment()
    {
        $loader = new Twig_Loader_Filesystem($this->macros['deploy_path']);
        $this->twig = new TwigEnvironment($loader, $this->getTwigEnvironmentOptions());
        $this->configureTwig($this->macros['macro_opening_string'], $this->macros['macro_closing_string']);
    }

    protected function getTwigEnvironmentOptions()
    {
        return array(
            'cache' => false,
            'strict_variables' => false,
            'autoescape' => false,
            'base_template_class' => TwigTemplate::class,
        );
    }

    protected function configureTwig($macroOpeningString, $macroClosingString)
    {
        $tags = array(
            'if',
            'verbatim'
        );

        $filters = array(
            'capitalize',
            'convert_encoding',
            'date',
            'escape',
            'format',
            'json_encode',
            'length',
            'lower',
            'nl2br',
            'replace',
            'reverse',
            'striptags',
            'title',
            'trim',
            'upper',
            'url_encode'
        );

        $methods = array();
        $properties = array();

        $functions = array(
            'date',
            'max',
            'min',
            'random'
        );

        $policy = new Twig_Sandbox_SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $sandbox = new Twig_Extension_Sandbox($policy, true);
        $this->twig->addExtension($sandbox);

        $lexer = new Twig_Lexer($this->twig, array(
            'tag_comment'   => array('{#', '#}'),
            'tag_block'     => array('{%', '%}'),
            'tag_variable'  => array($macroOpeningString, $macroClosingString),
            'interpolation' => array('#{', '}'),
        ));
        $this->twig->setLexer($lexer);
    }
    
    public function loadTemplate($name)
    {
        return $this->twig->loadTemplate($name);
    }
}