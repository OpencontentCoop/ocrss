<?php

abstract class SubTreeRSSHandler extends OCRSSHandlerBase
{
    /**
     * @var eZContentObjectTreeNode
     */
    protected $node;

    /**
     * @var eZContentObjectAttribute[]
     */
    protected $dataMap;

    protected $searchParameters = array();

    public function __construct( $nodeId, $classIdentifiers = null )
    {
        $this->node = eZContentObjectTreeNode::fetch( $nodeId );
        if ( is_array( $classIdentifiers ) )
        {
            $this->setSearchParameters(
                array(
                    'limit' => 20,
                    'subtree_array' => array( $this->node->attribute( 'node_id' ) ),
                    'class_id' => $classIdentifiers,
                    'sort_by' => array(
                        'attr_publish_date_dt' => 'desc',
                        'published' => 'desc'
                    )
                )
            );
        }
    }

    function setSearchParameters( $searchParameters )
    {
        $this->searchParameters = array_merge( $this->searchParameters, $searchParameters );
    }

    /**
     * @return string
     */
    function getFeedTitle()
    {
        return $this->node->attribute( 'object' )->attribute( 'name' );
    }

    /**
     * @return string
     */
    function getFeedDescription()
    {
        if ( method_exists( 'OCOperatorsCollection', 'getAbstract' ) )
        {
            $operators = new OCOperatorsCollection();
            return $operators->getAbstract( $this->node );
        }
        return '';
    }

    function getFeedImageUrl()
    {
        $imageAttribute =  $this->dataMap['image'];
        if ( !$imageAttribute )
        {
            return false;
        }

        /** @var eZImageAliasHandler $imageHandler */
        $imageHandler = $imageAttribute->attribute( 'content' );
        if ( !$imageHandler instanceof eZImageAliasHandler )
        {
            return false;
        }

        $imageAlias =  $imageHandler->imageAlias( 'medium' );
        if( !$imageAlias )
        {
            return false;
        }

        $url = eZSys::hostname() . eZSys::wwwDir() .'/'. $imageAlias['url'];
        $url = preg_replace( "#^(//)#", "/", $url );

        return 'http://'.$url;
    }

    function getNodes()
    {
        $searchResult = eZFunctionHandler::execute( 'ezfind', 'search', $this->searchParameters );
        return $searchResult['SearchResult'];
    }
}