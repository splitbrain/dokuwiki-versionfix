<?php

namespace splitbrain\DokuWikiVersionFix;


use GuzzleHttp\Client;

class DokuwikiClient
{

    protected $user;
    protected $pass;
    protected $guzzle;

    /**
     * DokuwikiClient constructor.
     *
     * @param string $user DokuWiki user name
     * @param string $pass DokuWiki password
     */
    public function __construct($user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->guzzle = new Client([
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'DokuWikiVersionFix/1.0',
            ]
        ]);
    }

    /**
     * Get matching extensions from the DokuWiki repository
     *
     * @param string $identifier either an extension name or an email
     * @return array
     */
    public static function getRepoData($identifier)
    {
        // anonymous request to the API
        $guzzle = new Client();

        if (strpos($identifier, '@') !== false) {
            $url = 'https://www.dokuwiki.org/lib/plugins/pluginrepo/api.php?mail[]=' . md5(strtolower($identifier));
        } else {
            $url = 'https://www.dokuwiki.org/lib/plugins/pluginrepo/api.php?ext[]=' . $identifier;
        }


        $response = $guzzle->get($url);
        $data = json_decode($response->getBody()->getContents(), true);

        $extensions = array();
        foreach ($data as $result) {
            if ($result['bundled']) continue;
            if (!$result['lastupdate']) continue;
            if (!$result['sourcerepo']) continue;

            // match github repo
            if (!preg_match('/github\.com\/([^\/]+)\/([^\/]+)/i', $result['sourcerepo'], $m)) {
                continue;
            }

            // template detection
            $page = $result['plugin'];
            $istemplate = true;
            if (!preg_match('/^template:/', $page)) {
                $page = 'plugin:' . $page;
                $istemplate = false;
            }

            $extensions[] = array(
                'page' => $page,
                'repoversion' => $result['lastupdate'],
                'is_template' => $istemplate,
                'github_user' => $m[1],
                'github_repo' => $m[2]
            );
        }

        return $extensions;
    }


    /**
     * Read the raw contents of the given page
     *
     * @param string $page
     * @return string
     */
    public function read($page)
    {
        $response = $this->guzzle->get('https://www.dokuwiki.org/' . $page . '?do=export_raw');
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() > 299) {
            throw new \RuntimeException(
                'Status ' . $response->getStatusCode() . " GET\n" .
                'https://www.dokuwiki.org/' . $page . '?do=export_raw' . "\n" .
                $body,
                $response->getStatusCode()
            );
        }

        return $body;
    }

    /**
     * Write the page back to DokuWiki
     *
     * @param string $page
     * @param string $content
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function write($page, $content)
    {
        $response = $this->guzzle->post('https://www.dokuwiki.org/doku.php', [
            'form_params' => [
                'id' => $page,
                'do' => 'edit',
                'u' => $this->user,
                'p' => $this->pass
            ]
        ]);
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() > 299) {
            throw new \RuntimeException(
                'Status ' . $response->getStatusCode() . " POST\n" .
                "https://www.dokuwiki.org/doku.php\n" .
                $body,
                $response->getStatusCode()
            );
        }

        if (!preg_match('/<input type="hidden" name="sectok" value="([0-9a-f]{32})" \/>/', $body, $m)) {
            throw new \RuntimeException('Failed to open extension page for editing. Might be locked or your credentials are wrong.');
        }
        $sectoken = $m[1];

        $response = $this->guzzle->post('https://www.dokuwiki.org/doku.php', [
            'form_params' => [
                'id' => $page,
                'prefix' => '',
                'suffix' => '',
                'wikitext' => $content,
                'summary' => 'version upped',
                'sectok' => $sectoken,
                'do' => 'save',
            ]
        ]);
        $body = $response->getBody()->getContents();

        if ($response->getStatusCode() > 299) {
            throw new \RuntimeException(
                'Status ' . $response->getStatusCode() . " POST\n" .
                "https://www.dokuwiki.org/doku.php\n" .
                $body,
                $response->getStatusCode()
            );
        }

        if (preg_match('/<div class="error">(.*?)(<\/div>)/', $body, $m)) {
            throw new \RuntimeException('Seems like something went wrong on editing the page ' . $page . '. Error was: ' . $m[1]);
        }
    }

    /**
     * Builds a hash from an array of lines
     *
     * If $lower is set to true all hash keys are converted to
     * lower case.
     *
     * @param string[] $lines
     * @param bool $lower
     * @return array
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Gina Haeussge <gina@foosel.net>
     */
    public static function linesToHash($lines, $lower = false)
    {
        $conf = array();
        // remove BOM
        if (isset($lines[0]) && substr($lines[0], 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf))
            $lines[0] = substr($lines[0], 3);
        foreach ($lines as $line) {
            //ignore comments (except escaped ones)
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) continue;
            $line = array_pad(preg_split('/\s+/', $line, 2), 2, '');
            // Build the associative array
            if ($lower) {
                $conf[strtolower($line[0])] = $line[1];
            } else {
                $conf[$line[0]] = $line[1];
            }
        }
        return $conf;
    }
}
