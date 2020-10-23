{**
 * plugins/importexport/copernicus/index.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Copyright (c) 2020 Eko Didik Widianto
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List of operations this plugin can perform
 *
 * Changes:
 * - 20201023,Eko Didik Widianto: Rewrite initial code from DOAJ import/export plugin
 *   didik@live.undip.ac.id
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.copernicus.displayName"}
{include file="common/header.tpl"}
{/strip}

<br />
<strong>Index Copernicus International url: </strong><a href="https://journals.indexcopernicus.com/" target="_blank">https://journals.indexcopernicus.com</a>
<h3>{translate key="plugins.importexport.copernicus.export"}</h3>
<ul>
	<li><a href="{plugin_url path="issues"}">{translate key="plugins.importexport.copernicus.export.issue"}</a>: {translate key="plugins.importexport.copernicus.export.issueInfo"}</li>
</ul>
<br />
<a href="https://journals.indexcopernicus.com/app/auth/login" target="_blank">{translate key="plugins.importexport.copernicus.export.contact"}</a>: {translate key="plugins.importexport.copernicus.export.contactInfo"}

{include file="common/footer.tpl"}
