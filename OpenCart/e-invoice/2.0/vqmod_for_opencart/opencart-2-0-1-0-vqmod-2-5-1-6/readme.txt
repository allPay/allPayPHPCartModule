VQMod 2.5.1.6 for OpenCart 2.0.1.0
==================================

This package provides a XML-controlled search and add/replace modification system for OpenCart 2.0 core files. 
In addition to OpenCart's OCMod XML you can now also use the VQMod XML for describing the modifications.

See also the documentations at

<https://code.google.com/p/vqmod/wiki/Scripting>

and

<https://github.com/opencart/opencart/wiki/Modification-System>

for more details on the XML syntaxes.





Installation
============

Simply upload the folders and files from the 'upload' directory to your OpenCart server's main directory.

The following OpenCart files are replaced by the upload:

admin/controller/extension/installer.php
admin/controller/extension/modification.php

 



How to upload OpenCart modifications
====================================

There are 4 ways to upload modifications to an OpenCart server:

1)
Use the OpenCart admin backend at Extensions > Extension Installer to upload individual XML files to the OpenCart database. 
The XML file names must end with '.vqmod.xml' or '.ocmod.xml' depending on the type of XML used.

2)     
Use the OpenCart admin backend at Extensions > Extension Installer to upload zipped OpenCart extensions 
which include an XML file (the latter ends up in the OpenCart database). 
The name of the ZIP-file to be uploaded must end with '.vqmod.zip' or '.ocmod.zip'. 
The folders and files structure of the ZIP-file should look like this:

    upload/*
    install.xml (can contain VQmod or OCmod XML)
    install.php (optional)
    install.sql (optional)

3)     
Use FTP to upload XML files directly to OpenCart's system folder. 
The XML file names must end with '.vqmod.xml' or '.ocmod.xml' depending on the type of XML used.

4)     
Use FTP to upload VQmod XML files directly to OpenCart's vqmod/xml folder. The file names must end with '.xml'.

Note: Option 4) is useful for the many OpenCart extensions that come as ZIP-files, 
and which include an upload folder with amongst other a vqmod/xml sub-folder. 
The content of the ZIP-archive is extracted on a local computer and then everything from the extracted upload folder, 
including an embedded vqmod/xml folder, is uploaded to OpenCart's main folder.
     

After the OpenCart modifications are uploaded, it is important to go into the OpenCart admin backend at 
Extensions > Modifications and then click on the 'Refresh' button. 
This will re-create a cache for all modified OpenCart core files in the system/modification folder.
 



Further help and customized versions
====================================

This software has been successfully tested for a standard 
OpenCart 2.0.1.0. Don't use other Opencart versions with this software.

If you need a customized version of this module,
let us know and we can create one for a charge.

You can contact us at <http://www.mhccorp.com>

