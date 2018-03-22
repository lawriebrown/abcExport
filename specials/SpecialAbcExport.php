<?php

if ( !defined( 'MEDIAWIKI' ) ) 
     die();

/*
 * AbcExport Special Page that provides support for exporting
 * abc music notation from pages in abc or pdf formats
 *
 * It may be called without params to display this special page, or
 * with a single page param (& format or cmd) to export or tag page and then
 * redirect back to calling page, or with form data to export tunes listed
 * and then redisplay this special page.
 *
 * @ingroup Extensions
 */

class SpecialAbcExport extends SpecialPage {
    # class instance variables for this special page
    var $title;
    var $article;
    var $wikiPage;
    var $parserOptions;
    var $pageText;
    var $abc;

    /*
     * constructor for this special page object
     */
    public function __construct() {
        parent::__construct( 'AbcExport' );
    }

    /*
     * Override parent to set where special page appears on Special:SpecialPages
     */
    protected function getGroupName() {
        return 'pagetools';
    }

    /*
     * process one page, returning just abc tune notation content
     * abc notation assumed surrounded by $wgabcExport_abcTag &
     * $wgabcExport_abcTagEnd tags (as used by ABCjs extension)
     * The provided page/tune name will be adjusted as needed.
     *
     * @param $page Name of page containing abc notation to export
     * @return abc tune notation found
     */
    public function save1page ( $page ) {
        # access required globals MW & ours
        global $wgUser;
        global $wgParser;
        global $wgScriptPath;
        global $wgServer;                       
        global $wgSitename;
        global $wgabcExport_abcTag;
        global $wgabcExport_abcTagEnd;
        global $wgabcExport_mungeNames;
        global $wgabcExport_addFileURL;

        # check if tune title has leading The/An/A & move around
        # per site policy for page names with these at end
        if ($wgabcExport_mungeNames) {
            if (preg_match('~^The\s+~', $page) )
                $page = preg_replace('~^The\s+~', '', $page) . ' (The)';
            else if (preg_match('~^An\s+~', $page) )
                $page = preg_replace('~^An\s+~', '', $page) . ' (An)';
            else if (preg_match('~^A\s+~', $page) )
                $page = preg_replace('~^A\s+~', '', $page) . ' (A)';
        }

        # construct page title object from page name
        $title = Title::newFromText( $page );
        if( is_null( $title ) ) { 
            return null;
        }

        # and check current user allowed to view it
        if( !$title->userCan('read') ){
            return null;
        }

        # get raw (not parsed) page content since need raw abc content
        $wikiPage = WikiPage::factory($title);
        $revision = $wikiPage->getRevision();
        $content = $revision->getContent( Revision::RAW );
        $pageText = ContentHandler::getContentText( $content );

        # scan page content in pageText & extract abc tune(s)
        $inABC = false;         # whether inside block of abc notation
        $foundABC = false;      # whether have found any abc notation on page
        $topABC = true;         # whether scanning initial abc info tags
        $addedF = false;        # whether have added File URL to source page
        $abc = "% exported tune from page '$page'\n";   # abc notation to export
        $pageText = explode("\n", $pageText);           # raw page text
        foreach($pageText as $index => $textLine) {
            # find start & end pre format text tags wrapping abc notation
            # $abc .= "%DBG> " . $textLine . "\n";      # debug if needed
            if (preg_match("~$wgabcExport_abcTag~", $textLine)) {
                $inABC = true;
                $foundABC = true;
            }
            else if (preg_match("~$wgabcExport_abcTagEnd~", $textLine))    {
                $abc .= "\n";
                $inABC = false;
            }
            # otherwise if inside block of abc notation
            else if ($inABC) {
                # check if have scanned passed initial abc info tags
                if ( $topABC &&
                     !preg_match("~^[XTBCDHNOSZ]:~", $textLine) &&
                     !preg_match("~^\s*$~", $textLine) &&
                     !preg_match("~^%~", $textLine) )   $topABC = false;
                # check if should add File URL to source page to abc
                if ($wgabcExport_addFileURL && !$topABC && !$addedF) {
                    $abc .= "F:" . $title->getFullURL() . "\n";
                    $addedF = true;
                }
                # save lines in abc pre block
                $abc .= $textLine . "\n";
            }
        }

        # add message if no abc content found
        if (!$foundABC)
            $abc .= "% WARNING - no abc tune content found!\n\n";

        # return abc notation found on page
        return $abc;
    }

    /*
     * render abc tune notation as pdf score writing data straight back
     * using external abcm2ps & gs programs
     *
     * @param $abc abc tune notation text
     */
    function abc2pdf( $abc ) {
        # access required globals MW & ours
        global $wgabcExport_abcm2ps;
        global $wgabcExport_gs;

        $gsOpts = "-dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sPAPERSIZE=a4 -dPDFSETTINGS=/prepress -sOutputFile=-";

        # write abc data into temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'abcExport_');
        $tf = fopen($temp_file, 'w') or
            die("abcExport unable to open tempfile $temp_file");
        fwrite($tf, $abc);
        fclose($tf);

        # call external abcm2ps & gs command to convert abc in temp file
        # PDF output is passed thru back to browser direct as
        # relevant HTTP headers have already been set for this
        passthru("$wgabcExport_abcm2ps -q -O- $temp_file | " .
            "$wgabcExport_gs $gsOpts -", $err);

        # remove temp file
        unlink($temp_file);
    }

    /*
     * process all named pages, extracting any abc tune content, and
     * returning to browser as file of either abc text or pdf score
     *
     * @param $pages list of pages to export
     * @param $filename name of exported file
     * @param $format export format (abc|pdf|txt)
     */
    function processabc($pages, $filename = 'abctunes', $format = 'abc') {
        # access required globals MW & ours
        global $wgRequest;
        global $wgSitename;
        global $wgServer;
        global $wgContLang;
        global $wgabcExportProperties;

        # set assorted values that depend on output format (default abc)
        $fileSuffix = 'abc';            # filename suffix is abc or pdf
        $transferEncoding = '8bit';     # encoding is 8bit or binary
        $contentType = 'text/vnd.abc';  # text/vnd.abc or application/pdf
        if ($format == '')    $format = 'abc';
        else if ($format == 'pdf') {    # set for PDF output format
            $fileSuffix = 'pdf';
            $transferEncoding = 'binary';
            $contentType = 'application/pdf';
        }
        else if ($format == 'txt') {    # set for text tune list
            $fileSuffix = 'txt';
            $transferEncoding = '8bit';
            $contentType = 'text/plain';
        }

        # adjust filename
        $filename = str_replace(" ", "_", $filename);
        $fileTime = date("D, d M Y H:i:s T");

        # now process list of tune/page names
        $i = 1;
        $this->abc = "";
        # add initial abc comment identifying tune source (if not txt format)
        if ( $format != 'txt' ) 
            $this->abc .= "%abc\n%%abc-creator selected tunes " .
                    "from $wgSitename at $fileTime as $format\n\n";

        foreach ($pages as $pg) {
            if ( $format == 'txt' ) {          # just output tune list
                $this->abc .= $pg . "\n";
                continue;
            }
            if ( preg_match('~^%~', $pg) ) {   # copy abc comment/directive
                $this->abc .= $pg . "\n";
                continue;
            }
            if ( preg_match('~^\s*$~', $pg) ) { # ignore any blank lines
                continue;
            }
            # else have a page name (valid or not) so try and use it
            $content = $this->save1page($pg);
            if ( $content !== null ) {
                $this->abc .= $content;
                $i++;
            }
        }

        # emit abc content with correct HTTP headers
        if (!headers_sent($headerFile, $headerLine) or die("<p><strong>Error:</strong> Unable to export file <strong>$filename.$fileSuffix</strong>. HTML Headers have already been sent from <strong>$headerFile</strong> in line <strong>$headerLine</strong></p>")) {

            header("Last-Modified: " . $fileTime);
            header("Expires: 0");
            header("Content-Type: " . $contentType);
            header('Content-Disposition: attachment; filename="' . $filename . '.' . $fileSuffix . '";' );
            header("Content-Transfer-Encoding: " . $transferEncoding);
            if ($format == 'pdf') {
                $this->abc2pdf($this->abc);    # convert to pdf
            } else {
                echo $this->abc;               # print abc/txt
            }
        }
    }

    /*
     * submit callback function to check form data, required by HTMLForm class
     * However, most handling is already done in execute()
     *
     * @param $formData data from form submission
     * @return false to redisplay current form | true to not display | error txt
     */
    static function formSubmit( $formData ) {
        # return "DBG: cmd=" . $formData['cmd'] . ", pagel=" . $formData['pagel'];
        return false;        # return false so form is redisplayed
    }

    /*
     * display special page content with provided list of tune/page names
     *
     * @param $pagel list of tune/page names
     */
    function showContent( $pagel ) {
        # access required globals MW & ours
        global $wgRequest;
        global $wgSitename;
        global $wgabcExport_pagel_cols;
        global $wgabcExport_pagel_rows;

        # get output object for special page
        $out = $this->getOutput();

        # determine if have Session Manager to preserve tagged page names
        $haveSessMgr = class_exists('\MediaWiki\Session\Session');    # MW1.7+

        # get session data from request to store current page list in
        if ($haveSessMgr) {
            $session = $wgRequest->getSession();
            $session->persist();    # make session persistent
        }

        # set filename either from saved or using default of sitename
        $filename = $wgSitename;        # by default use sitename
        if ($haveSessMgr) {
            $fn = $session->get('abcExport_filename', '');
            if (($fn != null) && ($fn != ""))   $filename = $fn;
        }

        # construct special page output with heading, descriptive text & form
        $this->setHeaders();

        $out->addHtml(wfMessage('abc_special_page_title')->parse());
        $out->addHtml( wfMessage('abc_export_text')->parse());
        if ($haveSessMgr)
            $out->addHtml( wfMessage('abc_export_text2')->parse());

        # display section header for form
        $out->addHtml( wfMessage('abc_list_title')->parse() . "\n");

        # construct form used to gather data from special page
        # using HTMLForm class to generate form
        $formDescriptor = [
            'pagel' => [
                'type' => 'textarea',
                'label-message' => 'abc_tune_list',
                'name' => 'pagel',
                'cols' => $wgabcExport_pagel_cols,
                'rows' => $wgabcExport_pagel_rows,
                'default' => ($haveSessMgr ?
                    $session->get('abcExport_pagel', '') :
                    "$pagel"),
            ],
            'filename' => [
                'type' => 'text',
                'label-message' => 'abc_filename',
                'name' => 'filename',
                'default' => "$filename",
            ],
            'format' => [
                'type' => 'select',
                'label-message' => 'abc_format_text',
                'name' => 'format',
                'options' => [
                    wfMessage('abc_format_abc')->plain() => 'abc',
                    wfMessage('abc_format_pdf')->plain() => 'pdf',
                    wfMessage('abc_format_txt')->plain() => 'txt',
                ],
                'default' => 'abc',
            ],
            'cmd' => [
                'type' => 'radio',
                'name' => 'cmd',
                'label-message' => 'abc_action',
                'options' => [
                    wfMessage('abc_export')->plain() => 'export',
                    wfMessage('abc_clear')->plain() => 'clear',
                    wfMessage('abc_save')->plain() => 'save',
                ],
                'default' => 'export',
            ],
        ];

        # create HTML form, set assorted options, then display
        $htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
        $htmlForm->setSubmitText( wfMessage('abc_submit') );
        $htmlForm->setSubmitCallback( [ 'SpecialAbcExport', 'formSubmit' ] );
        $htmlForm->setFormIdentifier( 'tunelist' );
        $htmlForm->show();

    }

    /*
     * execute request to render this special page (expected entry point)
     * may be called either to display special page, or to action response
     *
     * @param $par extra page arguments from URL
     */
    function execute( $par ) {
        # access required globals MW & ours
        global $wgRequest;
        global $wgabcExport_abcm2ps;
        
        # get title for this special page
        $self = SpecialPage::getTitleFor( 'AbcExport' );

        # get output object for special page
        $out = $this->getOutput();

        # determine if have Session Manager to preserve tagged page names
        $haveSessMgr = class_exists('\MediaWiki\Session\Session');    # MW1.7+

        $doexport = false;         # flag if exporting file or displaying page
        $pagel = $page = "";       # list of page names
        $filename = "";
        # get session data to store current page list in
        if ($haveSessMgr) {
            $session = $wgRequest->getSession();
            $session->persist();   # make session persistent
        }

        # see if posted response to special page, or request for it 
        # if posted form response, get tune/page list & filename from form
        if ($wgRequest->wasPosted()) {        # posted form response
            $pagel = $wgRequest->getText ('pagel');
            $pages = array_filter( explode( "\n", $pagel ), 'wfFilterPageabc' );
            $filename = $wgRequest->getText ('filename');
            $doexport = true;
        } else {                   # GET request, arguments in $par
            # special page request to export/tag/display page
            $page = isset( $par ) ? $par : $wgRequest->getText( 'page' );
            if ($page != "") {     # for specific wiki page
                $doexport = true; 
                $pagel = $page;    # change tune list to this tune
                $pages = array ($page);
                $filename = str_replace(" ", "_", $page);
            } else {               # initial call to show special page
                $pagel = $session->get('abcExport_pagel', '');
                $pages = array_filter( explode( "\n", $pagel ), 'wfFilterPageabc' );
                if ($haveSessMgr) 
                    $filename = $session->get('abcExport_filename', '');
            }
        }

        # get other form fields
        $format = $wgRequest->getText ('format');
        $cmd = $wgRequest->getText ('cmd');

        # process assorted commands other than the default export tune
        if ($cmd == 'clear' ) {    # clear page list
            $pagel = "";
            $filename = "";
            if ($haveSessMgr) {
                $session->set('abcExport_pagel', $pagel);
                $session->set('abcExport_filename', $filename);
            }
            # now redirect back to this page afresh
            $out->redirect($self->getFullURL());
            $doexport = false;
            return;
        }
        else if ($cmd == 'save' ) {    # save page list (then redisplay page)
            if ($haveSessMgr) {
                $session->set('abcExport_pagel', $pagel);
                $session->set('abcExport_filename', $filename);
            }
            $doexport = false;
        }
        else if ($cmd == 'tag' ) {    # add this page to page list
            # get saved page list if can, add tag page, then resave
            if ($haveSessMgr)
                $pagel = $session->get('abcExport_pagel', '');
            $pagel .= $page . "\n";    # add current page to list
            if ($haveSessMgr)
                $session->set('abcExport_pagel', $pagel);
            # now redirect back to tune page
            # construct page title object from page name
            $title = Title::newFromText( $page );
            $out->redirect($title->getFullURL());
            $doexport = false;
            return;
        }

        # if generating abc output return file with content & done 
        if ( $doexport ) {
            # save current page list in session
            if ($haveSessMgr) {
                $session->set('abcExport_pagel', $pagel);
                $session->set('abcExport_filename', $filename);
            }
            $out->setPrintable();
            $out->disable();
            $this->processabc ($pages, $filename, $format);
            return;
        }

        # otherwise display special page
        $this->showContent( $pagel );
    }
}

    /*
     * filter function used when exploding page list
     */
    function wfFilterPageabc( $page ) {
        return $page !== '' && $page !== null;
    }
?>
