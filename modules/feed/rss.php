<?php

$http = eZHTTPTool::instance();
/** @var eZModule $module */
$module = $Params['Module'];

$key = $Params['Key'];
$value = $Params['Value'];

try
{
    $handler = OCRSSHandler::instance( $key, $value );
    $handler->printRSS();
    eZExecution::cleanExit();
}
catch( Exception $e )
{
    eZDebug::writeError( $e->getMessage() );
    return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );    
}
