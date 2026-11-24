<?php

/**
 * Blog List
 */

/** @var QUI\Projects\Project $Project */
/** @var QUI\Projects\Site $Site */

/** @var QUI\Interfaces\Template\EngineInterface $Engine */
/** @var QUI\Template $Template */

$ChildrenList = require __DIR__ . '/list.children.php';
$filterEnabled = $ChildrenList->isFilterEnabled();
$query = $_GET['list-q'] ?? '';

if (
    isset($_REQUEST['sheet']) && is_numeric($_REQUEST['sheet']) && (int)$_REQUEST['sheet'] > 1
    || isset($_REQUEST['limit'])
    || ($filterEnabled && ((is_string($query) && trim($query) !== '') || !empty($_GET['list-tag'])))
) {
    $Site->setAttribute('meta.robots', 'noindex,follow');
}

$hidePageContent = (bool)$Site->getAttribute('quiqqer.settings.blog.hidePageContentIfPaginationActive');
if ($filterEnabled && $hidePageContent) {
    $ChildrenList->addCSSFile(dirname(__DIR__) . '/bin/blog/list.css');
}

// Prepare the visible list and explicitly register its structured data before the page head.
$childrenListHtml = $ChildrenList->create();

try {
    $ListJsonLd = $ChildrenList->getJsonLd();

    if ($ListJsonLd !== null) {
        $Template->getJsonLd()->setJsonLdNode('blog', $ListJsonLd->getJsonLdData());
    }
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

$Engine->assign([
    'childrenListHtml' => $childrenListHtml,
    'ChildrenList' => $ChildrenList,
    'showPageContent' => !$hidePageContent || $filterEnabled || !isset($_REQUEST['sheet']),
    'hideOnLaterPages' => $hidePageContent && $filterEnabled
]);
