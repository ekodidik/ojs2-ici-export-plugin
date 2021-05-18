# Copernicus Index XML export plugin for OJS 2.4x
This plug-in allows the journal manager to export issues' metadata as an XML file for Index Copernicus International (ICI) indexing. It follows the XML scheme from ICI (https://journals.indexcopernicus.com/ic-import.xsd).

This plugin is mainly developed for OJS 2.4.8x. However, It may work in other 2.4x OJS versions.

# Installation

1. Download the latest release. Unzip the file and rename the extracted directory to copernicus. Compress the copernicus directory into copernicus.tar.gz;
2. As a journal manager, Open Plugin Management > Install A New Plugin. You must have the privilege to install a new plugin from the OJS site administrator;
3. Choose the copernicus.tar.gz file and click the Install button.

Once the XML file exported, follow the importing issues instruction through the ICI the journal system from https://journals.indexcopernicus.com/api/download/issues_manual_en.pdf or watch our installation video at https://www.youtube.com/watch?v=2st4Yxt2LAE&lc

# Troubleshooting

Please create an issue if you find a bug in installing, generating, and importing the XML file. Send the issue to didik@live.undip.ac.id.
To remove the plugin, you can delete folder "copernicus" in OJS_DIR/plugins/importexport/

# Upgrade
This plugin has not supported upgrading yet. Please ensure you have the new installation file. Follow these steps to upgrade the plugin:
1. Remove the old plugin
2. Reinstall the new one

# Credits

This plugin version was ported from https://github.com/a-vodka/ojs_copernicus_export_plugin. 
