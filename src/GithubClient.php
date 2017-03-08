<?php

namespace splitbrain\DokuWikiVersionFix;

use EasyRequest\Client;

class GithubClient
{

    protected $options = array();
    protected $apibase;

    /**
     * Github constructor.
     *
     * @param string $user API user
     * @param string $key API key
     */
    public function __construct($user, $key)
    {
        $this->options['auth'] = "$user:$key";
        $this->options['header'] = array(
            'Accept' => 'application/vnd.github.v3+json'
        );
    }

    /**
     * All following requests will work on this repository
     *
     * @param string $user
     * @param string $repo
     */
    public function setRepo($user, $repo)
    {
        $this->apibase = "https://api.github.com/repos/$user/$repo/";
    }


    /**
     * Do a GitHub API GET query at the given endpoint
     *
     * @param string $endpoint
     * @return mixed
     */
    public function read($endpoint)
    {
        $response = Client::request($this->apibase . $endpoint, 'GET', $this->options)
            ->send();
        return json_decode($response->getBody(), true);
    }

    /**
     * Do a GitHub API PUT query at the given endpoint
     *
     * @param string $endpoint
     * @param mixed $data
     * @return mixed
     */
    public function write($endpoint, $data)
    {
        die('disabled github');
        $response = Client::request($this->apibase . $endpoint, 'POST', $this->options)
            ->withJson($data)
            ->send();
        return json_decode($response->getBody(), true);
    }


}