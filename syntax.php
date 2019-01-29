<?php
/**
 * Gitlab Syntax Plugin: display Gitlab project
 *
 * @author Algorys
 */

if (!defined('DOKU_INC')) die();
require 'gitlab/gitlab.php';

class syntax_plugin_gitlabproject extends DokuWiki_Syntax_Plugin {
    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'normal';
    }

    function getAllowedTypes() {
        return array('container', 'baseonly', 'substition','protected','disabled','formatting','paragraphs');
    }

    public function getSort() {
        return 196;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<gitlab[^>]*/>', $mode, 'plugin_gitlabproject');
    }

   function getServerFromJson($server) {
        $json_file = file_get_contents(__DIR__.'/server.json');
        $json_data = json_decode($json_file, true);
        if(isset($json_data[$server])) {
            return $json_data[$server];
        } else {
            return null;
        }
    }
 
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch($state){
            case DOKU_LEXER_SPECIAL :
                // Init @data
                $data = array(
                    'state' => $state
                );

                // Match @server and @token
                preg_match("/server *= *(['\"])(.*?)\\1/", $match, $server);
                if (count($server) != 0) {
                    $server_data = $this->getServerFromJson($server[2]);
                    if (!is_null($server_data)) {
                        $data['server'] = $server_data['url'];
                        $data['token'] = $server_data['api_token'];
                    }
                }
                if (!isset($data['server'])) {
                    $data['server'] = $this->getConf('server.default');
                }
                if (!isset($data['token'])) {
                    $data['token'] = $this->getConf('token.default');
                }

                // Match @project
                preg_match("/project *= *(['\"])(.*?)\\1/", $match, $project);
                if (count($project) != 0) {
                    $data['project'] = $project[2];
		}

		preg_match("/commits *= *(['\"])(.*?)\\1/", $match, $commits);
                if (count($commits) != 0) {
                    $data['commits'] = $commits[2];
		} else {
		    $data['commits'] = 0;
		}

                return $data;
            case DOKU_LEXER_UNMATCHED :
                return array('state'=>$state, 'text'=>$match);
            default:
                return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
    }

    // Dokuwiki Renderer
    function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;
        if($data['error']) {
            $renderer->doc .= $data['text'];
            return true;
        }

        $renderer->info['cache'] = false;
        switch($data['state']) {
            case DOKU_LEXER_SPECIAL:
                $this->renderGitlab($renderer, $data);
                break;
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_EXIT:
            case DOKU_LEXER_UNMATCHED:
                $renderer->doc .= $renderer->_xmlEntities($data['text']);
                break;
        }
        return true;
    }

    function renderGitlab($renderer, $data) {
        // -------- Gitlab Data --------
        $gitlab = new DokuwikiGitlab($data);
        // Project
        $project = $gitlab->getProject();
        if(empty($project)) {
            $this->renderProjectError($renderer, $data);
            return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
        $namespace = $project['namespace']['name'];
        // Members
        $kind = $project['namespace']['kind'];
        $unwanted_members = $this->getConf('unwanted.users');
        $members = $gitlab->getProjectMembers($kind, $unwanted_members);
        if(array_key_exists("message", $members)) {
            $this->renderProjectError($renderer, $data);
            return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
	}
	// Image
	$img_url = DOKU_URL . 'lib/plugins/gitlabproject/images/gitlab.png';
	// Commits
        $last_commit = $gitlab->getLastCommit();
	$commit_date = new DateTime($last_commit['committed_date']);
	$user = $gitlab->getUser($last_commit['committer_email']);
	$last_events = $gitlab->getLastEvents($data['commits']);

        // -------- Renderer --------
	$renderer->doc .= '<div class="gitlab">'; // Main container
	// Project title
	$renderer->doc .= '<div><h4 id="title-'.$namespace.'-'.$project['name'].'">';
        $renderer->doc .= '<img src="'.$img_url.'" class="gitlab">';
        $renderer->doc .= '<a href="'.$project['web_url'].'" class="gitlab" target="_blank">'.$namespace.' <span class="separator">&gt;</span> '.$project['name'].'</a>';
	$renderer->doc .= '</h4></div>';
	$renderer->doc .= '<hr class="gitlab">';
	// Project actions
	$renderer->doc .= '<h4 id="actions-'.$namespace.'-'.$project['name'].'">'.$this->getLang('gitlab.repos').'</h4>';
	$renderer->doc .= '<div class="gitlab-actions">';
	$renderer->doc .= '<a href="'.$project['web_url'].'/tree/'.$project['default_branch'].'" class="gitlab-btn" target="_blank">'.$project['default_branch'].'</a>';
	$renderer->doc .= '<a href="'.$project['web_url'].'/commits/'.$project['default_branch'].'" class="gitlab-btn" target="_blank">'.$this->getLang('gitlab.history').'</a>';
	$renderer->doc .= '<a href="'.$project['web_url'].'/find_file/'.$project['default_branch'].'" class="gitlab-btn" target="_blank">'.$this->getLang('gitlab.findfile').'</a>';
	$renderer->doc .= '</div>';
	// Project members
	$renderer->doc .= '<div>';
        $renderer->doc .= '<h4 id="members-'.$namespace.'-'.$project['name'].'">'.$this->getLang('gitlab.members').'</h4>';
	$renderer->doc .= '<div class="members">';
        $total_members = count($members);
        $i = 0;
        foreach ($members as $key => $member) {
            $i++;
            $renderer->doc .= ' <a href="'.$member['web_url'].'" target="_blank">'.$member['username'].'</a> ';
            $renderer->doc .= '('.$gitlab->getRoleName($member['access_level']).')';
            if ($i != $total_members) $renderer->doc .= ',';
        }
        $renderer->doc .= '</div></div>';

	if($data['commits'] > 0) {
            $renderer->doc .= '<h4 id="events-'.$namespace.'-'.$project['name'].'">'.$this->getLang('gitlab.activity').'</h4>';
	    foreach($last_events as $key => $event) {
	        $short_sha = substr($event['push_data']['commit_to'], 0, 8);
		$datetime = new DateTime($event['created_at']);
		$event_date = $this->elapsed_time($datetime->getTimestamp());
	        $renderer->doc .= '<div class="row commit-detail">';
	        $renderer->doc .= '<div class="column col-left">';
                $renderer->doc .= '<img src="'.$event['author']['avatar_url'].'" class="avatar" />';
                $renderer->doc .= '</div>';
                $renderer->doc .= '<div class="column col-middle">';
                $renderer->doc .= '<b>'.$event['author']['username'].' '.$event['action_name'].' '.$event['push_data']['ref'].'</b><br>';
                $renderer->doc .= '<span><a href="'.$event['author']['web_url'].'" target="_blank">'.$event['author']['username'].'</a> committed '.$event_date.'</span>';
                $renderer->doc .= '</div>';
                $renderer->doc .= '<div class="column col-right"><b><a href="'.$project['web_url'].'/commit/'.$event['push_data']['commit_to'].'" target="_blank">'.$short_sha.'</a></b></div>';
                $renderer->doc .= '</div>';
	     }
	}

        $renderer->doc .= '</div>'; // End of main container
        $gitlab->closeClient();
    }

    function elapsed_time($timestamp, $precision = 2) {
        $time = time() - $timestamp;
        $a = array('decade' => 315576000, 'year' => 31557600, 'month' => 2629800, 'week' => 604800, 'day' => 86400, 'hour' => 3600, 'min' => 60, 'sec' => 1);
        $i = 0;
        foreach($a as $k => $v) {
            $$k = floor($time/$v);
            if ($$k) $i++;
            $time = $i >= $precision ? 0 : $time - $$k * $v;
            $s = $$k > 1 ? 's' : '';
            $$k = $$k ? $$k.' '.$k.$s.' ' : '';
            @$result .= $$k;
          }
        return $result ? $result.'ago' : '1 sec to go';
    }

    function renderProjectError($renderer, $data) {
        // Renderer
        $img_url = DOKU_URL . 'lib/plugins/gitlabproject/images/gitlab.png';

        $renderer->doc .= '<div class="gitlab">';
        $renderer->doc .= '<span><img src="'.$img_url.'" class="gitlab"></span>';
        $renderer->doc .= '<b class="gitlab">'.$this->getLang('gitlab.project').'</b><br>';
        $renderer->doc .= '<hr class="gitlab">';
        $renderer->doc .= '<p>'.$this->getLang('gitlab.error').'</p>';
        $renderer->doc .= '</div>';
    }
}
