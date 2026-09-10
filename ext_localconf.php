<?php

declare(strict_types=1);

use Andersundsehr\ShyConverter\Form\Element\VisibleShyElement;
use Andersundsehr\ShyConverter\Form\FormDataProvider\VisibleShyFormDataProvider;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsOverrides;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;

defined('TYPO3') or die();

$formEngineConfiguration = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine'];
$formEngineConfiguration['nodeRegistry'][1788535200] = [
    'nodeName' => VisibleShyFormDataProvider::RENDER_TYPE,
    'priority' => 40,
    'class' => VisibleShyElement::class,
];

$formEngineConfiguration['formDataGroup']['tcaDatabaseRecord'][VisibleShyFormDataProvider::class] = [
    'depends' => [
        TcaColumnsOverrides::class,
    ],
    'before' => [
        TcaColumnsProcessCommon::class,
    ],
];
unset($formEngineConfiguration);
