<?php

class LegacyRSSHandler extends OCRSSHandlerBase
{

    /**
     * @var eZRSSExport
     */
    protected $rssExport;

    function __construct( eZRSSExport $rssExport )
    {
        $this->rssExport = $rssExport;
    }

    //@todo usefull?
    public function generateFeed()
    {
        return $this->rssExport->attribute( 'rss-xml-content' );
    }

    /**
     * @return eZContentObjectTreeNode[]
     */
    function getNodes()
    {
        $rssSources = eZRSSExportItem::fetchFilteredList( array(
            'rssexport_id'  => $this->rssExport->ID,
            'status'        => $this->rssExport->Status
        ) );

        //@todo override this for openpa?
        return eZRSSExportItem::fetchNodeList( $rssSources, $this->rssExport->getObjectListFilter() );
    }

    /**
     * @return string
     */
    function getFeedTitle()
    {
        return $this->rssExport->attribute( 'title' );
    }

    /**
     * @return string
     */
    function getFeedAccessUrl()
    {
        return $this->rssExport->attribute( 'url' );
    }

    /**
     * @return string
     */
    function getFeedDescription()
    {
        return $this->rssExport->attribute( 'description' );
    }

    /**
     * @return string
     */
    function getFeedImageUrl()
    {
        return $this->rssExport->fetchImageURL();
    }
    
    function cacheKey()
    {
        return $this->rssExport->attribute( 'access_url' );
    }
}