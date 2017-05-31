<?php
/*
 * Gitlab Access
 * @author Algorys
 */

class DokuwikiGitlab {
    public $client;
    public $url_api;
    public $token;

    function __construct($url, $token) {
        $this->client = new DokuHTTPClient(); 
        $this->url_api = $url . '/api/v3/';
        $this->token = $token;
    }

    function getProject($project) {
        $url_request = $this->url_api . 'projects/' . urlencode($project) . '/?private_token=' . $this->token;
        $project = json_decode($this->client->get($url_request), true);

        return $project;
    }

    function getProjectMembers($project, $kind) {
        if (strcmp($kind, 'user') == 0) {
            $url_request = $this->url_api . 'projects/' . urlencode($project) . '/members/?private_token=' . $this->token;
        } else {
            $namespace = explode('/', $project)[0];
            $url_request = $this->url_api . 'groups/' . urlencode($namespace) . '/members/?private_token=' . $this->token;
        }
        $members = json_decode($this->client->get($url_request), true);

        return $members;
    }

    function getRole($role_nb) {
        $roles = array(
            10 => Guest,
            20 => Reporter,
            30 => Developer,
            40 => Master,
            50 => Owner
        );
        return $roles[$role_nb];
    }

}
