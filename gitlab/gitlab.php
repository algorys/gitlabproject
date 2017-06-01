<?php
/*
 * Gitlab Access
 * @author Algorys
 */

class DokuwikiGitlab {
    public $client;
    public $data;

    function __construct($dw_data) {
        $this->client = new DokuHTTPClient(); 
        $this->dw_data = $dw_data;
    }

    function getAPIUrl() {
        return $this->dw_data['server'] . '/api/v3/';
    }

    function getProject() {
        $url_request = $this->getAPIUrl().'projects/'.urlencode($this->dw_data['project']).'/?private_token='.$this->dw_data['token'];
        $project = json_decode($this->client->get($url_request), true);

        return $project;
    }

    function getProjectMembers($kind) {
        // Check if 'user' or 'group'
        $user_url_request = $url_request = $this->getAPIUrl().'projects/'.urlencode($this->dw_data['project']).'/members/?private_token='.$this->dw_data['token'];

        $namespace = explode('/', $this->dw_data['project'])[0];
        $group_url_request = $this->getAPIUrl().'groups/'.urlencode($namespace).'/members/?private_token='.$this->dw_data['token'];

        $user_members = json_decode($this->client->get($user_url_request), true);
        $group_members = json_decode($this->client->get($group_url_request), true);

        print_r($user_members);
        print_r($group_members);

        $members = array_merge($user_members, $group_members);

        return $members;
    }

    function getRoleName($role_nb) {
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
