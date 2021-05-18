<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportDom.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportDom
 * @ingroup plugins_importexport_Copernicus
 *
 * @brief Copernicus import/export plugin DOM functions for export
 * @brief It uses the XML import file from https://journals.indexcopernicus.com/ic-import.xsd
 * @brief 20201023,Eko Didik Widianto: Rewrite code from DOAJ import/export plugin
 * @brief Credit: https://github.com/a-vodka/ojs_copernicus_export_plugin
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

class CopernicusExportDom {
	/**
	 * Generate the export DOM tree for a given journal.
	 * @param $doc object DOM object
	 * @param $journal object Journal to export
	 * @param $selectedObjects array
	 */
	function generateJournalDom($doc, $journal, $selectedObjects) {
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$pubArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$journalId = $journal->getId();

		// Records node contains all articles, each called a record
		$records = XMLCustomWriter::createElement($doc, 'ici-import');

		$issn = $journal->getSetting('onlineIssn');
		$issn = $issn ? $issn : $journal->getSetting('printIssn');

		$journal_elem = XMLCustomWriter::createChildWithText($doc, $records, 'journal', '', true);
        	XMLCustomWriter::setAttribute($journal_elem, 'issn', $issn);
		
		// retrieve selected issues
		$selectedIssues = array();
		if (isset($selectedObjects[DOAJ_EXPORT_ISSUES])) {
			$selectedIssues = $selectedObjects[DOAJ_EXPORT_ISSUES];
			
			// make sure the selected issues belong to the current journal
			foreach($selectedIssues as $key => $selectedIssueId) {
				$selectedIssue = $issueDao->getIssueById($selectedIssueId, $journalId);
				if (!$selectedIssue) unset($selectedIssues[$key]);
				// Issue node
				$issue_elem = XMLCustomWriter::createChildWithText($doc, $records, 'issue', '', true);
				$pub_issue_date = $selectedIssue->getDatePublished() ? date('Y-m-d', strtotime($selectedIssue->getDatePublished())) : '';

				XMLCustomWriter::setAttribute($issue_elem, 'number', $selectedIssue->getNumber());
				XMLCustomWriter::setAttribute($issue_elem, 'volume', $selectedIssue->getVolume());
				XMLCustomWriter::setAttribute($issue_elem, 'year', $selectedIssue->getYear());
				XMLCustomWriter::setAttribute($issue_elem, 'publicationDate', $pub_issue_date, false);
                
				$num_articles = 0;

				$pubArticles = $pubArticleDao->getPublishedArticles($selectedIssueId);

				foreach ($pubArticles as $pubArticle) {
					$articleNode = CopernicusExportDom::generateArticleDom($doc, $journal, $selectedIssue, $section, $pubArticle);

					XMLCustomWriter::appendChild($issue_elem, $articleNode);
					$num_articles++;
				}
				XMLCustomWriter::setAttribute($issue_elem, 'numberOfArticles', $num_articles, false);
				unset($issue_elem, $articleNode);
			}
		}

		return $records;
	}

	/**
	  * Generate the DOM tree for a given article.
	  * @param $doc object DOM object
	  * @param $journal object Journal
	  * @param $issue object Issue
	  * @param $section object Section
	  * @param $article object Article
	  */
	function generateArticleDom($doc, $journal, $issue, $section, $article) {
		$article_elem = XMLCustomWriter::createElement($doc, 'article');
		XMLCustomWriter::createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');

		$locales = array_keys($article->_data['title']);

		/* --- Localized nodes --- */
		foreach ($locales as $loc) {
			$lc = explode('_', $loc);
			$lang_version = XMLCustomWriter::createChildWithText($doc, $article_elem, 'languageVersion', '', true);
			XMLCustomWriter::setAttribute($lang_version, 'language', $lc[0]);
			
			/* --- Title and abstract --- */
			XMLCustomWriter::createChildWithText($doc, $lang_version, 'title', $article->getLocalizedTitle($loc), true);
			XMLCustomWriter::createChildWithText($doc, $lang_version, 'abstract', strip_tags($article->getLocalizedData('abstract', $loc)), true);

			/* --- FullText URL --- */
			foreach ($article->getGalleys() as $galley) {
				XMLCustomWriter::createChildWithText($doc, $lang_version, 'pdfFileUrl', Request::url(null, 'article', 'view', array($article->getId(),$galley->getId())));    
			}

			/* --- Article's publication date --- */
			if ($article->getDatePublished()) 				
				$publicationDate = date('Y-m-d', strtotime($article->getDatePublished()));
			else
				$publicationDate = date('Y-m-d', strtotime($issue->getDatePublished()));
			XMLCustomWriter::createChildWithText($doc, $lang_version, 'publicationDate', $publicationDate, false);
            
			/** --- FirstPage / LastPage (from PubMed plugin)---
			  * there is some ambiguity for online journals as to what
			  * "page numbers" are; for example, some journals (eg. JMIR)
			  * use the "e-location ID" as the "page numbers" in PubMed
			  */
			$pages = $article->getPages();
			if (preg_match("/([0-9]+)\s*-\s*([0-9]+)/i", $pages, $matches)) {
				// simple pagination (eg. "pp. 3-8")
				XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $matches[1]);
				XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', $matches[2]);
			} elseif (preg_match("/(e[0-9]+)/i", $pages, $matches)) {
				// elocation-id (eg. "e12")
				XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageFrom', $matches[1]);
				XMLCustomWriter::createChildWithText($doc, $lang_version, 'pageTo', $matches[1]);
			}

			/*--- DOI ---*/
			XMLCustomWriter::createChildWithText($doc, $lang_version, 'doi',  $article->getPubId('doi'), false);

			/* --- Keywords --- */
			if ($article->getSubject($loc)){
				$subjects = array_map('trim', explode(';', $article->getSubject($loc)));
				$keywords = XMLCustomWriter::createElement($doc, 'keywords');
				XMLCustomWriter::appendChild($lang_version, $keywords);
				foreach ($subjects as $keyword) {
					XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', $keyword, false);
				}
			}

		}

		/*--- Authors node ----*/
		$authors_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'authors', '', true);
		$index = 1;

		foreach ($article->getAuthors() as $author) {
			$author_elem = XMLCustomWriter::createChildWithText($doc, $authors_elem, 'author', '', true);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'name', ucfirst($author->getFirstName()), true);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'name2', ucfirst($author->getMiddleName()), false);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'surname', ucfirst($author->getLastName()), true);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'order', $index, true);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'instituteAffiliation', $author->getLocalizedAffiliation(), false);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);
			XMLCustomWriter::createChildWithText($doc, $author_elem, 'ORCID', $author->getData('orcid'), false);
			
			$index++;
		}

		/*--- Citation element ---*/
		$citation_text = $article->getData('citations');

		if ($citation_text) {
			$citation_arr = explode("\n", $citation_text);
			$references_elem = XMLCustomWriter::createChildWithText($doc, $article_elem, 'references', '', true);
			$index = 1;
			foreach ($citation_arr as $citation) {
				if (strlen(trim($citation))) { /*FIXME: the single blanks in a reference returned an error*/
					$reference_elem = XMLCustomWriter::createChildWithText($doc, $references_elem, 'reference', '', true);
					XMLCustomWriter::createChildWithText($doc, $reference_elem, 'unparsedContent', $citation, true);
					XMLCustomWriter::createChildWithText($doc, $reference_elem, 'order', $index, true);
					XMLCustomWriter::createChildWithText($doc, $reference_elem, 'doi', '', true);
					$index++;
				}
			}
		}
        
		return $article_elem; 
	}

}
?>
