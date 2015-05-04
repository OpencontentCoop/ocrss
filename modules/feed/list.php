<?php

$module = $Params['Module'];
$tpl = eZTemplate::factory();

$exportList = eZRSSExport::fetchList();
$tpl->setVariable( 'legacy_rss_export_list', $exportList );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:feed/list.tpl' );
$Result['path'] = array( array( 'text' => 'Feed RSS', 'url' => false ) );