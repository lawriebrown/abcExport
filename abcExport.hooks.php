<?php

if ( !defined( 'MEDIAWIKI' ) ) 
     die();

/**
 * Hooks to add items to page navigation for the abcExport extension.
 * Based on code from the CiteThisPage extension.
 *
 * @ingroup Extensions
 */

class abcExportHooks {

    /**
     * hook function to add new navigation urls into skin Toolbox that 
     * call Special:AbcExport to:
     * + Export this tune abcs, and stay on tune page
     * + Export this pdf scors, and stay on tune pagee
     * + Tag tune for later export, and return back to the tune page
     * + Export multiple tunes
     *
     * @param SkinTemplate $skintemplate
     * @param $nav_urls
     * @param $oldid
     * @param $revid
     * @return bool
     */
    public static function onSkinTemplateBuildNavUrlsNav_urlsAfterPermalink(
        &$skintemplate, &$nav_urls, &$oldid, &$revid) {

        global $wgabcExport_abcm2ps;

        # check if have Session Manager to preserve tagged page names
        $haveSessMgr = class_exists('\MediaWiki\Session\Session');

        # check if in right namespace, $revid correct type & not empty
        $title = $skintemplate->getTitle();
        if ( !( $title->isContentPage() && $revid !== 0 &&
            !empty( $revid ) ) ) { return true; }

        # add Exportabc link
        $nav_urls['abcExportabc'] = [
            'text' => $skintemplate->msg( 'abc_export_link' )->text(),
            'href' => SpecialPage::getTitleFor( 'abcExport' )
                ->getLocalURL( [ 'page' => $title->getPrefixedDBkey() ] ),
            'id' => 't-exportabc',
            ];
        # add Exportpdf link, provided have abcm2ps program available
        if ( $wgabcExport_abcm2ps != null ) {
            $nav_urls['abcExportpdf'] = [
                'text' => $skintemplate->msg( 'pdf_export_link' )->text(),
                'href' => SpecialPage::getTitleFor( 'abcExport' )
                    ->getLocalURL( [ 'page' => $title->getPrefixedDBkey(),
                    'format' => 'pdf' ] ),
                'id' => 't-exportpdf',
                ];
        }
        # add Exporttag link, provided have MW Session Manager
        if ( $haveSessMgr ) {
            $nav_urls['abcExporttag'] = [
                'text' => $skintemplate->msg( 'tag_tune_link' )->text(),
                'href' => SpecialPage::getTitleFor( 'abcExport' )
                    ->getLocalURL( [ 'page' => $title->getPrefixedDBkey(),
                    'cmd' => 'tag' ] ),
                'id' => 't-exporttag',
                ];
        }
        # add Exportmulti link
        $nav_urls['abcExportmulti'] = [
            'text' => $skintemplate->msg( 'multi_export_link' )->text(),
            'href' => SpecialPage::getTitleFor( 'abcExport' )
                ->getLocalURL( ),
            'id' => 't-exportabc',
            ];

        return true;
    }

    /**
     * hook function to enable skin display of added Toolbox navigation links
     *
     * @param BaseTemplate $baseTemplate
     * @param array $toolbox
     * @return bool
     */
    public static function onBaseTemplateToolbox(
        BaseTemplate $baseTemplate, array &$toolbox ) {

        if ( isset( $baseTemplate->data['nav_urls']['abcExportabc'] ) ) {
            $toolbox['abcExportabc'] = $baseTemplate->data['nav_urls']['abcExportabc'];
        }
        if ( isset( $baseTemplate->data['nav_urls']['abcExportpdf'] ) ) {
            $toolbox['abcExportpdf'] = $baseTemplate->data['nav_urls']['abcExportpdf'];
        }
        if ( isset( $baseTemplate->data['nav_urls']['abcExporttag'] ) ) {
            $toolbox['abcExporttag'] = $baseTemplate->data['nav_urls']['abcExporttag'];
        }
        if ( isset( $baseTemplate->data['nav_urls']['abcExportmulti'] ) ) {
            $toolbox['abcExportmulti'] = $baseTemplate->data['nav_urls']['abcExportmulti'];
        }

        return true;
    }

}
