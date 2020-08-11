<?php

// Locale, for multibyte stuff. It does not change anything in relation to
// output
$GLOBALS ['nc_dav_web_server_locale'] = 'sv_SE.UTF-8';

// The BASE URL of your Nextcloud server without a trailing slash
$GLOBALS ['nc_dav_web_server_url'] = 'https://enter.your.url.here';

// The Nextcloud username. Create a separate user for this with very restricted access!
$GLOBALS ['nc_dav_web_server_username'] = 'nextcloud_username';

// The APPLICATION password, do not use the actual Nextcloud account password!
$GLOBALS ['nc_dav_web_server_password'] = 'nextcloud_password';

// The folder from where you want the root of the web site to be served. This
// cannot be empty, i.e. you cannot server documents from the root folder.
$GLOBALS ['nc_dav_web_root'] = '/Nextcloud/Subdirectory/For/Content';

// The array index we should use for the $_SERVER array
$GLOBALS ['nc_dav_web_server_var'] = 'REQUEST_URI';

// Our name, in an URL, this is removed from "REQUEST_URI" (see above). This
// should not have a trailing slash; if index.php is placed at the top level,
// this option should be an empty string.
$GLOBALS ['nc_dav_web_root_url'] = '';

// Default to index.md instead of index.html and enable rendering of Markdown
// to HTML. Nextcloud must return the content-type as text/markdown for the
// rendering to kick in.
$GLOBALS ['nc_dav_web_markdown'] = true;

?>
