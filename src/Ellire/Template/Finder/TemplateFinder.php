<?php

namespace Ellire\Template\Finder;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Finder\Finder;

class TemplateFinder implements TemplateFinderInterface
{
    private $dir = null;
    private $extension = null;
    private $excludePaths = array();

    public function __construct($dir, $extension, $excludePaths = null)
    {
        $this->dir = $dir;
        $this->extension = $extension;

        if (isset($excludePaths)) {
            if (is_array($excludePaths)) {
                $this->excludePaths = $excludePaths;
            } else {
                foreach (explode(',', $excludePaths) as $excludePath) {
                    $this->excludePaths[] = rtrim(trim($excludePath), DIRECTORY_SEPARATOR);
                }
            }
        }
    }

    public function getFiles()
    {
        $finder = new Finder();
        $iterator = $finder
            ->files()
            ->name('*.'.$this->extension)
            ->in($this->getTemplatesDirectory());

        if (!empty($this->excludePaths)) {
            $iterator->exclude($this->excludePaths);
        }

        $files = array();
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $files[] = $file->getRelativePathname();
        }

        return $files;
    }

    public function getTemplatesDirectory()
    {
        return $this->dir;
    }
}
