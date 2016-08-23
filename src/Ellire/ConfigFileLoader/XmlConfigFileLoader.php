<?php

namespace Ellire\ConfigFileLoader;

use Symfony\Component\Config\Util\XmlUtils;

class XmlConfigFileLoader extends ConfigFileLoader
{
    public function loadFile($resource, $type = null)
    {
        $dom = XmlUtils::loadFile($resource);
        return XmlUtils::convertDomElementToArray($dom->documentElement);
    }

    public function getType()
    {
        return 'xml';
    }
}