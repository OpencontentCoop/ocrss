<?php

class OCRSSHandler
{
    /**
     * @var OCRSSHandlerBase
     */
    private $handler;

    private $identifier;

    protected function __construct( $identifier, OCRSSHandlerBase $handler )
    {
        $this->identifier = $identifier;
        $this->handler = $handler;
        $this->handler->setIdentifier($identifier);
    }

    public static function instance( $identifier, $factoryParams = null )
    {
        $handlers = eZINI::instance( 'ocrss.ini' )->group( 'Handlers' );
        $legacyRss = eZRSSExport::fetchByName( $identifier );

        $handlerClassName = false;
        if ( $legacyRss instanceof eZRSSExport )
        {
            $handlerClassName = $handlers['LegacyHandler'];
            $factoryParams = $legacyRss;
        }
        elseif ( array_key_exists( $identifier, $handlers['CustomHandlers'] ) )
        {
            $handlerClassName = $handlers['CustomHandlers'][$identifier];
        }
        if ( $handlerClassName )
        {
            if ( class_exists( $handlerClassName ) )
            {
                $handler = new $handlerClassName( $factoryParams );
                if ( $handler instanceof OCRSSHandlerBase )
                {
                    return new OCRSSHandler( $identifier, $handler );
                }
                else
                {
                    throw new Exception( "$handlerClassName not extends OCRSSHandlerBase" );
                }

            }
            else
            {
                throw new Exception( "$handlerClassName not found" );
            }
        }
        else
        {
            throw new Exception( "$identifier does not match any handler" );
        }
    }
    
    public function printRSS()
    {        
        //@todo anonymous?
        $config = eZINI::instance( 'site.ini' );
        $cacheTime = intval( $config->variable( 'RSSSettings', 'CacheTime' ) );
        if ( $config->variable( 'DebugSettings', 'DebugOutput' ) == 'enabled' )
        {
            $cacheTime = -1;
        }
        if ( $cacheTime <= 0 )
        {
            $rssContent = $this->handler->generateFeed();
        }
        else
        {
            $cacheDir = eZSys::cacheDirectory();
            $currentSiteAccessName = $GLOBALS['eZCurrentAccess']['name'];
            $cacheFilePath = $cacheDir . '/ocrss/' . md5( $currentSiteAccessName . $this->handler->cacheKey() ) . '.xml';

            if ( !is_dir( dirname( $cacheFilePath ) ) )
            {
                eZDir::mkdir( dirname( $cacheFilePath ), false, true );
            }

            $cacheFile = eZClusterFileHandler::instance( $cacheFilePath );

            if ( !$cacheFile->exists() or ( time() - $cacheFile->mtime() > $cacheTime ) )
            {
                $rssContent = $this->handler->generateFeed();
                $cacheFile->storeContents( $rssContent, 'ocrsscache', 'xml' );
            }
            else
            {
                $lastModified = gmdate( 'D, d M Y H:i:s', $cacheFile->mtime() ) . ' GMT';

                if( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
                {
                    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'];

                    // Internet Explorer specific
                    $pos = strpos($ifModifiedSince,';');
                    if ( $pos !== false )
                        $ifModifiedSince = substr( $ifModifiedSince, 0, $pos );

                    if( strcmp( $lastModified, $ifModifiedSince ) == 0 )
                    {
                        header( 'HTTP/1.1 304 Not Modified' );
                        header( 'Last-Modified: ' . $lastModified );
                        header( 'X-Powered-By: eZ Publish' );
                        eZExecution::cleanExit();
                    }
                }
                $rssContent = $cacheFile->fetchContents();
            }
        }

        echo $rssContent;
    }
}

?>