<?php

namespace Ellire;

use Exception;
use Ellire\Template\Finder\TemplateFinderInterface;
use Ellire\Template\Processor\TemplateProcessorInterface;

class FileGenerator
{
    const OUTCOME_CHANGED = 'changed';
    const OUTCOME_SKIPPED = 'skipped';

    /**
     * @var TemplateFinderInterface
     */
    private $templateFinder;

    /**
     * @var TemplateProcessorInterface
     */
    private $templateProcessor;

    /**
     * @var array
     */
    private $allProcessedMacros;

    /**
     * @var array
     */
    private $fileChangesSummary;

    /**
     * @var array
     */
    private $fileMacrosSummary;

    public function __construct(
        TemplateFinderInterface $templateFinder,
        TemplateProcessorInterface $templateProcessor,
        array $allProcessedMacros
    ) {
        $this->templateFinder = $templateFinder;
        $this->templateProcessor = $templateProcessor;
        $this->allProcessedMacros = $allProcessedMacros;
    }

    public function generateFilesFromTemplatesAndMacroValues()
    {
        $this->resetFileMacrosSummary();
        $this->resetFileChangesSummary();

        foreach ($this->templateFinder->getFiles() as $templateFilename) {
            $template = $this->templateProcessor->loadTemplate($templateFilename);
            $newContent = $template->render($this->allProcessedMacros);
            $this->recordFileMacros($templateFilename, $template->getAllKnownMacros(), $template->getMissingMacros());

            $templateFilename = $this->templateFinder->getTemplatesDirectory() . DIRECTORY_SEPARATOR . $templateFilename;
            $generatedFilename = $this->generateFilename(
                $templateFilename,
                $this->allProcessedMacros['dist_file_extension']
            );

            if ($this->generatedFileExistsWithSameContent($generatedFilename, $newContent)) {
                $this->recordFileSkipped($templateFilename);
                continue;
            }

            $this->writeContent(
                $newContent,
                $templateFilename,
                $generatedFilename,
                $this->allProcessedMacros['generated_files_writable']
            );

            $this->recordFileChanged($templateFilename);
        }

        return $this;
    }

    private function generatedFileExistsWithSameContent($generatedFilename, $newContent)
    {
        return file_exists($generatedFilename) && $newContent == file_get_contents($generatedFilename);
    }

    private function writeContent($content, $templateFilename, $generatedFilename, $generateWritable = false)
    {
        $this->makeSureFileIsWritable($generatedFilename);

        if (false === file_put_contents($generatedFilename, $content)) {
            throw new Exception('Could not write content to ' . $generatedFilename);
        }

        list($originalPermissions, $nonWritablePermissions) = $this->getPermissionsFromSourceFile($templateFilename);
        $this->setFilePermissions($generatedFilename, $originalPermissions);

        if ((is_bool($generateWritable) && false === $generateWritable) ||
            (is_string($generateWritable) && 'false' == strtolower($generateWritable))) {
            $this->setFilePermissions($generatedFilename, $nonWritablePermissions);
        }
    }

    private function makeSureFileIsWritable($file)
    {
        if (file_exists($file)) {
            $this->setFilePermissions($file, 0600);
        }
    }

    private function getPermissionsFromSourceFile($file)
    {
        $originalPermissions = fileperms($file);
        $nonWritablePermissions = $originalPermissions & octdec('100555');

        return array($originalPermissions, $nonWritablePermissions);
    }

    private function setFilePermissions($file, $permissions)
    {
        chmod($file, $permissions);
    }

    private function generateFilename($originalFilename, $distExtension)
    {
        if (!preg_match('/\.' . $distExtension . '$/', $originalFilename)) {
            throw new Exception('File is not the right extension');
        }

        return preg_replace('/^(.+)\.' . $distExtension .'$/','\1', $originalFilename);
    }

    private function recordFileChanged($filename)
    {
        $this->fileChangesSummary[$filename] = self::OUTCOME_CHANGED;
    }

    private function recordFileSkipped($filename)
    {
        $this->fileChangesSummary[$filename] = self::OUTCOME_SKIPPED;
    }

    private function resetFileChangesSummary()
    {
        $this->fileChangesSummary = array();
    }

    public function getFileChangesSummary()
    {
        return $this->fileChangesSummary;
    }
    
    private function recordFileMacros($filename, array $allMacrosInTemplate, array $missingMacros)
    {
        $this->fileMacrosSummary[$filename] = array($allMacrosInTemplate, $missingMacros);
    }

    public function resetFileMacrosSummary()
    {
        $this->fileMacrosSummary = array();
    }

    public function getFileMacrosSummary()
    {
        return $this->fileMacrosSummary;
    }
    
}