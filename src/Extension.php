<?php

namespace splitbrain\DokuWikiVersionFix;


use splitbrain\phpcli\CLI;

class Extension
{

    protected $cli;
    protected $github;
    protected $dokuwiki;

    protected $page;
    protected $infotxt;

    protected $version = array(
        'dw' => '0000-00-00',
        'txt' => '0000-00-00',
        'commit' => '0000-00-00'
    );

    /**
     * VersionFixExtension constructor.
     *
     * @param CLI $cli
     * @param array $repoinfo
     * @param array $credentials
     */
    public function __construct($cli, $repoinfo, $credentials)
    {
        $this->page = $repoinfo['page'];
        $this->infotxt = 'plugin.info.txt';
        if ($repoinfo['is_template']) $this->infotxt = 'template.info.txt';

        $this->cli = $cli;

        $this->github = new GithubClient($credentials['github_user'], $credentials['github_key']);
        $this->github->setRepo($repoinfo['github_user'], $repoinfo['github_repo']);

        $this->dokuwiki = new DokuwikiClient($credentials['dokuwiki_user'], $credentials['dokuwiki_pass']);

        $this->version['dw'] = $repoinfo['repoversion'];
        $this->version['txt'] = $this->fetchInfoTxtVersion();
        $this->version['commit'] = $this->fetchLastCommit();

    }

    public function fixVersion()
    {
        $this->cli->info('dokuwiki.org version: ' . $this->version['dw']);
        $this->cli->info('github.com version:   ' . $this->version['txt']);
        $this->cli->info('last real commit:     ' . $this->version['commit']);

        if ($this->version['dw'] == $this->version['txt'] && $this->version['txt'] == $this->version['commit']) {
            $this->cli->info('versions match, no update needed.');
            return;
        }

        $target = max($this->version['txt'], $this->version['commit']);
        $this->cli->info('target version:       ' . $target);

        if ($target != $this->version['txt']) {
            $this->updateGithub($this->version['txt'], $target);
        } else {
            $this->cli->info('info.txt is uptodate already.');
        }

        if ($target != $this->version['dw']) {
            $this->updateDokuWiki($this->version['dw'], $target);
        } else {
            $this->cli->info('extension page is uptodate already.');
        }

    }

    /**
     * Update the *info.txt file at github
     *
     * @param string $current the current version
     * @param string $target the target version to set
     */
    protected function updateGithub($current, $target)
    {
        $response = $this->github->read('contents/' . $this->infotxt);
        $sha = $response['sha'];

        // replace the date
        $content = base64_decode($response['content']);
        $newcontent = preg_replace('/(\n[ \t]*date[ \t]+)' . $current . '/s', '${1}' . $target, $content);
        if ($content == $newcontent) {
            $this->cli->fatal('Failed to update version in info.txt');
        }

        // prepare new data
        $data = array(
            'sha' => $sha,
            'message' => 'Version upped',
            'content' => base64_encode($newcontent)
        );

        $this->github->write('contents/' . $this->infotxt, $data);
        $this->cli->success('Updated info.txt at github.');
    }

    /**
     * @param string $current the current version
     * @param string $target the target version to set
     */
    protected function updateDokuWiki($current, $target)
    {
        $content = $this->dokuwiki->read($this->page);

        $current = preg_quote($current, '/');
        $newcontent = preg_replace('/(\n---- (?:plugin|template) ----.*?(?:lastupdate(?:_dt)? *: *))' . $current . '/s', '${1}' . $target, $content);
        if ($content == $newcontent) {
            $this->cli->error('Failed to adjust date in dokuwiki page. Date might differ from cached repo data.');
            return;
        }
        $this->dokuwiki->write($this->page, $newcontent);

        $this->cli->success('Updated ' . $this->page . ' at dokuwiki.org.');
    }

    /**
     * Get the date from the *info.txt file at github
     *
     * @return string
     */
    protected function fetchInfoTxtVersion()
    {
        $response = $this->github->read('contents/' . $this->infotxt);
        $infotxt = base64_decode($response['content']);
        $infotxt = DokuwikiClient::linesToHash(explode("\n", $infotxt));

        if (empty($infotxt['date'])) $this->cli->fatal('info.txt has no date field');
        return $infotxt['date'];
    }

    /**
     * Return the date of the last significant commit
     *
     * @return string
     */
    protected function fetchLastCommit()
    {
        $commits = $this->github->read('commits?per_page=100');
        foreach ($commits as $commit) {
            if (preg_match('/^Merge/i', $commit['commit']['message'])) continue; // skip merges;
            if ($commit['commit']['committer']['email'] == 'translate@dokuwiki.org') continue; //skip translations
            if (preg_match('/^Version upped/i', $commit['commit']['message'])) continue; // skip version ups;

            return substr($commit['commit']['author']['date'], 0, 10);
        }

        return date('Y-m-d');
    }

}
