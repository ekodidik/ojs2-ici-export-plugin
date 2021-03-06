<?php

/**
 * @file plugins/importexport/copernicus/CopernicusPlugin.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 * @brief 20201023,Eko Didik Widianto: Rewrite code from DOAJ import/export plugin
 * @brief didik@live.undip.ac.id
 *  
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

import('classes.plugins.ImportExportPlugin');

// Export types.
define('COPERNICUS_EXPORT_ISSUES', 0x01);

class CopernicusPlugin extends ImportExportPlugin {

	/** @var PubObjectCache */
	var $_cache;

	function _getCache() {
		if (!is_a($this->_cache, 'PubObjectCache')) {
			// Instantiate the cache.
			if (!class_exists('PubObjectCache')) { // Bug #7848
				$this->import('classes.PubObjectCache');
			}
			$this->_cache = new PubObjectCache();
		}
		return $this->_cache;
	}

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * @see PKPPlugin::getTemplatePath()
	 */
	function getTemplatePath() {
		return parent::getTemplatePath().'templates/';
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'CopernicusPlugin';
	}

	/**
	 * Get the display name for this plugin
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.importexport.copernicus.displayName');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	function getDescription() {
		return __('plugins.importexport.copernicus.description');
	}

	/**
	 * Display the plugin
	 * @param $args array
	 *
	 * This supports the following actions:
	 * - unregistered, issues, articles: lists with exportable objects
	 * - markRegistered: mark a single object (article, issue) as registered
	 * - export: export a single object (article, issue)
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager();
		parent::display($args, $request);
		$journal = $request->getJournal();

		switch (array_shift($args)) {
			case 'issues':
				return $this->_displayIssueList($templateMgr, $journal);
				break;
			case 'process':
				return $this->_process($request, $journal);
				break;
			default:
				$this->setBreadcrumbs();
				$templateMgr->display($this->getTemplatePath() . 'index.tpl');
		}
	}

	/**
	 * Export a journal's content
	 * @param $journal object
	 * @param $selectedObjects array
	 * @param $outputFile string
	 */
	function _exportJournal($journal, $selectedObjects, $outputFile = null) {
		$this->import('classes.CopernicusExportDom');
		$doc = XMLCustomWriter::createDocument();

		$journalNode = CopernicusExportDom::generateJournalDom($doc, $journal, $selectedObjects);
		XMLCustomWriter::appendChild($doc, $journalNode);

		if (!empty($outputFile)) {
			if (($h = fopen($outputFile, 'wb'))===false) return false;
			fwrite($h, XMLCustomWriter::getXML($doc));
			fclose($h);
		} else {
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: attachment; filename=\"journal-" . $journal->getId() . ".xml\"");
			XMLCustomWriter::printXML($doc);
		}
		return true;
	}

	/**
	 * Display a list of issues for export.
	 * @param $templateMgr TemplateManager
	 * @param $journal Journal
	 */
	function _displayIssueList($templateMgr, $journal) {
		$this->setBreadcrumbs(array(), true);

		// Retrieve all published issues.
		AppLocale::requireComponents(array(LOCALE_COMPONENT_OJS_EDITOR));
		$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
		$issueIterator = $issueDao->getPublishedIssues($journal->getId());

		// check whether all articles of an issue are doaj::registered or not
		$issues = array();
		while ($issue = $issueIterator->next()) {
			$issueId = $issue->getId();
			$articles = $this->_retrieveArticlesByIssueId($issueId);

			$issues[] = $issue;
			unset($issue);
		}
		unset($issueIterator);

		// Instantiate issue iterator.
		import('lib.pkp.classes.core.ArrayItemIterator');
		$rangeInfo = Handler::getRangeInfo('issues');
		$iterator = new ArrayItemIterator($issues, $rangeInfo->getPage(), $rangeInfo->getCount());

		// Prepare and display the issue template.
		$templateMgr->assign_by_ref('issues', $iterator);
		$templateMgr->display($this->getTemplatePath() . 'issues.tpl');
	}


	/**
	 * Return the issue of an article.
	 *
	 * The issue will be cached if it is not yet cached.
	 *
	 * @param $article Article
	 * @param $journal Journal
	 *
	 * @return Issue
	 */
	function _getArticleIssue($article, $journal) {
		$issueId = $article->getIssueId();

		// Retrieve issue if not yet cached.
		$cache = $this->_getCache();
		if (!$cache->isCached('issues', $issueId)) {
			$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
			$issue = $issueDao->getIssueById($issueId, $journal->getId(), true);
			assert(is_a($issue, 'Issue'));
			$nullVar = null;
			$cache->add($issue, $nullVar);
			unset($issue);
		}

		return $cache->get('issues', $issueId);
	}

	/**
	 * Retrieve all articles for the given issue
	 * and commit them to the cache.
	 * @param $issue Issue
	 * @return array
	 */
	function _retrieveArticlesByIssueId($issueId) {
		$articlesByIssue = array();
		$cache = $this->_getCache();

		if (!$cache->isCached('articlesByIssue', $issueId)) {
			$articleDao = DAORegistry::getDAO('PublishedArticleDAO');
			$articles = $articleDao->getPublishedArticles($issueId);
			if (!empty($articles)) {
				foreach ($articles as $article) {
					$cache->add($article, $nullVar);
					unset($article);
				}
				$cache->markComplete('articlesByIssue', $issueId);
				$articlesByIssue = $cache->get('articlesByIssue', $issueId);
			}
		}
		return $articlesByIssue;
	}

	/**
	 * Identify the issue of the given article.
	 * @param $article PublishedArticle
	 * @param $journal Journal
	 * @return array|null Return prepared article data or
	 *  null if the article is not from a published issue.
	 */
	function _prepareArticleData($article, $journal) {
		$nullVar = null;

		// Add the article to the cache.
		$cache = $this->_getCache();
		$cache->add($article, $nullVar);

		// Retrieve the issue.
		$issue = $this->_getArticleIssue($article, $journal);

		if ($issue->getPublished()) {
			$articleData = array(
				'issue' => $issue
			);
			return $articleData;
		} else {
			return $nullVar;
		}
	}

	/**
	 * Return the object types supported by this plug-in.
	 * @return array An array with object names and the
	 *  corresponding export types.
	 */
	function _getAllObjectTypes() {
		return array(
			'issue' => DOAJ_EXPORT_ISSUES,
		);
	}

	/**
	 * Process a request.
	 * @param $request PKPRequest
	 * @param $journal Journal
	 */
	function _process($request, $journal) {
		$objectTypes = $this->_getAllObjectTypes();
		$target = $request->getUserVar('target');
		$selectedIds = array();
		$action = '';
		
		switch($target) {
			case('issue'):
				$action = 'issues';
				$selectedIds = (array) $request->getUserVar('issueId');
				break;
			default: assert(false);
		}
		
		if (empty($selectedIds)) {
			$request->redirect(null, null, null, array('plugin', $this->getName(), $action));
		}

		$selectedObjects = array($objectTypes[$target] => $selectedIds);

		if ($request->getUserVar('export')) {
			return $this->_exportJournal($journal, $selectedObjects);
		}
		return false;
	}


	/**
	 * Add a notification.
	 * @param $request Request
	 * @param $message string An i18n key.
	 * @param $notificationType integer One of the NOTIFICATION_TYPE_* constants.
	 * @param $param string An additional parameter for the message.
	 */
	function _sendNotification($request, $message, $notificationType, $param = null) {
		static $notificationManager = null;

		if (is_null($notificationManager)) {
			import('classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
		}

		if (!is_null($param)) {
			$params = array('param' => $param);
		} else {
			$params = null;
		}

		$user = $request->getUser();
		$notificationManager->createTrivialNotification(
			$user->getId(),
			$notificationType,
			array('contents' => __($message, $params))
		);
	}
}
?>
