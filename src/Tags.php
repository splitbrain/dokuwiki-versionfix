<?php

namespace splitbrain\DokuWikiVersionFix;


use splitbrain\phpcli\CLI;

class Tags
{

    protected $cli;
    protected $github;

    protected $infotxt;

    /**
     * VersionFixExtension constructor.
     *
     * @param CLI $cli
     * @param array $repoinfo
     * @param array $credentials
     */
    public function __construct($cli, $repoinfo, $credentials)
    {
        $this->infotxt = 'plugin.info.txt';
        if ($repoinfo['is_template']) $this->infotxt = 'template.info.txt';
        $this->cli = $cli;

        $this->github = new GithubClient($credentials['github_user'], $credentials['github_key']);
        $this->github->setRepo($repoinfo['github_user'], $repoinfo['github_repo']);

    }

    /**
     * Fix the missing tags for this extension
     */
    public function fixTags()
    {
        $tags = $this->fetchTags();
        $this->cli->debug('Found tags:');
        $this->cli->debug(print_r($tags, true));

        if (count($tags)) {
            $newest = reset($tags);
        } else {
            $newest = '';
        }

        $changes = $this->fetchInfoTxtChanges($newest);

        foreach ($changes as $version => $sha) {
            if (!isset($tags[$version])) {
                $this->setTag($sha, $version);
            }
        }
    }

    /**
     * Get the tags for this repo
     *
     * @return array tag => sha1
     */
    protected function fetchTags()
    {
        $tags = array();

        try {
            $response = $this->github->read('git/refs/tags?per_page=100');
        } catch (\RuntimeException $e) {
            if ($e->getCode() == 404) {
                return $tags;
            } else {
                throw $e;
            }
        }

        foreach ($response as $info) {
            if (!preg_match('/^refs\/tags\/(.+)$/', $info['ref'], $m)) continue;
            $tags[$m[1]] = $info['object']['sha'];
        }

        krsort($tags);
        return $tags;
    }

    /**
     * Get the commits that changed the *info.txt file
     *
     * @param string $newest the newest known tag sha
     * @return array date => sha1
     */
    protected function fetchInfoTxtChanges($newest)
    {
        $response = $this->github->read('commits?path=' . $this->infotxt . '&per_page=100');

        $versions = array();

        foreach ($response as $commit) {
            $sha = $commit['sha'];
            if ($sha == $newest) break;
            $this->cli->info("reading version info from commit $sha");

            try {
                $data = $this->github->read('contents/' . $this->infotxt . '?ref=' . $sha);
            } catch (\RuntimeException $e) {
                continue;
            }
            $data = base64_decode($data['content']);
            $data = DokuwikiClient::linesToHash(explode("\n", $data));
            $version = $data['date'];
            $versions[$version] = $sha;
        }

        ksort($versions);
        return $versions;
    }

    /**
     * Creates a version tag pointing to the commit identified by $sha
     *
     * @param string $sha
     * @param string $version
     */
    protected function setTag($sha, $version)
    {
        $data = array(
            'sha' => $sha,
            'ref' => 'refs/tags/' . $version
        );

        $this->cli->debug("Tagging $sha with $version");
        $this->github->write('git/refs', $data, 'POST');
        $this->cli->success("Tagged $sha with $version");
    }

}
