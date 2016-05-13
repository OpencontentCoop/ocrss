<?php

class LegacyRSSHandler extends OCRSSHandlerBase
{

    /**
     * @var eZRSSExport
     */
    protected $rssExport;

    function __construct(eZRSSExport $rssExport)
    {
        $this->rssExport = $rssExport;
    }

    /**
     * @see eZRSSExport::rssXmlContent
     * @return string
     * @throws ezcFeedUnsupportedElementException
     * @throws ezcFeedUnsupportedTypeException
     */
    public function generateFeed()
    {
        $locale = eZLocale::instance();

        // Get URL Translation settings.
        $config = eZINI::instance();
        if ($config->variable('URLTranslator', 'Translation') == 'enabled') {
            $useURLAlias = true;
        } else {
            $useURLAlias = false;
        }

        if ($this->rssExport->attribute('url') == '') {
            $baseItemURL = '';
            eZURI::transformURI($baseItemURL, false, 'full');
            $baseItemURL .= '/';
        } else {
            $baseItemURL = $this->rssExport->attribute('url') . '/'; //.$this->rssExport->attribute( 'site_access' ).'/';
        }

        $feed = new ezcFeed();

        $feed->title = htmlspecialchars(
            $this->rssExport->attribute('title'), ENT_NOQUOTES, 'UTF-8'
        );

        $link = $feed->add('link');
        $link->href = htmlspecialchars($baseItemURL, ENT_NOQUOTES, 'UTF-8');

        $feed->description = htmlspecialchars(
            $this->rssExport->attribute('description'), ENT_NOQUOTES, 'UTF-8'
        );
        $feed->language = $locale->httpLocaleCode();

        // to add the <atom:link> element needed for RSS2
        $feed->id = htmlspecialchars(
            $baseItemURL . 'rss/feed/' . $this->rssExport->attribute('access_url'),
            ENT_NOQUOTES, 'UTF-8'
        );

        $this->decorateFeed($feed);

        // required for ATOM
        $feed->updated = time();
        $author = $feed->add('author');
        $author->email = htmlspecialchars(
            $config->variable('MailSettings', 'AdminEmail'),
            ENT_NOQUOTES, 'UTF-8'
        );
        $creatorObject = eZContentObject::fetch($this->rssExport->attribute('creator_id'));
        if ($creatorObject instanceof eZContentObject) {
            $author->name = htmlspecialchars(
                $creatorObject->attribute('name'), ENT_NOQUOTES, 'UTF-8'
            );
        }

        $imageURL = $this->rssExport->fetchImageURL();
        if ($imageURL !== false) {
            $imageURL = htmlspecialchars($imageURL, ENT_NOQUOTES, 'UTF-8');
            $image = $feed->add('image');

            // Required for RSS1
            $image->about = $imageURL;

            $image->url = $imageURL;
            $image->title = htmlspecialchars(
                $this->rssExport->attribute('title'), ENT_NOQUOTES, 'UTF-8'
            );
            $image->link = $link->href;
        }

        $cond = array(
            'rssexport_id' => $this->rssExport->attribute('id'),
            'status' => $this->rssExport->attribute('status')
        );
        $rssSources = eZRSSExportItem::fetchFilteredList($cond);

        /** @var eZContentObjectTreeNode[] $nodeArray */
        $nodeArray = eZRSSExportItem::fetchNodeList($rssSources, $this->rssExport->getObjectListFilter());

        if (is_array($nodeArray) && count($nodeArray)) {
            $attributeMappings = eZRSSExportItem::getAttributeMappings($rssSources);

            foreach ($nodeArray as $node) {
                if ($node->attribute('is_hidden') && !eZContentObjectTreeNode::showInvisibleNodes()) {
                    // if the node is hidden skip past it and don't add it to the RSS export
                    continue;
                }
                $object = $node->attribute('object');
                $dataMap = $object->dataMap();
                if ($useURLAlias === true) {
                    $nodeURL = $this->rssExport->urlEncodePath($baseItemURL . $node->urlAlias());
                } else {
                    $nodeURL = $baseItemURL . 'content/view/full/' . $node->attribute('node_id');
                }

                // keep track if there's any match
                $doesMatch = false;

                $title = false;
                $description = false;
                $category = false;
                $enclosure = false;

                // start mapping the class attribute to the respective RSS field
                foreach ($attributeMappings as $attributeMapping) {
                    // search for correct mapping by path
                    if ($attributeMapping[0]->attribute('class_id') == $object->attribute('contentclass_id') and
                        in_array($attributeMapping[0]->attribute('source_node_id'), $node->attribute('path_array'))
                    ) {
                        // found it
                        $doesMatch = true;
                        /** @var eZContentObjectAttribute $title */
                        $title = $dataMap[$attributeMapping[0]->attribute('title')];
                        // description is optional
                        $descAttributeIdentifier = $attributeMapping[0]->attribute('description');
                        /** @var eZContentObjectAttribute $description */
                        $description = $descAttributeIdentifier ? $dataMap[$descAttributeIdentifier] : false;
                        // category is optional
                        $catAttributeIdentifier = $attributeMapping[0]->attribute('category');
                        /** @var eZContentObjectAttribute $category */
                        $category = $catAttributeIdentifier ? $dataMap[$catAttributeIdentifier] : false;
                        // enclosure is optional
                        $enclosureAttributeIdentifier = $attributeMapping[0]->attribute('enclosure');
                        /** @var eZContentObjectAttribute $enclosure */
                        $enclosure = $enclosureAttributeIdentifier ? $dataMap[$enclosureAttributeIdentifier] : false;
                        break;
                    }
                }

                if (!$doesMatch) {
                    // no match
                    eZDebug::writeError('Cannot find matching RSS attributes for datamap on node: ' . $node->attribute('node_id'),
                        __METHOD__);

                    return null;
                }

                // title RSS element with respective class attribute content
                $titleContent = $title->attribute('content');
                if ($titleContent instanceof eZXMLText) {
                    $outputHandler = $titleContent->attribute('output');
                    $itemTitleText = $outputHandler->attribute('output_text');
                } else {
                    $itemTitleText = $titleContent;
                }

                /** @var ezcFeedEntryElement $item */
                $item = $feed->add('item');

                $item->title = htmlspecialchars($itemTitleText, ENT_NOQUOTES, 'UTF-8');

                $link = $item->add('link');
                $link->href = htmlspecialchars($nodeURL, ENT_NOQUOTES, 'UTF-8');

                $item->id = $object->attribute('remote_id');
                $item->id->isPermaLink = false;

                $itemCreatorObject = $node->attribute('creator');
                if ($itemCreatorObject instanceof eZContentObject) {
                    $author = $item->add('author');
                    $author->name = htmlspecialchars(
                        $itemCreatorObject->attribute('name'), ENT_NOQUOTES, 'UTF-8'
                    );
                    $author->email = $config->variable('MailSettings', 'AdminEmail');
                }

                // description RSS element with respective class attribute content
                if ($description) {
                    $descContent = $description->attribute('content');
                    if ($descContent instanceof eZXMLText) {
                        $outputHandler = $descContent->attribute('output');
                        $itemDescriptionText = htmlspecialchars(
                            $outputHandler->attribute('output_text'), ENT_NOQUOTES, 'UTF-8'
                        );
                    } else if ($descContent instanceof eZImageAliasHandler) {
                        $itemImage = $descContent->hasAttribute('rssitem') ? $descContent->attribute('rssitem') : $descContent->attribute('rss');
                        $origImage = $descContent->attribute('original');
                        eZURI::transformURI($itemImage['full_path'], true, 'full');
                        eZURI::transformURI($origImage['full_path'], true, 'full');
                        $itemDescriptionText = '&lt;a href="' . htmlspecialchars($origImage['full_path'])
                                               . '"&gt;&lt;img alt="' . htmlspecialchars($descContent->attribute('alternative_text'))
                                               . '" src="' . htmlspecialchars($itemImage['full_path'])
                                               . '" width="' . $itemImage['width']
                                               . '" height="' . $itemImage['height']
                                               . '" /&gt;&lt;/a&gt;';
                    } else {
                        $itemDescriptionText = htmlspecialchars(
                            $descContent, ENT_NOQUOTES, 'UTF-8'
                        );
                    }
                    $item->description = $itemDescriptionText;
                }

                // category RSS element with respective class attribute content
                if ($category) {
                    $categoryContent = $category->attribute('content');
                    if ($categoryContent instanceof eZXMLText) {
                        $outputHandler = $categoryContent->attribute('output');
                        $itemCategoryText = $outputHandler->attribute('output_text');
                    } elseif ($categoryContent instanceof eZKeyword) {
                        $itemCategoryText = $categoryContent->keywordString();
                    } else {
                        $itemCategoryText = $categoryContent;
                    }

                    if ($itemCategoryText) {
                        $cat = $item->add('category');
                        $cat->term = htmlspecialchars(
                            $itemCategoryText, ENT_NOQUOTES, 'UTF-8'
                        );
                    }
                }

                // enclosure RSS element with respective class attribute content
                if ($enclosure) {
                    $encItemURL = false;
                    $enclosureContent = $enclosure->attribute('content');
                    if ($enclosureContent instanceof eZMedia) {
                        $enc = $item->add('enclosure');
                        $enc->length = $enclosureContent->attribute('filesize');
                        $enc->type = $enclosureContent->attribute('mime_type');
                        $encItemURL = 'content/download/' . $enclosure->attribute('contentobject_id')
                                      . '/' . $enclosureContent->attribute('contentobject_attribute_id')
                                      . '/' . urlencode($enclosureContent->attribute('original_filename'));
                        eZURI::transformURI($encItemURL, false, 'full');
                    } else if ($enclosureContent instanceof eZBinaryFile) {
                        $enc = $item->add('enclosure');
                        $enc->length = $enclosureContent->attribute('filesize');
                        $enc->type = $enclosureContent->attribute('mime_type');
                        $encItemURL = 'content/download/' . $enclosure->attribute('contentobject_id')
                                      . '/' . $enclosureContent->attribute('contentobject_attribute_id')
                                      . '/version/' . $enclosureContent->attribute('version')
                                      . '/file/' . urlencode($enclosureContent->attribute('original_filename'));
                        eZURI::transformURI($encItemURL, false, 'full');
                    } else if ($enclosureContent instanceof eZImageAliasHandler) {
                        $enc = $item->add('enclosure');
                        $origImage = $enclosureContent->attribute('original');
                        $enc->length = $origImage['filesize'];
                        $enc->type = $origImage['mime_type'];
                        $encItemURL = $origImage['full_path'];
                        eZURI::transformURI($encItemURL, true, 'full');
                    }

                    if (isset( $enc ) && $encItemURL) {
                        $enc->url = htmlspecialchars($encItemURL, ENT_NOQUOTES, 'UTF-8');
                    }
                }

                $item->published = $object->attribute('published');
                $item->updated = $object->attribute('published');

                $this->decorateFeedEntryElement($item, $node);
            }
        }

        return $feed->generate('rss2');
    }

    protected function decorateFeed(ezcFeed $feed)
    {
    }

    /**
     * @param ezcFeedEntryElement $item
     * @param eZContentObjectTreeNode $node
     */
    protected function decorateFeedEntryElement($item, $node)
    {
    }

    /**
     * @return eZContentObjectTreeNode[]
     */
    function getNodes()
    {
        $rssSources = eZRSSExportItem::fetchFilteredList(array(
            'rssexport_id' => $this->rssExport->attribute('id'),
            'status' => $this->rssExport->attribute('status')
        ));

        //@todo override this for openpa?
        return eZRSSExportItem::fetchNodeList($rssSources, $this->rssExport->getObjectListFilter());
    }

    /**
     * @return string
     */
    function getFeedTitle()
    {
        return $this->rssExport->attribute('title');
    }

    /**
     * @return string
     */
    function getFeedAccessUrl()
    {
        return $this->rssExport->attribute('url');
    }

    /**
     * @return string
     */
    function getFeedDescription()
    {
        return $this->rssExport->attribute('description');
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
        return $this->rssExport->attribute('access_url');
    }
}