<?php

import('lib.pkp.classes.plugins.ImportExportPlugin');
import('lib.pkp.classes.xml.XMLCustomWriter');

class RepecExportPlugin extends ImportExportPlugin
{
    function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
        $this->addLocaleData();
        return $success;
    }

    function getName()
    {
        return 'RepecExportPlugin';
    }

    function getDisplayName()
    {
        return __('plugins.importexport.repec.displayName');
    }

    function displayName()
    {
        return 'Repec export plugin';
    }

    function getDescription()
    {
        return __('plugins.importexport.repec.description');
    }

    function multiexplode($delimiters, $string)
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return $launch;
    }

    function generateIssueText(&$journal, &$issue)
    {
        $output = [];
        $issueNumber = $issue->getNumber();
        $issueYear = $issue->getYear();

        $sectionDao =& DAORegistry::getDAO('SectionDAO');
        $publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
        $articleFileDao =& DAORegistry::getDAO('ArticleGalleyDAO');
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
        foreach ($sectionDao->getByIssueId($issue->getId()) as $section) {
            $incr = 0;
            foreach ($publishedArticleDao->getPublishedArticlesBySectionId($section->getId(), $issue->getId()) as $article) {

                if (!$article->getStartingPage()) continue;

                $locales = array_keys($article->_data['title']);
                $output[] = "\nTemplate-Type: ReDIF-Article 1.0\n";

                foreach ($article->getAuthors() as $author) {
                    $author_FirstName = '';
                    $author_MiddleName = '';
                    $author_LastName = '';

                    if (method_exists($author, "getLocalizedFirstName")) { 
                        $author_FirstName = $author->getLocalizedFirstName();
                        $author_MiddleName = $author->getLocalizedMiddleName();
                        $author_LastName = $author->getLocalizedLastName();
                    } elseif (method_exists($author, "getLocalizedGivenName")) {
                        $author_FirstName = $author->getLocalizedGivenName();
                        $author_MiddleName = '';
                        $author_LastName = $author->getLocalizedFamilyName();
                    } else {
                        $author_FirstName = $author->getFirstName();
                        $author_MiddleName = $author->getMiddleName();
                        $author_LastName = $author->getLastName();
                    }

                    $output[] = 'Author-Name: ' . $author_FirstName . ' ' . $author_LastName . "\n";
                }

                foreach ($locales as $loc) {
                    $lc = explode('_', $loc);
                    $output[] = 'Title: ' . $article->getLocalizedTitle($loc) . "\n";
                    $output[] = 'Abstract: ' . strip_tags($article->getLocalizedData('abstract', $loc)) . "\n";
                    $output[] = 'Publication-Status: Published in "Journal of Community Positive Practices", ' . $issueNumber . ' ' .  $issueYear . "\n";

                    if (is_a($article, 'PublishedArticle')) {
                        foreach ($article->getGalleys() as $galley) {
                            $url = Request::url($journal->getPath()) . '/article/download/' . $article->getBestArticleId() . '/' . $galley->getBestGalleyId();
                            break;
                        }
                        $output[] = 'File-URL: ' . $url . "\n";
                        $output[] = 'File-Format: Application/pdf' . "\n";
                        $output[] = 'File-Function: First version, '.$issueYear . "\n";
                    }

                    $kwds = $submissionKeywordDao->getKeywords($article->getId(), array($loc));
                    $kwds = $kwds[$loc];
                    $j = 0;
                    $combine = '';
                    foreach ($kwds as $k) {
                       $combine .= '; ' . $k;
                        $j++;
                    }
                    $output[] = 'Keywords:' . substr($combine, 1) . "\n";

                    $incr++;
                    $output[] = 'Pages: ' .$article->getStartingPage(). '-'. $article->getEndingPage() . "\n";
                    $output[] = 'Issue: '.$issueNumber . "\n";
                    $output[] = 'Year: '.$issueYear . "\n";
                    $output[] = 'Number: '.$issueNumber . substr($issueYear, -2) . $incr . "\n";
                    $output[] = 'Handle: RePEc:cta:jcppxx:'.$issueNumber . substr($issueYear, -2) . $incr . "\n";
                }
            }
        }
        return implode("", $output);
    }

    function exportIssue(&$journal, &$issue, $outputFile = null)
    {
        $output = $this->generateIssueText($journal, $issue);
        if (!empty($outputFile)) {
            if (($h = fopen($outputFile, 'wb')) === false) return false;
            fwrite($h, $output);
            fclose($h);
        } else {
            header("Content-Type: text/plain");
            header("Cache-Control: private");
            header("Content-Disposition: attachment; filename=\"" . 'jcpp' . '_' . $issue->getNumber() . '_' . $issue->getYear() . ".txt\"");
            echo $output;
        }
        return true;
    }

    function display($args, $request)
    {
        parent::display($args, $request);
        $issueDao =& DAORegistry::getDAO('IssueDAO');
        $journal =& $request->getJournal();
        $action = array_shift($args);
        if ($action === 'exportIssue') {
            $issueId = array_shift($args);
            $issue = $issueDao->getById($issueId, $journal->getId());
            if (!$issue) {
                $request->redirect();
                return;
            }
            $this->exportIssue($journal, $issue);
            return;
        }

        $journal =& Request::getJournal();
        $issueDao =& DAORegistry::getDAO('IssueDAO');
        $issues = $issueDao->getIssues($journal->getId(), Handler::getRangeInfo($request, 'issues'));

        $templateMgr = TemplateManager::getManager($request);

        if (method_exists($this, "getTemplateResource")) {
            $templateMgr->assignByRef('issues', $issues);
            $templateMgr->display($this->getTemplateResource('issues.tpl'));
        } else {
            $templateMgr->assign_by_ref('issues', $issues);
            $templateMgr->display($this->getTemplatePath() . '/templates/issues.tpl');
        }
    }

    function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    function usage($scriptName)
    {
        echo "test";
    }
}