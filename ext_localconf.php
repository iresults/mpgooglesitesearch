<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    $_EXTKEY,
    'Search',
    array(
        'Search' => 'index, result',

    ),
    // non-cacheable actions
    array(
        'Search' => 'result',
    )
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    $_EXTKEY,
    'Widget',
    array(
        'Search' => 'widget',

    ),
    // non-cacheable actions
    array()
);
