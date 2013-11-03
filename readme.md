u BP Forum Attachment Migration to bbPress
==========================================

The [u BP Forum Attachment plugin](http://wordpress.org/plugins/u-buddypress-forum-attachment/) (will be referred to in this document as "u BP" from here on)  is an older plugin used to upload attachments with the legacy forums component for [BuddyPress](http://buddypress.org).  The plugin does *not* work with the bbPress plugin.

This plugin was developed to migrate the attachment data created by u BP over to the [bbPress](http://bbpress.org) plugin for display only.


How to use?
- 
* Make sure you have converted the legacy forums over to bbPress.  If you haven't done this yet, [follow this guide](http://codex.buddypress.org/getting-started/guides/migrating-from-old-forums-to-bbpress-2/).
* Download, install and activate this plugin.
* Login to the WP admin dashboard and navigate to the "Tools > Forums" page.
* Next, click on the "Repair u BuddyPress Forum Attachment postdata" checkbox and hit the "Repair Items" button.  This will convert the data over.
* Once that is done, navigate to a group forum post that has a u BP Forum Attachment.  It should now be displayed.


Version
-
0.1 - Test release.


License
-
GPLv2 or later.