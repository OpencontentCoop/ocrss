<?php

class ezcFeedCustomTextModule extends ezcFeedModule
{
    protected $namespacePrefix = 'custom';

    protected $properties = array();

    public static function getModuleName()
    {
        return 'CustomText';
    }

    public function __set( $name, $value )
    {
        $node = $this->add( $name );
        $node->text = $value;
    }

    public function __get( $name )
    {
        if ( isset( $this->properties[$name] ) )
        {
            return $this->properties[$name];
        }
        return parent::__get( $name );
    }

    public function __isset( $name )
    {
        return isset( $this->properties[$name] );
    }

    public function isElementAllowed($name)
    {
        return true;
    }

    public function add($name)
    {
        $node = new ezcFeedTextElement();
        $this->properties[$name] = $node;
        return $node;
    }

    public function generate(DOMDocument $xml, DOMNode $root)
    {
        foreach( $this->properties as $name => $property ) {
            $elementTag = $xml->createElement($this->getNamespacePrefix() . ':' . $name);
            $root->appendChild($elementTag);
            $elementTag->nodeValue = htmlspecialchars($property->__toString(), ENT_NOQUOTES);
        }
    }

    public function parse($name, DOMElement $node)
    {
        $element = $this->add( $name );
        $value = $node->textContent;
        $element->text = htmlspecialchars_decode( $value, ENT_NOQUOTES );
    }

    public function setNamespacePrefix($prefix)
    {
        $this->namespacePrefix = $prefix;
    }

    public function getNamespacePrefix()
    {
        return $this->namespacePrefix;
    }

    public static function getNamespace()
    {
        return 'http://purl.org/rss/1.0/modules/content/';
    }


}