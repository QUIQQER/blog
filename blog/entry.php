<?php

/**
 * author and date
 */

/** @var QUI\Projects\Project $Project */
/** @var QUI\Projects\Site $Site */
/** @var QUI\Template $Template */

/** @var QUI\Interfaces\Template\EngineInterface $Engine */

use QUI\Projects\Media\Image;
use QUI\Projects\Media\Utils as MediaUtils;

// default
$enableDateAndCreator = true;
$showCreator = false;
$showDate = false;

if ($Project->getConfig('blog.settings.entry.showCreator')) {
    $showCreator = $Project->getConfig('blog.settings.entry.showCreator');
}

if ($Project->getConfig('blog.settings.entry.showDate')) {
    $showDate = $Project->getConfig('blog.settings.entry.showDate');
}

switch ($Site->getAttribute('quiqqer.settings.blog.entry.dateAndCreator')) {
    case 'showAll':
        $showCreator = true;
        $showDate = true;
        break;

    case 'showCreator':
        // hide date
        $showCreator = true;
        $showDate = false;
        break;

    case 'showDate':
        // hide author
        $showDate = true;
        $showCreator = false;
        break;

    case 'hide':
        // disable date and author
        $enableDateAndCreator = false;
        break;
}

if (!$showCreator && !$showDate) {
    $enableDateAndCreator = false;
}

/**
 * comments
 */
$Request = QUI::getRequest();
$baseUrl = $Request->getScheme() . '://' . $Request->getHttpHost() . $Request->getBasePath();
$url = $baseUrl . $_SERVER['REQUEST_URI'];

$fbLangParam = $Site->getProject()->getLang() . '_';
$fbLangParam .= strtoupper($Site->getProject()->getLang());

$pageIdentifier = $Site->getProject()->getName() . '-';
$pageIdentifier .= $Site->getProject()->getLang() . '-';
$pageIdentifier .= $Site->getId();

// disable comments on one page
$comments = false;
if ($Site->getAttribute('quiqqer.settings.blog.entry.comments') != 'disable') {
    $comments = $Project->getConfig('blog.settings.general.enableComments');
}

$type = 'disqus';
if ($Project->getConfig('blog.settings.general.type')) {
    $type = $Project->getConfig('blog.settings.general.type');
}

$numberOfPosts = 6;
if ($Project->getConfig('blog.settings.facebook.numberOfPosts')) {
    $numberOfPosts = $Project->getConfig('blog.settings.facebook.numberOfPosts');
}


/**
 * More blog entries
 */
$amountOfSiblings = $Project->getConfig('blog.settings.entries.more.amount');

$moreEntriesShowDate = $Project->getConfig('blog.settings.entries.more.show_date');
$moreEntriesShowTime = $Project->getConfig('blog.settings.entries.more.show_time');

$previousSiblings = array_reverse($Site->previousSiblings($amountOfSiblings));
$nextSiblings = $Site->nextSiblings($amountOfSiblings);

// Structured data
$PageJsonLd = $Template->getJsonLd();
$BlogPosting = new QUI\Utils\JsonLd();
$blogPostingId = $Site->getUrlRewrittenWithHost() . '#blogposting';
$webPageId = $PageJsonLd->get('@id');

$BlogPosting->set('type', 'BlogPosting');
$BlogPosting->set('@id', $blogPostingId);
$BlogPosting->set('headline', $Site->getAttribute('title'));
$BlogPosting->set('description', $Site->getAttribute('short'));
$BlogPosting->set('datePublished', $Site->getAttribute('release_from'));
$dateModified = QUI\Utils\StructuredData::getModificationDate(
    $Site->getAttribute('c_date'),
    $Site->getAttribute('e_date')
);

if ($dateModified !== null) {
    $BlogPosting->set('dateModified', $dateModified);
}

if (!empty($webPageId)) {
    $BlogPosting->set('mainEntityOfPage', [
        '@id' => $webPageId
    ]);
}

/**
 * Author
 */
$authorProfile = QUI\Blog\Utils\AuthorData::resolve($Site);
$authorName = $authorProfile['name'] ?? null;
$Engine->assign('author', $authorName);

if ($authorName !== null) {
    $BlogPosting->set('author', $authorName);
}

// publisher
$Publisher = new QUI\Controls\Utils\MetaList\Publisher();
$Publisher->importFromProject($Site->getProject());
$publisher = $Publisher->toArray();
$pagePublisher = $PageJsonLd->get('publisher');

if (is_array($pagePublisher) && !empty($pagePublisher['@id'])) {
    $publisher['@id'] = $pagePublisher['@id'];
}

$BlogPosting->set('publisher', $publisher);

// image
$image = $Site->getAttribute('image_site');
$imageAbsolutePath = '';
$host = QUI::getRequest()->getHost();
$scheme = QUI::getRequest()->getScheme();

if (str_contains($image, 'fa-')) {
    $image = '';
}

if (MediaUtils::isMediaUrl($image)) {
    try {
        $Image = MediaUtils::getImageByUrl($image);
        // structured data needs absolute urls for images
        $imageAbsolutePath = $scheme . '://' . $host . $Image->getSizeCacheUrl();
    } catch (QUI\Exception $Exception) {
    }
}

// use default
if (empty($imageAbsolutePath)) {
    try {
        $Placeholder = $Site->getProject()->getMedia()->getPlaceholderImage();

        if ($Placeholder instanceof Image) {
            // structured data needs absolute urls for images
            $imageAbsolutePath = $scheme . '://' . $host . $Placeholder->getSizeCacheUrl();
        }
    } catch (QUI\Exception $Exception) {
    }
}

if (!empty($imageAbsolutePath)) {
    $BlogPosting->set('image', $imageAbsolutePath);
}

try {
    $PageJsonLd->set('mainEntity', [
        '@id' => $blogPostingId
    ]);
    $PageJsonLd->setJsonLdNode('blogPosting', $BlogPosting->getJsonLdData());
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

$Engine->assign([
    'enableDateAndCreator' => $enableDateAndCreator,
    'showCreator' => $showCreator,
    'showDate' => $showDate,
    'comments' => $comments,
    'type' => $type,
    'url' => $url,
    'pageIdentifier' => $pageIdentifier,
    'disqusLink' => $Project->getConfig('blog.settings.disqus.link') . '/embed.js',
    'fbLangParam' => $fbLangParam,
    'numberOfPosts' => $Project->getConfig('blog.settings.facebook.numberOfPosts'),
    'apiVer' => $Project->getConfig('blog.settings.facebook.apiVer'),
    'appId' => $Project->getConfig('blog.settings.facebook.appId'),
    'moreEntriesShowDate' => $moreEntriesShowDate,
    'moreEntriesShowTime' => $moreEntriesShowTime,
    'previousSiblings' => $previousSiblings,
    'nextSiblings' => $nextSiblings,
    'showTitle' => $Project->getConfig('blog.settings.entry.showTitle'),
    'showDescription' => $Project->getConfig('blog.settings.entry.showDescription')
]);
