[![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](LICENSE)

**NCweb** is a reasonably small (ish) PHP "application" that will allow you to expose a given folder of a Nextcloud instance and serve documents from it as if it would have been a regular website. The folder does not have to be shared in Nextcloud. You can serve `.html` (HTML) or `.md` (Markdown) files. NCweb can be configured to `render Markdown` files to HTML.

NCweb has been tested with PHP 7.2, Nginx 1.19.x, and Apache 2.x. It utilizes `sabre/dav` (client) and `PHP CommonMark` parser to do the heavy lifting. 

#### IMPORTANT: :raised_hand: Exposing a folder of a Nextcloud instance using NCweb as a public website MAY have security implications. NCweb does some basic attempts at sanitizing requests, but it relies on Nextcloud to handle malformed requests and die gracefully. If you are not sure this is up to your standards, DO NOT USE NCweb.

### Installation

Download the distribution or clone the repo. Files and folders should be placed in your web root, or another folder accessible to your web server (this is not your Nextcloud :partly_sunny: server). The web server must have PHP 7.2 or higher installed, with the `mbstring` functions. The `curl_exec()` function must not be disabled.

_OPTIONAL: Change the permissions of all files and folders to the lowest required level (i.e. 440/550)_

### Configuration, web server

Reasonable plain vanilla configurations of PHP, Nginx, and Apache can be used. There are, however, some things to keep in mind:

1. The intention is that NCweb's index.php is the "handler of all things". In other words, all requests should be passed to NCweb. It will in turn attempt to fetch the requested file from Nextcloud and serve it to the visitor. This includes .css, .html, .htm, .md, images, and so on. Typically, Nginx and Apache are often configured to serve these files locally, so you may need to change that.
2. The above most likely means that you need to add some sort of `rewrite` rules to Nginx or Apache.
3. NCweb requires outbound HTTPS access. I.e. it needs to be able to initiate HTTPS traffic to the Nextcloud server it's serving documents from. This is done to fetch the requested documents.
4. If you serve NCweb index.php from a sub-folder on your web server, e.g. `https://yourdomain.com/ncweb`, you need to make sure that this is used for all links and URLs used in the Nextcloud documents being served. If you have a folder for your CSS files, the URL to use in your content is /ncweb/css/filename.css. If you do not serve NCweb index.php from a sub-folder, the URL to use in your content is /css/filename.css, and so on.

### Configuration, Nextcloud

1. NCweb will not serve documents from the root folder of whatever account credentials you give it. It requires that you configure a sub-folder on the Nextcloud, e.g. /Website, and configure NCweb accordingly.
2. You should create an `app password` for the account from which you wish to serve your public web documents. This is configured under `SETTINGS > SECURITY > DEVICES & SESSIONS ("Create new app password")`. This is the password you should use in NCweb.

### Configuration, NCweb

1. Copy the file `config.inc.dist.php` to `config.inc.php` and place it in the same folder as index.php.
2. Edit `config.inc.php` and change it to your liking/needs.
3. Once you are done editing it, and things are working, you could (should) optionally set the file permissions of config.inc.php to the minimum required (e.g. 440).

### Troubleshooting

There are a number of things that can go wrong, in particular when it comes to combinations of PHP-FPM and Nginx/Apache. Looking at your web server log files will most likely give you a clue as to what the problem is. NCweb will also output some errors to the PHP log file. A good start is to go to your configured URL, and append /index.php and see what happens.

Make sure that the document you're trying to request actually exist in the configured folder on the Nextcloud instance, and try reaching it using your configured URL/name_of_document.html or URL/name_of_document.md.

### License

**NCweb** is licensed under the BSD-3 license.  See the [`LICENSE`](LICENSE) file for more details.

### External references

These links are not here for any sort of endorsement or marketing, they're purely for informational purposes.

* PHP CommonMark parser; https://commonmark.thephpleague.com/
* sabre/dav; https://sabre.io/
* Nextcloud; https://nextcloud.com

