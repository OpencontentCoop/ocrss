<?php

abstract class eZTagsRSSHandler extends OCRSSHandlerBase
{

    /**
     * @var eZTagsObject[]
     */
    private $tags;

    /**
     * @var eZTagsObject
     */
    private $mainTag;

    /**
     * @var array[]
     */
    private $classAttributeIdentifiers;

    /**
     * @var int[]
     */
    private $subTreeArray;

    /**
     * @param $tag
     * @param bool $includeTagSubTree
     *
     * @throws Exception
     */
    protected function setTag( $tag, $includeTagSubTree = false )
    {
        if( is_numeric( $tag ))
        {
            $this->tags = array( eZTagsObject::fetch( $tag ));
        }
        else
        {
            $this->tags = eZTagsObject::fetchByKeyword( $tag );
        }
        if ( !empty( $this->tags ) )
        {
            $this->mainTag = $this->tags[0];
            if ( $includeTagSubTree )
            {
                foreach ( $this->tags as $tag )
                {
                    $subTags = eZTagsObject::subTreeByTagID( array(), $tag->attribute( 'id' ) );
                    if ( is_array( $subTags ) )
                    {
                        $this->tags = array_merge( $this->tags, $subTags );
                    }
                }
            }
        }
        else
        {
            throw new Exception( "Tag $tag not found" );
        }
    }

    /**
     * @return eZTagsObject
     */
    protected function getTagObject()
    {
        return $this->mainTag;
    }


    /**
     * @param array[] $classAttributeIdentifiers array( 'article' => array( 'tags', 'terms', ... ), 'magazine' => array() )
     */
    protected function setClassAttributeIdentifiers( $classAttributeIdentifiers )
    {
        $this->classAttributeIdentifiers = $classAttributeIdentifiers;
    }

    /**
     * @param int[] $subTreeArray
     */
    protected function setSubTreeArray( $subTreeArray = null )
    {
        $this->subTreeArray = $subTreeArray;
    }

    /**
     * @return eZContentObjectTreeNode[]
     * @throws Exception
     */
    function getNodes()
    {
        if ( empty( $this->tags ) )
        {
            throw new Exception( "Tags not found" );
        }
        $searchParameters = array(
            'limit' => eZINI::instance( 'ocrss.ini' )->variable( 'FilterSettings', 'limit' ),
            'subtree_array' => $this->subTreeArray,
            'filter' => $this->buildTagSearchFilter(),
            'sort_by' => array(
                'attr_publish_date_dt' => 'desc',
                'published' => 'desc'
            )
        );
        
        $searchResult = eZFunctionHandler::execute( 'ezfind', 'search', $searchParameters );        
        return $searchResult['SearchResult'];
    }

    protected function buildTagSearchFilter()
    {
        $classes = array();
        $classAttributes = array();
        if ( $this->classAttributeIdentifiers == null )
        {
            /** @var eZContentClassAttribute[] $classAttributes */
            $classAttributes = eZContentClassAttribute::fetchList( true, array( 'data_type_string' => 'eztags' ) );
            foreach( $classAttributes as $classAttribute )
            {
                if ( !isset( $classes[$classAttribute->attribute( 'contentclass_id' )] ) )
                    $classes[$classAttribute->attribute( 'contentclass_id' )] = eZContentClass::fetch( $classAttribute->attribute( 'contentclass_id' ) );
            }
        }
        else
        {
            /** @var eZContentClass[] $_classes */
            $_classes = eZContentClass::fetchList( eZContentClass::VERSION_STATUS_DEFINED, true, false, null, null, array_keys( $this->classAttributeIdentifiers ) );
            foreach( $_classes as $class )
            {
                $classes[$class->attribute('id')] = $classes;
                $params = array(
                    'contentclass_id' => $class->attribute( 'id' ),
                    'version' => $class->attribute( 'version' ),
                    'data_type_string' => 'eztags'
                );
                if ( !empty( $this->classAttributeIdentifiers[$class->attribute( 'identifier' )] ) )
                {
                    $params['identifier'] = array( $this->classAttributeIdentifiers[$class->attribute( 'identifier' )] );
                }                
                $classAttributes = array_merge(
                    $classAttributes,
                    eZContentClassAttribute::fetchFilteredList( $params, true )
                );
            }
        }

        $classFilters = array();
        foreach( array_keys( $classes ) as $classId )
        {
            $classFilters[] = eZSolr::getMetaFieldName( 'contentclass_id' ) . ':' . $classId;
        }
        $classFilter = implode( ' OR ', $classFilters );

        $attributeFilters = array();

        foreach( $classAttributes as $classAttribute )
        {
            foreach( $this->tags as $tag )
            {
                $attributeFilters[] = ezfSolrDocumentFieldBase::getFieldName( $classAttribute ) . ':"' . $tag->attribute( 'keyword' ) . '"';
            }
        }
        $attributeFilter = implode( ' OR ', $attributeFilters );        
        return array( $classFilter, $attributeFilter );
    }
}