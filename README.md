# abcExport - Mediawiki extension to support abc music notation export

Provides support for exporting abc music notation on pages in mediawiki
as a abc file or a rendered pdf score of the abc.

This extension is most likely used along with the ABCjs extension
that supports input, tagging & display on abc music notation.

## Features

To use this extension, unzip the extension code from abcExport.zip 
in your wiki extensions dir, creating directory extensions/abcExport

To support pdf score rendering you need to have the abcm2ps & gs
programs installed on your system, with locations configured in
extension.json.

To enable this extension, include in your LocalSettings.php file:
wfLoadExtension('abcExport');

You can then redefine any of the following globals as needed:

* $wgabcExport_abcm2ps - full path to abcm2ps command (or null if missing)
  if set to null, pdf score export is not available, so option removed
* $wgabcExport_gs - full path to gs command
* $wgabcExport_abcTag - start tag for abc content (same as used in ABCjs)
* $wgabcExport_abcTagEnd - end tag for abc content (same as used in ABCjs)
* $wgabcExport_mungeNames - whether to munge tune names by moving any
  leading The/An/A to the end in brackets, per site policy
* $wgabcExport_addFileURL - whether to add F: file URL tag to source page for
  tune to the exported abc (or generated PDF score)

