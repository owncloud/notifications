<?php
if (!\defined('PHPUNIT_RUN')) {
	\define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../../lib/base.php';

\OC::$composerAutoloader->addPsr4('Test\\', OC::$SERVERROOT . '/tests/lib/', true);
// load notification unit test classes
\OC::$composerAutoloader->addPsr4('OCA\\Notifications\\Tests\\Unit\\', __DIR__, true);
