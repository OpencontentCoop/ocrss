<?php

$Module = array( 'name' => 'Custom RSS Feed' );

$ViewList['rss'] = array(
    'script' => 'rss.php',
    'functions' => array( 'rss' ),
    'params' => array ( 'Key', 'Value' ) );


$FunctionList = array( );
$FunctionList['rss'] = array();

?>