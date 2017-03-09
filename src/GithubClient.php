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
        if ($response->getStatusCode() > 299) {
            throw new \RuntimeException(
                'Status ' . $response->getStatusCode() . " GET\n" .
                $this->apibase . $endpoint . "\n" .
                $response->getBody()->getContents(),
                $response->getStatusCode()
            );
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Do a GitHub API PUT query at the given endpoint
     *
     * @param string $endpoint
     * @param mixed $data
     * @param string $method defaults to PUT but some endpoints need POST
     * @return mixed
     */
    public function write($endpoint, $data, $method = 'PUT')
    {

        $response = Client::request($this->apibase . $endpoint, $method, $this->options)
            ->withJson($data)
            ->send();

        if ($response->getStatusCode() > 299) {
            throw new \RuntimeException(
                'Status ' . $response->getStatusCode() . " $method\n" .
                $this->apibase . $endpoint . "\n" .
                $response->getBody()->getContents(),
                $response->getStatusCode()
            );
        }

        return json_decode($response->getBody()->getContents(), true);
    }


}