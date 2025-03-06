# DokuWiki VersionFixer

This is a utility for DokuWiki plugin developers. End-Users should not bother with it.

## What does it do?

This tool ensures that your plugins (and templates) have the proper version set in your github repository and on dokuwiki.org. This way end users should always see correct update information in the extension manager.
 
It will perform the following steps:

1) determine the version in your plugin.info.txt at GitHub (date)
2) check when the last significant commit (non-translation update) happened in the GitHub repository
3) determine the version currently mentioned at the plugin's page at dokuwiki.org (lastupdate)
4) Take the higher version from 1) and 2) and update the repository and the dokuwiki.org pages accordingly
5) create git tags for all versions of your plugin by examining the changes to the plugin.info.txt

Yes, all of this is completely automatic. You could even set it up as a cronjob.

## Requirements

* PHP 5.6 or higher and [composer](https://getcomposer.org/)
* your plugins must have the GitHub repository listed at dokuwiki.org
* your plugins must use the ``master`` branch as the means of distributing the most current version
* a user account at dokuwiki.org

## Setup

* clone the repository
* run ``composer install``
* run ``versionfix.php`` - it will create an example ``~/.dwversionfix.conf`` file - edit it and add
  * your DokuWiki user and password
  * your GitHub username and [Personal Access Token](https://github.com/settings/tokens) (the ``public_repo`` scope should be enough)

## Running it

You can let it update a single plugin:

    versionfix.php myplugin

For updating a template, prefix the name with ``template:``
   
    versionfix.php template:mytemplate

Or you can let it run over all your plugins and templates registered at dokuwiki.org using the same email address as given on the plugin page:

    versionfix.php me@example.com

## Run via GitHub Actions

You can also run this script via GitHub Actions. Clone the repository, then go to `Settings` -> `Secrets and Variables` -> `Actions`.

Add your credentials as `Repository secrets`:

* `DOKUWIKI_USER` - your DokuWiki user
* `DOKUWIKI_PASS` - your DokuWiki password
* `GH_USER` - your GitHub username
* `GH_KEY` - your GitHub Personal Access Token

Then create a new variable under `Repository varialbles`:

* `CHECK` - a space separated list of all the extensions and email address you want to update 


## FAQ

**Isn't this bad? Shouldn't versions be carefully selected and updated when needed?**

Yes. In therory. But people (me) are bad at it. Since I point downloads to the ``master`` branch anyway, people get my changes immeadiately anyway. This just ensures there are proper update messages in the extension manager.

**Wouldn't it be better if dokuwiki.org would fetech uptodate info from GitHub directly instead of relying on page updates?**

Maybe. There is some redundancy between the ``plugin.info.txt`` file and what is recorded at the dokuwiki.org plugin pages. Someone™ would need to extend our plugin repository to automatically get data from GitHub, but nobody did, yet.

And we wouldn't want to force this development model (GitHub, direct master downloads, etc.) on every plugin developer.

**Vim or Emacs?**

Vim! Though mostly IntelliJ Idea for PHP.
