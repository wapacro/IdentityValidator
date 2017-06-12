<?php
/**
 * Copyright (C) 2017 by WAProgramms Software Group, Roman Ackermann. All rights reserved.
 */

/**
 * IdentityValidator
 * IdentityValidator.php
 * @author Roman Ackermann
 */

namespace wapacro\IdentityValidator;

use SimpleXMLElement;
use wapacro\IdentityValidator\Exceptions\MalformedIdentityTemplateException;
use wapacro\IdentityValidator\Exceptions\TemplateNotFoundException;
use wapacro\IdentityValidator\Exceptions\TemplateNotLoadedException;

class IdentityValidator {

    /** @var string $templatePath */
    private $templatePath = __DIR__ . '/templates/';

    /** @var SimpleXMLElement $loadedTemplateXml */
    private $loadedTemplateXml = null;

    /** @var SimpleXMLElement $generatedXml */
    private $generatedXml = null;

    /**
     * IdentityValidator constructor
     * @param string|null $template
     */
    public function __construct (string $template = null) {
        if (!is_null($template)) {
            $this->loadedTemplateXml = $this->loadTemplate($template);
        }

        $this->resetGeneratedXml();
    }

    /**
     * Sets the currently used template
     * @param string $template
     * @return self
     */
    public function setTemplate (string $template) {
        $this->loadedTemplateXml = $this->loadTemplate($template);
        $this->resetGeneratedXml();

        return $this;
    }

    /**
     * Checks wheter the entered lines are
     * valid or not
     * @see self::putDataToXml
     * @return bool
     */
    public function validateMachineReadableLines () {
        $this->preconditionCheck(true);
        $documentStructure = $this->generatedXml->children();

        foreach ($documentStructure as $structure) {
            if (isset($structure['HasChecksum']) && isset($structure['Id'])) {
                $checksum = $this->getChecksumById($structure['Id'], $documentStructure);
                if (is_null($checksum) || !$this->checkChecksum($structure, $checksum)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Generates an XML string with values
     * according to the loaded template
     * @param string $lines
     * @throws MalformedIdentityTemplateException
     */
    public function addMachineReadableLines (string $lines) {
        $this->preconditionCheck();
        $lines = $this->normalizeLines($lines);
        $currentPosition = 0;

        foreach ($this->loadedTemplateXml->Structure->children() as $structure) {
            if ($structure->getName() === 'LineBreak') {
                $currentPosition++;
                continue;
            }

            $length = isset($structure['Length']) ? (string)$structure['Length'] : (isset($structure['Count']) ? (string)$structure['Count'] : null);
            if (is_null($length)) {
                throw new MalformedIdentityTemplateException();
            }

            if ($length === '*') {
                if ($structure->getName() === 'Name') {
                    $length = stripos($lines, (string)$this->loadedTemplateXml->Meta->Separator, $currentPosition) - $currentPosition;
                } else {
                    $length = (int)$this->loadedTemplateXml->Meta->LineLength - $currentPosition;
                }
            }

            $node = $this->generatedXml->addChild($structure->getName(), substr($lines, $currentPosition, $length));
            foreach ($structure->attributes() as $name => $value) {
                $node->addAttribute($name, $value);
            }

            $currentPosition += $length;
        }

        $this->normalizeGeneratedXml();
    }

    /**
     * Strips all separators and unneccessary
     * attributes from the generated XML
     */
    private function normalizeGeneratedXml () {
        $generatedXml = $this->generatedXml;
        $this->resetGeneratedXml();

        foreach ($generatedXml->children() as $structure) {
            if ($structure->getName() !== 'Separator') {
                $node = $this->generatedXml->addChild($structure->getName(), $structure);
                foreach ($structure->attributes() as $name => $value) {
                    if ($name !== 'Length' && $name !== 'Count' && $name !== 'HasChecksum') {
                        $node->addAttribute($name, $value);
                    }
                }
            }
        }
    }

    /**
     * Searches a checksum by id
     * @param string $id
     * @param SimpleXMLElement $structure
     * @return null|string
     */
    private function getChecksumById (string $id, SimpleXMLElement $structure) {
        foreach ($structure as $checksumCandidate) {
            if ($checksumCandidate->getName() === 'Checksum' && isset($checksumCandidate['For'])) {
                if ((string)$checksumCandidate['For'] === $id) {
                    return (string)$checksumCandidate;
                }
            }
        }

        return null;
    }

    /**
     * Calculates the checksum for the
     * given value and determines if
     * everything is correct
     * @param string $value
     * @param string $checknumber
     * @return bool
     */
    private function checkChecksum (string $value, string $checknumber) {
        $p = 7;
        $sum = 0;
        for ($i = 0; $i < strlen($value); $i++) {
            $char = $value{$i};

            if ($char >= '0' && $char <= '9') {
                $int = intval($char);
            } else {
                $int = ord($char) - 55;
            }

            $sum += $int * $p;

            if ($p == 1) {
                $p = 7;
            } else if ($p == 3) {
                $p = 1;
            } else if ($p == 7) {
                $p = 3;
            }
        }

        $last_number = substr(strval($sum), -1);

        return $last_number == $checknumber;
    }


    /**
     * Get a full list of all supported identity
     * documents and its associated country
     * @return array
     */
    public function getSupportedTypes () {
        $templateFolders = scandir($this->templatePath);
        $supportedList = [];

        foreach ($templateFolders as $folder) {
            if ($folder !== '.' && $folder !== '..') {
                $templates = scandir($this->templatePath . '/' . $folder);

                foreach ($templates as $template) {
                    if ($template !== '.' && $template !== '..') {
                        $xml = $this->loadTemplate($folder . '.' . substr($template, 0, -4));

                        $supportedList[] = [
                            'type'     => [
                                'code'        => $xml->Meta[0]->Type['Code'],
                                'description' => $xml->Meta->Type,
                            ],
                            'country'  => [
                                'code'              => $xml->Meta[0]->Country['Code'],
                                'name'              => $xml->Meta->Country,
                                'internationalName' => $xml->Meta[0]->Country['International'],
                            ],
                            'notation' => $folder . '.' . substr($template, 0, -4),
                        ];
                    }
                }
            }
        }

        return $supportedList;
    }

    /**
     * Load a template by its dot notation (e. g. "CH.id")
     * @param string $template
     * @return SimpleXMLElement
     * @throws TemplateNotFoundException
     * @internal param bool $dotNotation
     */
    private function loadTemplate (string $template) {
        $path = $this->getTemplatePath($template);
        if (!file_exists($path)) {
            throw new TemplateNotFoundException();
        }

        return simplexml_load_file($path);
    }

    /**
     * Converts the dot notation to the full
     * relative XML file path
     * @param string $template
     * @return string
     */
    private function getTemplatePath (string $template) {
        list($folder, $type) = explode('.', $template);

        return $this->templatePath . strtoupper($folder) . '/' . strtolower($type) . '.xml';
    }

    /**
     * Checks if everything is ready
     * to compute some identities
     * @param bool $generatedXml
     * @throws TemplateNotLoadedException
     */
    private function preconditionCheck (bool $generatedXml = false) {
        if (is_null($this->loadedTemplateXml)) {
            throw new TemplateNotLoadedException();
        }

        if ($generatedXml) {
            if (strlen($this->generatedXml->asXML()) < 80) {
                throw new TemplateNotLoadedException();
            }
        }
    }

    /**
     * Replaces all line breaks with the |
     * symbol for better checksum handling
     * @param string $input
     * @return string
     */
    private function normalizeLines (string $input) {
        $linebreaks = [
            '\\n',
            '\\r\\n',
            '<br>',
            '<br/>',
            '<br />'
        ];

        return str_ireplace($linebreaks, '|', $input);
    }

    /**
     * Sets an initial generated XML string
     */
    private function resetGeneratedXml () {
        $this->generatedXml = simplexml_load_string('<?xml version="1.0" encoding="utf-8" ?><IdentityDocument></IdentityDocument>');
    }

}