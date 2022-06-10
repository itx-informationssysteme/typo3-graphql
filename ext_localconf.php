<?php
defined('TYPO3') or die('Access denied.');

/***************
 * Register Icons
 */
/** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon('systeminformation-basicdistribution', \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class, ['source' => 'EXT:typo3_graphql/Resources/Public/Icons/Extension.png']);
