<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Index Queue Page Indexer frontend helper to ask the frontend page indexer to
 * index the page.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_frontendhelper_PageIndexer extends tx_solr_indexqueue_frontendhelper_Abstract {

	/**
	 * This frontend helper's executed action.
	 *
	 * @var	string
	 */
	protected $action = 'indexPage';

	/**
	 * the page currently being indexed.
	 *
	 * @var	tslib_fe
	 */
	protected $page;

	/**
	 * page indexing statuus.
	 *
	 * @var	boolean
	 */
	protected $pageIndexed = FALSE;

	/**
	 * Activates a frontend helper by registering for hooks and other
	 * resources required by the frontend helper to work.
	 */
	public function activate() {
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['initFEuser'][__CLASS__]        = 'EXT:solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pageindexer.php:&tx_solr_indexqueue_frontendhelper_PageIndexer->authorizeFrontendUser';
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['tslib_fe-PostProc'][__CLASS__] = 'EXT:solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pageindexer.php:&tx_solr_indexqueue_frontendhelper_PageIndexer->disableCaching';
		$pageIndexingHookRegistration                                                                         = 'EXT:solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pageindexer.php:&tx_solr_indexqueue_frontendhelper_PageIndexer';
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing'][__CLASS__]      = $pageIndexingHookRegistration;

			// indexes fields defined in plugin.tx_solr.index.queue.pages.fields
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['tx_solr_indexqueue_frontendhelper_PageFieldMappingIndexer'] = 'EXT:solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pagefieldmappingindexer.php:tx_solr_indexqueue_frontendhelper_PageFieldMappingIndexer';

			// making sure this instance is reused when called by the hooks registered before
			// t3lib_div::callUserFunction() and t3lib_div::getUserObj() use
			// these storages while the object was instantiated by
			// tx_solr_indexqueue_frontendhelper_Manager before.
			// t3lib_div::makeInstance() also uses a dedicated cache
		$GLOBALS['T3_VAR']['callUserFunction_classPool'][__CLASS__]     = $this;
		$GLOBALS['T3_VAR']['getUserObj'][$pageIndexingHookRegistration] = $this;

		$this->registerAuthorizationService();
# Since TypoScript is not available at this point we can not bind it to some TS configuration option whether to log or not
#		t3lib_div::devLog('Registered Solr Page Indexer authorization service', 'solr', 1, array(
#			'auth services' => $GLOBALS['T3_SERVICES']['auth']
#		));
	}

	/**
	 * Returns the status of whether a page was indexed.
	 *
	 * @return	array	page indexed status.
	 */
	public function getData() {
			// TODO return more information about a status, success / failure

		return array('pageIndexed' => (int) $this->pageIndexed);
	}


	#
	# Indexer auhtorization for access restricted pages / content
	#


	/**
	 * Fakes a logged in user to retrieve access restricted content.
	 *
	 * @return	void
	 */
	public function authorizeFrontendUser() {
		$accessRootline = $this->getAccessRootline();
		$stringAccessRootline = (string) $accessRootline;

		if (empty($stringAccessRootline)) {
			return;
		}

		if (!is_array($GLOBALS['TSFE']->fe_user->user)) {
			$GLOBALS['TSFE']->fe_user->user = array();
		}

		$groups    = $accessRootline->getGroups();
		$groupList = implode(',', $groups);

		$GLOBALS['TSFE']->fe_user->user['username']  = tx_solr_indexqueue_frontendhelper_AuthorizationService::SOLR_INDEXER_USERNAME;
		$GLOBALS['TSFE']->fe_user->user['usergroup'] = $groupList;
	}

	/**
	 * Gets the access rootline as defined by the request.
	 *
	 * @return	tx_solr_access_Rootline The access rootline to use for indexing.
	 */
	protected function getAccessRootline() {
		$stringAccessRootline = '';

		if ($this->request->getParameter('accessRootline')) {
			$stringAccessRootline = $this->request->getParameter('accessRootline');
		}

		$accessRootline = t3lib_div::makeInstance(
			'tx_solr_access_Rootline',
			$stringAccessRootline
		);

		return $accessRootline;
	}

	/**
	 * Registers an authentication service to authorize / grant the indexer to
	 * access protected pages.
	 *
	 * @return	void
	 */
	protected function registerAuthorizationService() {
		$overrulingPriority = $this->getHighestAuthenticationServicePriority() + 1;

		t3lib_extMgm::addService(
			'solr', // extension key
			'auth', // service type
			'tx_solr_indexqueue_frontendhelper_AuthorizationService', // service key
			array( // service meta data
				'title'       => 'Solr Indexer Authorization',
				'description' => 'Authorizes the Solr Index Queue indexer to access protected pages.',

				'subtype'     => 'getUserFE,authUserFE,getGroupsFE',

				'available'   => TRUE,
				'priority'    => $overrulingPriority,
				'quality'     => 100,

				'os'          => '',
				'exec'        => '',

				'classFile'   => $GLOBALS['PATH_solr'] . 'classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_authorizationservice.php',
				'className'   => 'tx_solr_indexqueue_frontendhelper_AuthorizationService',
			)
		);
	}

	/**
	 * Determines the highest priority of all registered authentication
	 * services.
	 *
	 * @return	integer	Highest priority of all registered authentication service
	 */
	protected function getHighestAuthenticationServicePriority() {
		$highestPriority = 0;

		if (is_array($GLOBALS['T3_SERVICES']['auth'])) {
			foreach ($GLOBALS['T3_SERVICES']['auth'] as $service) {
				if ($service['priority'] > $highestPriority) {
					$highestPriority = $service['priority'];
				}
			}
		}

		return $highestPriority;
	}


	#
	# Indexing
	#


	/**
	 * Generates the current page's URL.
	 *
	 * Uses the provided GET parameters, page id and language id.
	 *
	 * @return	string	URL of the current page.
	 */
	protected function generatePageUrl() {
		$contentObject = t3lib_div::makeInstance('tslib_cObj');

		$typolinkConfiguration = array(
			'parameter'                 => intval($this->page->id),
			'linkAccessRestrictedPages' => '1'
		);

		$language = t3lib_div::_GET('L');
		if (!empty($language)) {
			$typolinkConfiguration['additionalParams'] = '&L=' . $language;
		}

		$url = $contentObject->typoLink_URL($typolinkConfiguration);

			// clean up
		if ($url == '') {
			$url = '/';
		}
		$urlComponents = parse_url($url);
		if (empty($urlComponents['host'])) {
				// add the host to get an absolute URL
			$url = t3lib_div::locationHeaderUrl($url);
		}

		return $url;
	}

	/**
	 * Handles the indexing of the page content during post processing of a
	 * generated page.
	 *
	 * @param	tslib_fe	Typoscript frontend
	 */
	public function hook_indexContent(tslib_fe $page) {
		$this->page = $page;

		if (!$this->page->config['config']['index_enable']) {
			return;
		}

		try {
			$indexer = t3lib_div::makeInstance('tx_solr_Typo3PageIndexer', $page);
			$indexer->setIndexerMode(tx_solr_IndexerSelector::INDEXER_STRATEGY_QUEUE);
			$indexer->setPageAccessRootline($this->getAccessRootline());
			$indexer->setPageUrl($this->generatePageUrl());

			$this->pageIndexed = $indexer->indexPage();
		} catch (Exception $e) {
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('Exception while trying to index page ' . $page->id, 'solr', 3, array(
					$e->__toString()
				));
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pageindexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_pageindexer.php']);
}

?>