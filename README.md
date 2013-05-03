<a href="http://www.ecomdev.org/services/magento-development?utm_source=github&utm_medium=logo&utm_campaign=github">![EcomDev](http://www.ecomdev.org/wp-content/themes/ecomdev/images/logo.png)</a>

Alternative Url Rewrite Implementation by EcomDev
=================================================
* Full support of nested rewrites for categories
* Full support of product rewrites    
* Full support of duplicates handling
* Full support of transliteration during generation of url key
* Full support of history saving logic
* Url rewrite generation for product in anchor category


System Requirements
-------------------
* MySQL 5.x or higher
* Magento 1.4.x or higher

Compatibility
-------------

Currently extension tested on these Magento versions: 
CE 1.4.2, 1.5.1, 1.6.0

NOTICE
------
Currently this extension is in beta version, don't install it directly on live website without checking its stability on semilive/prelive/dev version

Issue with Stored Routines
-----------------------------
If you install extension on one db but then transfer db to anohter instance, it is possible that you forget to include stored porcedures with your dump.
The fix can be done by performing the following action:
* Drop all `ecomdev_urlrewrite_*` tables
* Delete record from `core_resource` with code `ecomdev_urlrewrite_setup`

