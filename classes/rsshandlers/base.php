<?php

abstract class OCRSSHandlerBase
{
    /**
     * @return eZContentObjectTreeNode[]
     */
    abstract function getNodes();

    /**
     * @return string
     */
    abstract function getFeedTitle();

    /**
     * @return string
     */
    abstract function getFeedAccessUrl();

    /**
     * @return string
     */
    abstract function getFeedDescription();

    /**
     * @return string
     */
    abstract function getFeedImageUrl();
    
    /**
     * @return string
     */
    abstract function cacheKey();

    /**
     * @param eZContentObjectTreeNode $node
     * @return array
     */
    function getAttributeMappings( eZContentObjectTreeNode $node = null )
    {
        $titleFields = (array) eZINI::instance( 'ocrss.ini' )->variable( 'FeedSettings', 'title' );
        $descriptionFields = (array) eZINI::instance( 'ocrss.ini' )->variable( 'FeedSettings', 'description' );
        $contentFields = (array) eZINI::instance( 'ocrss.ini' )->variable( 'FeedSettings', 'content' );
        $categoryFields = (array) eZINI::instance( 'ocrss.ini' )->variable( 'FeedSettings', 'category' );
        $enclosureFields = (array) eZINI::instance( 'ocrss.ini' )->variable( 'FeedSettings', 'enclosure' );        
        $dataMap = $node->attribute( 'data_map' );
        return array(
            'title' => $this->selectField( $titleFields, $dataMap ),
            'description' => $this->selectField( $descriptionFields, $dataMap ),
            'content' => $this->selectField( $contentFields, $dataMap ),
            'category' => $this->selectField( $categoryFields, $dataMap ),
            'enclosure' => $this->selectField( $enclosureFields, $dataMap )            
        );
    }

    protected function selectField( array $fieldList, $dataMap )
    {
        foreach( $fieldList as $field )
        {
            if ( array_key_exists( $field, $dataMap ))
            {
                return $dataMap[$field];
            }
        }
        return null;
    }

    /**
     * @return string
     */
    function getFeedBaseUrl(){
        $baseItemURL = '';
        eZURI::transformURI( $baseItemURL, false, 'full' );        
        return $baseItemURL;
    }

    /**
     * @return string
     */
    function getFeedLanguage()
    {
        $locale = eZLocale::instance();
        return $locale->httpLocaleCode();
    }

    /**
     * @return string
     */
    public function generateFeed()
    {
        $feed = new ezcFeed();

        $feed->title = htmlspecialchars(
            $this->getFeedTitle(), ENT_NOQUOTES, 'UTF-8'
        );

        /** @var ezcFeedLinkElement $link */
        $link = $feed->add( 'link' );
        $link->href = htmlspecialchars( $this->getFeedBaseUrl() . $this->getFeedAccessUrl(), ENT_NOQUOTES, 'UTF-8' );

        $feed->description = htmlspecialchars(
            $this->getFeedDescription(), ENT_NOQUOTES, 'UTF-8'
        );
        $feed->language = $this->getFeedLanguage();

        // to add the <atom:link> element needed for RSS2
        $feed->id = htmlspecialchars(
            $this->getFeedBaseUrl() . $this->getFeedAccessUrl(),
            ENT_NOQUOTES, 'UTF-8'
        );

        $imageURL = $this->getFeedImageUrl();
        if ( $imageURL !== false )
        {
            $imageURL = htmlspecialchars( $imageURL, ENT_NOQUOTES, 'UTF-8' );
            /** @var ezcFeedImageElement $image */
            $image = $feed->add( 'image' );

            // Required for RSS1
            $image->about = $imageURL;

            $image->url = $imageURL;
            $image->title = htmlspecialchars(
                $this->getFeedTitle(), ENT_NOQUOTES, 'UTF-8'
            );
            $image->link = $link->href;
        }        

        /** @var eZContentObjectTreeNode[] $nodeArray */
        $nodeArray = $this->getNodes();

        if ( is_array( $nodeArray ) && count( $nodeArray ) )
        {
            foreach ( $nodeArray as $node )
            {
                if ( $node->attribute('is_hidden') && !eZContentObjectTreeNode::showInvisibleNodes() )
                {
                    // if the node is hidden skip past it and don't add it to the RSS export
                    continue;
                }
                /** @var eZContentObject $object */
                $object = $node->attribute( 'object' );
                /** @var eZContentObjectAttribute[] $dataMap */
                $dataMap = $object->dataMap();
                
                $attributeMapping = $this->getAttributeMappings( $node );

                $title =  array_key_exists( 'title', $attributeMapping ) && $attributeMapping['title'] instanceof eZContentObjectAttribute ? $attributeMapping['title'] : null;
                $description = array_key_exists( 'description', $attributeMapping ) && $attributeMapping['description'] instanceof eZContentObjectAttribute ? $attributeMapping['description'] : null;
                $content = array_key_exists( 'content', $attributeMapping ) && $attributeMapping['content'] instanceof eZContentObjectAttribute ? $attributeMapping['content'] : null;;
                $category = array_key_exists( 'category', $attributeMapping ) && $attributeMapping['category'] instanceof eZContentObjectAttribute ? $attributeMapping['category'] : null;
                $enclosure = array_key_exists( 'enclosure', $attributeMapping ) && $attributeMapping['enclosure'] instanceof eZContentObjectAttribute ? $attributeMapping['enclosure'] : null;                

                /** @var ezcFeedEntryElement $item */
                $item = $feed->add( 'item' );

                $item->id = $object->attribute( 'remote_id' );
                $item->id->isPermaLink = false;

                $link = $item->add( 'link' );
                $nodeUrlAlias = $node->urlAlias();
                eZURI::transformURI( $nodeUrlAlias, false, 'full' );  
                $nodeURL = $this->urlEncodePath( $nodeUrlAlias );
                $link->href = htmlspecialchars( $nodeURL, ENT_NOQUOTES, 'UTF-8' );

                $itemCreatorObject = $node->attribute('creator');
                if ( $itemCreatorObject instanceof eZContentObject )
                {
                    /** @var ezcFeedPersonElement $author */
                    $author = $item->add( 'author' );
                    $author->name = htmlspecialchars(
                        $itemCreatorObject->attribute('name'), ENT_NOQUOTES, 'UTF-8'
                    );
                    $author->email = eZINI::instance()->variable( 'MailSettings', 'AdminEmail' );
                }

                if ( $title instanceof eZContentObjectAttribute )
                {
                    $titleContent = $title->attribute( 'content' );
                    if ( $titleContent instanceof eZXMLText )
                    {
                        $itemTitleText = $titleContent->attribute( 'output' )->attribute(
                            'output_text'
                        );
                    }
                    else
                    {
                        $itemTitleText = $titleContent;
                    }
                }
                else
                {
                    $itemTitleText = $node->attribute( 'object' )->attribute( 'name' );
                }
                $item->title = htmlspecialchars( $itemTitleText, ENT_NOQUOTES, 'UTF-8' );


                $itemDescriptionText = '';
                // description RSS element with respective class attribute content
                if ( $description instanceof eZContentObjectAttribute )
                {
                    $descContent = $description->attribute( 'content' );
                    if ( $descContent instanceof eZXMLText )
                    {
                        $itemDescriptionText = htmlspecialchars(
                            $descContent->attribute( 'output' )->attribute( 'output_text' ), ENT_NOQUOTES, 'UTF-8'
                        );
                    }
                    else if ( $descContent instanceof eZImageAliasHandler )
                    {
                        $itemImage   = $descContent->hasAttribute( 'rssitem' ) ? $descContent->attribute( 'rssitem' ) : $descContent->attribute( 'rss' );
                        $origImage   = $descContent->attribute( 'original' );
                        eZURI::transformURI( $itemImage['full_path'], true, 'full' );
                        eZURI::transformURI( $origImage['full_path'], true, 'full' );
                        $itemDescriptionText = '&lt;a href="' . htmlspecialchars( $origImage['full_path'] )
                                             . '"&gt;&lt;img alt="' . htmlspecialchars( $descContent->attribute( 'alternative_text' ) )
                                             . '" src="' . htmlspecialchars( $itemImage['full_path'] )
                                             . '" width="' . $itemImage['width']
                                             . '" height="' . $itemImage['height']
                                             . '" /&gt;&lt;/a&gt;';
                    }
                    else
                    {
                        $itemDescriptionText = htmlspecialchars(
                            $descContent, ENT_NOQUOTES, 'UTF-8'
                        );
                    }
                }
                elseif ( method_exists( 'OCOperatorsCollection', 'getAbstract' ) )
                {
                    $operators = new OCOperatorsCollection();
                    $itemDescriptionText = htmlspecialchars(
                        $operators->getAbstract( $node ), ENT_NOQUOTES, 'UTF-8'
                    );
                }
                $item->description = $itemDescriptionText;

                // category RSS element with respective class attribute content
                if ( $category  instanceof eZContentObjectAttribute )
                {
                    $categoryContent =  $category->attribute( 'content' );
                    if ( $categoryContent instanceof eZXMLText )
                    {
                        $itemCategoryText = $categoryContent->attribute( 'output' )->attribute( 'output_text' );
                    }
                    elseif ( $categoryContent instanceof eZKeyword )
                    {
                        $itemCategoryText = $categoryContent->keywordString();
                    }
                    elseif ( $categoryContent instanceof eZTags )
                    {
                        $itemCategoryText = $categoryContent->attribute( 'keyword_string' );
                    }
                    else
                    {
                        $itemCategoryText = $categoryContent;
                    }

                    if ( $itemCategoryText )
                    {
                        $cat = $item->add( 'category' );
                        $cat->term = htmlspecialchars(
                            $itemCategoryText, ENT_NOQUOTES, 'UTF-8'
                        );
                    }
                }

                // enclosure RSS element with respective class attribute content
                if ( $enclosure  instanceof eZContentObjectAttribute )
                {
                    $enc = false;
                    $encItemURL = false;
                    $enclosureContent = $enclosure->attribute( 'content' );
                    if ( $enclosureContent instanceof eZMedia )
                    {
                        $enc         = $item->add( 'enclosure' );
                        $enc->length = $enclosureContent->attribute('filesize');
                        $enc->type   = $enclosureContent->attribute('mime_type');
                        $encItemURL = 'content/download/' . $enclosure->attribute('contentobject_id')
                                    . '/' . $enclosureContent->attribute( 'contentobject_attribute_id' )
                                    . '/' . urlencode( $enclosureContent->attribute( 'original_filename' ) );
                        eZURI::transformURI( $encItemURL, false, 'full' );
                    }
                    else if ( $enclosureContent instanceof eZBinaryFile )
                    {
                        $enc         = $item->add( 'enclosure' );
                        $enc->length = $enclosureContent->attribute('filesize');
                        $enc->type   = $enclosureContent->attribute('mime_type');
                        $encItemURL = 'content/download/' . $enclosure->attribute('contentobject_id')
                                    . '/' . $enclosureContent->attribute( 'contentobject_attribute_id' )
                                    . '/version/' . $enclosureContent->attribute( 'version' )
                                    . '/file/' . urlencode( $enclosureContent->attribute( 'original_filename' ) );
                        eZURI::transformURI( $encItemURL, false, 'full' );
                    }
                    else if ( $enclosureContent instanceof eZImageAliasHandler )
                    {
                        $enc         = $item->add( 'enclosure' );
                        $origImage   = $enclosureContent->attribute( 'original' );
                        $enc->length = $origImage['filesize'];
                        $enc->type   = $origImage['mime_type'];
                        $encItemURL  = $origImage['full_path'];
                        eZURI::transformURI( $encItemURL, true, 'full' );
                    }
                    elseif ( $enclosure->attribute( 'data_type_string' ) == 'ezobjectrelationlist' )
                    {                        
                        foreach( $enclosureContent['relation_list'] as $related )
                        {                                                        
                            $relatedObject = eZContentObject::fetch( $related['contentobject_id'] );                            
                            if ( $relatedObject instanceof eZContentObject )
                            {
                                $relatedObjectDataMap = $relatedObject->attribute( 'data_map' );
                                if ( isset( $relatedObjectDataMap['image'] ) )
                                {
                                    $relatedObjectImageContent = $relatedObjectDataMap['image']->attribute( 'content' );
                                    if ( $relatedObjectImageContent instanceof eZImageAliasHandler )
                                    {
                                        $origImage = $relatedObjectImageContent->attribute( 'original' );
                                        $enc         = $item->add( 'enclosure' );                                        
                                        $enc->length = $origImage['filesize'];
                                        $enc->type   = $origImage['mime_type'];
                                        $encItemURL  = $origImage['full_path'];
                                        eZURI::transformURI( $encItemURL, true, 'full' );
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ( $encItemURL && $enc instanceof ezcFeedEnclosureElement )
                    {
                        $enc->url = htmlspecialchars( $encItemURL, ENT_NOQUOTES, 'UTF-8' );
                    }
                }

                if ( $content instanceof eZContentObjectAttribute )
                {
                    $contentAttributeContent =  $content->attribute( 'content' );
                    if ( $contentAttributeContent instanceof eZXMLText )
                    {
                        $itemContentText = $contentAttributeContent->attribute( 'output' )->attribute( 'output_text' );
                    }
                    elseif ( $contentAttributeContent instanceof eZKeyword )
                    {
                        $itemContentText = $contentAttributeContent->keywordString();
                    }
                    else
                    {
                        $itemContentText = $contentAttributeContent;
                    }

                    if ( $itemContentText )
                    {
                        $module = $item->addModule( 'Content' );
                        $module->encoded = $itemContentText;
                    }
                }
                
                $item->published = $object->attribute( 'published' );
                $item->updated = $object->attribute( 'published' );
            }
        }
        $rss = $feed->generate( 'rss2' );
        //$rss = preg_replace( '#(src|href)=([\'"])/#i',  sprintf( "$1=$2http:/%s/", $this->getFeedBaseUrl() ), $rss );
        return $rss;
    }

    protected function urlEncodePath( $url )
    {
        // Raw encode the path part of the URL
        $urlComponents = parse_url( $url );
        $pathParts = explode( '/', $urlComponents['path'] );
        foreach ( $pathParts as $key => $pathPart )
        {
            $pathParts[$key] = rawurlencode( $pathPart );
        }
        $encodedPath = implode( '/', $pathParts );

        // Rebuild the URL again, like this: scheme://user:pass@host/path?query#fragment
        $encodedUrl = $urlComponents['scheme'] . '://';

        if ( isset( $urlComponents['user'] ) )
        {
            $encodedUrl .= $urlComponents['user'];
            if ( isset( $urlComponents['pass'] ) )
            {
                $encodedUrl .= ':' . $urlComponents['pass'];
            }
            $encodedUrl .= '@';
        }

        $encodedUrl .= $urlComponents['host'];
        if ( isset( $urlComponents['port'] ) )
        {
            $encodedUrl .= ':' . $urlComponents['port'];
        }
        $encodedUrl .= $encodedPath;

        if ( isset( $urlComponents['query'] ) )
        {
            $encodedUrl .= '?' . $urlComponents['query'];
        }

        if ( isset( $urlComponents['fragment'] ) )
        {
            $encodedUrl .= '#' . $urlComponents['fragment'];
        }

        return $encodedUrl;
    }
}

?>