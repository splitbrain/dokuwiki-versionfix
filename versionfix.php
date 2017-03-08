#!/usr/bin/php
<?php
use splitbrain\DokuWikiVersionFix\DokuwikiClient;
use splitbrain\DokuWikiVersionFix\Extension;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

require 'vendor/autoload.php';

/**
 * Easily update plugin versions
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class VersionFixCLI extends CLI
{

    protected $credentials = array();

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     */
    protected function setup(Options $options)
    {
        $options->setHelp(
            "Update the version of a plugin or template"
        );

        $options->registerArgument(
            'extension|email',
            'The name of the extension to update. Templates have to be prefixed with \'template:\'. ' .
            'You can also provide your email address and the tool will check all your extensions.',
            true
        );
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options)
    {
        $this->loadCredentials();

        $args = $options->getArgs();
        $arg = array_shift($args);
        $extensions = DokuwikiClient::getRepoData($arg);
        $this->info('found ' . count($extensions) . ' matching extensions');
        foreach ($extensions as $extension) {
            $this->fixVersion($extension);
        }
    }

    /**
     * Updates the versions of the given extension
     *
     * @param array $repoinfo
     */
    protected function fixVersion($repoinfo)
    {
        $extension = new Extension($this, $repoinfo, $this->credentials);
        $extension->fixVersion();

    }

    /**
     * Load credentials from config file
     */
    protected function loadCredentials()
    {
        $home = getenv("HOME");
        $conf = "$home/.dwversionfix.conf";
        $this->info('Searching for credentials in ' . $conf);
        if (!file_exists($conf)) {
            file_put_contents(
                $conf,
                "## Uncomment the following lines and set correct credentials\n" .
                "#dokuwiki_user  <your user at dokuwiki.org>\n" .
                "#dokuwiki_pass  <your password at dokuwiki.org>\n" .
                "#github_user    <your user at github.com>\n" .
                "#github_key     <your API key at github.com>\n"
            );
            @chmod($conf, 0600);
            $this->fatal('Please edit the credentials in ' . $conf);
        }
        $creds = DokuwikiClient::linesToHash(file($conf));

        if (
            empty($creds['dokuwiki_user']) ||
            empty($creds['dokuwiki_pass']) ||
            empty($creds['github_user']) ||
            empty($creds['github_key'])
        ) $this->fatal('Please edit the credentials in ' . $conf);

        $this->credentials = $creds;
    }


}

// Main
$cli = new VersionFixCLI();
$cli->run();

