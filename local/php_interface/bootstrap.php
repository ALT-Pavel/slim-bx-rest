<?php

define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;

/**
 * Инициализация 1C-Bitrix.
 *
 * @throws \Bitrix\Main\SystemException
 */
function initBitrixCore()
{
	global $DB;

	$DB->db_Conn = Application::getInstance()::getConnection()->getResource();

	// Авторизация под администратором
	$_SESSION['SESS_AUTH']['USER_ID'] = 1;
}