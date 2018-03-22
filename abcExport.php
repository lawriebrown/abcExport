<?php

/*
 * abcExport MediaWiki extension 
 * @description provides support for exporting abc music notation
 * in mediawiki as a abc file or a rendered pdf score of the abc.
 *
 * This extension is most likely used along with the ABCjs extension
 * that supports input, tagging & display on abc music notation.
 *
 * To use this extension, unzip the extension code from abcExport.zip 
 * in your wiki extensions dir, creating directory extensions/abcExport
 *
 * To support pdf score rendering you need to have the abcm2ps & gs
 * programs installed on your system, with locations configured in globals.
 *
 * To enable this extension, include in your LocalSettings.php file:
 * wfLoadExtension('abcExport');
 *
 * You can then redefine any of the following globals as needed:
 * $wgabcExport_abcm2ps - full path to abcm2ps command (or null if missing)
 *    if set to null, pdf score export is not available, so option removed
 * $wgabcExport_gs - full path to gs command
 * $wgabcExport_abcTag - start tag for abc content (same as used in ABCjs)
 * $wgabcExport_abcTagEnd - end tag for abc content (same as used in ABCjs)
 * $wgabcExport_mungeNames - whether to munge tune names by moving any
 *    leading The/An/A to the end in brackets, per site policy
 * $wgabcExport_addFileURL - whether to add F: file URL tag to source page for
 *    tune to the exported abc (or generated PDF score)
 *
 * the following globals should be correctly defined automatically
 * $wgabcExport_haveSessMgr - true if using MW1.27+ with SessionManager to
 *    provide persistence for tune list & filename in web session
 *
 * @license Creative Commons Attribution-NonCommercial-ShareAlike
 * Copyright © 2087 Lawrie Brown <Lawrie.Brown@canb.auug.org.au>
 * This extension was originally written starting with the code in the
 * ePubExport extension, and subsequently rewritten using the cookiecutter
 * extension builder, and some ideas from the CiteThisPage extension.
 * Those contributions are gratefully acknowledged.
 */

# code to support legacy LocalSettings.php inclusion of extension
# but tag as deprectiated
if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'abcExport' );
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['abcExport'] = __DIR__ . '/i18n';
    $wgExtensionMessagesFiles['abcExportAlias'] = __DIR__ . '/abcExport.i18n.alias.php';
    $wgExtensionMessagesFiles['abcExportMagic'] = __DIR__ . '/abcExport.i18n.magic.php';
    wfWarn(
        'Deprecated PHP entry point used for abcExport extension. ' .
        'Please use wfLoadExtension instead, see ' .
        'https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return true;
} else {
    die( 'This version of the abcExport extension requires MediaWiki 1.25+' );
}

