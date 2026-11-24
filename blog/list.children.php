<?php

/**
 * The page controller includes this file to build the main list. The Sitetypes AJAX
 * endpoint loads the same definition so it can render just the list when filters change.
 */

use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site;
use QUI\Projects\Media\Image;
use QUI\Projects\Media\Utils as MediaUtils;

/** @var Site $Site */
$entryType = 'quiqqer/blog:blog/entry';

$ChildrenList = new ChildrenList([
    'ownJsonLd' => false,
    'showContent' => false,
    'showImages' => $Site->getAttribute('quiqqer.settings.blog.showImages'),
    'showHeader' => $Site->getAttribute('quiqqer.settings.blog.showHeader'),
    'showShort' => $Site->getAttribute('quiqqer.settings.blog.showShort'),
    'showCreator' => $Site->getAttribute('quiqqer.settings.blog.showCreator'),
    'showTime' => $Site->getAttribute('quiqqer.settings.blog.showTime'),
    'showDate' => $Site->getAttribute('quiqqer.settings.blog.showDate'),
    'Site' => $Site,
    'Project' => $Site->getProject(),
    'isMainList' => true,
    'filter' => $Site->getAttribute('quiqqer.settings.blog.filter') ?: 'disabled',
    'tags' => $Site->getAttribute('quiqqer.settings.blog.tags'),
    'byType' => $Site->getAttribute('quiqqer.settings.blog.sitesToDisplay') === 'all' ? $entryType : false,
    'where' => [
        'type' => $entryType
    ],
    'limit' => $Site->getAttribute('quiqqer.settings.blog.max'),
    'itemtype' => 'https://schema.org/Blog',
    'child-itemtype' => 'https://schema.org/BlogPosting',
    'child-itemprop' => 'blogPost',
    'display' => $Site->getAttribute('quiqqer.settings.blog.template'),
    'cardLayout' => $Site->getAttribute('quiqqer.settings.blog.cards.layout'),
    'cardColumns' => $Site->getAttribute('quiqqer.settings.blog.cards.columns'),
    'cardColumnsTablet' => $Site->getAttribute('quiqqer.settings.blog.cards.columnsTablet'),
    'cardColumnsMobile' => $Site->getAttribute('quiqqer.settings.blog.cards.columnsMobile'),
    'cardImageFit' => $Site->getAttribute('quiqqer.settings.blog.cards.imageFit'),
    'cardAspectRatio' => $Site->getAttribute('quiqqer.settings.blog.cards.aspectRatio'),
    'cardGap' => $Site->getAttribute('quiqqer.settings.blog.cards.gap'),
    'mediaImagePosition' => $Site->getAttribute('quiqqer.settings.blog.media.imagePosition'),
    'mediaImageWidth' => $Site->getAttribute('quiqqer.settings.blog.media.imageWidth'),
    'pinnedAttribute' => 'quiqqer.settings.blog.pinned',
    'pinnedOrder' => 'release_from DESC'
]);

$ChildrenList->addEvent('onMetaList', static function (
    ChildrenList $ChildrenList,
    Site $Site,
    QUI\Controls\Utils\MetaList $MetaList
) {
    $MetaList->add('headline', $Site->getAttribute('title'));
    $MetaList->add('datePublished', $Site->getAttribute('release_from'));
    $dateModified = QUI\Utils\StructuredData::getModificationDate(
        $Site->getAttribute('c_date'),
        $Site->getAttribute('e_date')
    );

    if ($dateModified !== null) {
        $MetaList->add('dateModified', $dateModified);
    }

    $MetaList->add('mainEntityOfPage', $Site->getUrlRewritten());

    try {
        // author
        $User = QUI::getUsers()->get($Site->getAttribute('c_user'));
        $MetaList->add('author', $User->getName());
    } catch (QUI\Exception $Exception) {
        if (
            !$Exception instanceof QUI\Users\Exception
            || $Exception->getCode() !== 404
        ) {
            QUI\System\Log::addInfo($Exception->getMessage(), [
                'project' => $Site->getProject()->getName(),
                'lang' => $Site->getProject()->getLang(),
                'site' => $Site->getId()
            ]);
        }
    }

    // publisher
    $Publisher = new QUI\Controls\Utils\MetaList\Publisher();
    $Publisher->importFromProject($Site->getProject());
    $MetaList->add('publisher', $Publisher);

    // image
    $image = $Site->getAttribute('image_site');

    if (str_contains($image, 'fa-')) {
        $image = '';
    }

    if (MediaUtils::isMediaUrl($image)) {
        try {
            $Image = MediaUtils::getImageByUrl($image);
            $image = $Image->getSizeCacheUrl();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addInfo($Exception->getMessage());
            $image = '';
        }
    }

    // use default
    if (empty($image)) {
        try {
            $Placeholder = $Site->getProject()->getMedia()->getPlaceholderImage();

            if ($Placeholder instanceof Image) {
                $image = $Placeholder->getSizeCacheUrl();
            }
        } catch (QUI\Exception) {
        }
    }

    $MetaList->add('image', $image);
});

return $ChildrenList;
