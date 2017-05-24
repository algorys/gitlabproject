<?php
/*
 * Gitlab Access
 */

class DokuwikiGitlab {
    public $client;
    public $url_api;
    public $token;

    function __construct($url, $token) {
        $this->client = new DokuHTTPClient(); 
        $this->url_api = $url . '/api/v3/projects/';
        $this->token = $token;
        $test = new DokuHTTPClient();
    }

    function displayVar() {
        print_r($this->client);
        print_r($this->url_api);
        print_r($this->token);
    }

    function getProject($project_id) {
        $project_encoded = urlencode($project_id);
        $url_project = $this->url_api . $project_encoded . '/?private_token=' . $this->token;
        $project = json_decode($this->client->get($url_project, true));

        return $project;
    }
}
