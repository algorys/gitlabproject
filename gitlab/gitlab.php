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

    function getProjectMembers($kind, $unwanted_members) {
        // Define url requests for 'user' and 'group'
        $user_url_request = $url_request = $this->getAPIUrl().'projects/'.urlencode($this->dw_data['project']).'/members/?private_token='.$this->dw_data['token'];

        $namespace = explode('/', $this->dw_data['project'])[0];
        $group_url_request = $this->getAPIUrl().'groups/'.urlencode($namespace).'/members/?private_token='.$this->dw_data['token'];

        // Get members and merge them
        $user_members = json_decode($this->client->get($user_url_request), true);
        $group_members = json_decode($this->client->get($group_url_request), true);

        $members = array_merge($user_members, $group_members);

        // Remove unwanted members
        $unwanted_members = explode(',', $unwanted_members);
        foreach ($unwanted_members as $unwanted_key => $unwanted_member) {
            foreach ($members as $key => $member) {
                if($member['username'] == trim($unwanted_member)) {
                    unset($members[$key]);
                }
            }
        }

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
