<?php

class OCRSSHandler
{
    /**
     * @var OCRSSHandlerBase
     */
    private $handler;

    private $identifier;

    /**
     * @var eZUser
     */
    private static $currentUser;

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
        $config = eZINI::instance( 'site.ini' );

        $lastModified = gmdate( 'D, d M Y H:i:s', time() ) . ' GMT';

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
                $this->enterAnonymous();
                $rssContent = $this->handler->generateFeed();
                $this->exitAnonymous();
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

        // Set header settings
        $httpCharset = eZTextCodec::httpCharset();
        if (eZINI::instance( 'ocrss.ini' )->variable('GeneralSettings', 'Cors') == 'enabled')
        {
            // Allow from any origin
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                //header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 3600');    // cache for 1 hour
            }

            // Access-Control headers are received during OPTIONS requests
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header("Accept: */*");
                header( 'Content-Type: application/rss+xml; charset=' . $httpCharset );
                header( "Accept-Language: *" );
                header( "Content-Language: it-IT" );

                // Access-Control-Allow-Methods
                if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                {
                    header("Access-Control-Allow-Methods: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']}, OPTIONS");
                }
                else
                {
                    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
                }

                // Access-Control-Allow-Headers
                if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                {
                    header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
                }
                else
                {
                    header('Access-Control-Allow-Headers: Authorization,DNT,X-Mx-ReqToken,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type');
                }
                eZExecution::cleanExit();
            }
        }

        header("Accept: */*");
        header( 'Content-Type: application/rss+xml; charset=' . $httpCharset );
        header( "Accept-Language: *" );
        header( "Content-Language: it-IT" );
        header( 'Last-Modified: ' . $lastModified );
        header( 'Content-Length: ' . strlen( $rssContent ) );
        header( 'X-Powered-By: eZ Publish' );
        echo $rssContent;
    }

    protected function enterAnonymous()
    {
        self::$currentUser = eZUser::currentUser();
        /** @var eZUser $anonymous */
        $anonymous = eZUser::fetch( eZUser::anonymousId() );
        eZUser::setCurrentlyLoggedInUser(
            $anonymous,
            $anonymous->attribute( 'contentobject_id' ),
            1
        );

    }

    protected function exitAnonymous()
    {
        eZUser::setCurrentlyLoggedInUser(
            self::$currentUser,
            self::$currentUser->attribute( 'contentobject_id' ),
            1
        );
    }

    public static function addLegacyRssRedirect()
    {
        $wildcard = eZURLWildcard::fetchBySourceURL('rss/feed/*', false);
        if ($wildcard) {
            $row = [
                'source_url' => 'rss/feed/*',
                'destination_url' => '/feed/rss/{1}',
                'type' => eZURLWildcard::TYPE_DIRECT,
            ];
            $wildcard = new eZURLWildcard($row);
            $wildcard->store();
            eZURLWildcard::expireCache();
        }
    }
}
