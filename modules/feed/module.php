<?php

$Module = array( 'name' => 'Custom RSS Feed' );

$ViewList['rss'] = array(
    'script' => 'rss.php',
    'functions' => array( 'rss' ),
    'params' => array ( 'Key', 'Value' ) );

$ViewList['list'] = array(
    'script' => 'list.php',
    'functions' => array( 'rss' ),
    'params' => array () );

$FunctionList = array( );
$FunctionList['rss'] = array();

?>