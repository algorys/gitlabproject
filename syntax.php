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
        // Gitlab object
        $gitlab = new DokuwikiGitlab($data);

        // Project
        $project = $gitlab->getProject();
        $project_url = $project['web_url'];
        $project_name = $project['name'];
        if(empty($project)) {
            $this->renderProjectError($renderer, $data);
            return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
        $date_time = $this->getDateTime($project['last_activity_at']);
        $namespace = $project['namespace']['name'];

        // Members
        $kind = $project['namespace']['kind'];
        $unwanted_members = $this->getConf('unwanted.users');
        $members = $gitlab->getProjectMembers($kind, $unwanted_members);
        if(array_key_exists("message", $members)) {
            $this->renderProjectError($renderer, $data);
            return array('state'=>$state, 'bytepos_end' => $pos + strlen($match));
        }
        
	// Files
	$files = $gitlab->getRepoTree('');
		
        $img_url = DOKU_URL . 'lib/plugins/gitlabproject/images/gitlab.png';

        // Renderer
        $renderer->doc .= '<div class="gitlab">';
        $renderer->doc .= '<span><img src="'.$img_url.'" class="gitlab"></span>';
	$renderer->doc .= '<b class="gitlab"><a href="'.$project_url.'" class="gitlab">'.$project_name.'</a></b></br>';
	$renderer->doc .= '<hr class="gitlab">';
        $renderer->doc .= '<h3>Namespace:</h3> <a href="'.$data['server'].'/'.$namespace.'"> '.$namespace.'</a>';
        $renderer->doc .= '<p><h3>'.$this->getLang('gitlab.activity').':</h3> '.$date_time['date'].' - '.$date_time['time'].'</p>';
        $renderer->doc .= '<p><h3>'.$this->getLang('gitlab.members').':</h3>';
        $total_members = count($members);
	$total_files = count($files);
	$i = 0;
	$i2 = 0;
        foreach ($members as $key => $member) {
            $i++;
            $renderer->doc .= ' <a href="'.$member['web_url'].'">'.$member['username'].'</a> ';
            $renderer->doc .= '('.$gitlab->getRoleName($member['access_level']).')';
            if ($i != $total_members) $renderer->doc .= ',';
        }
	$renderer->doc .= '</p>';
	$renderer->doc .= '<p><h3>Files:</h3>';
	foreach ($files as $key => $file) {
            $i2++;
	    if ($file['type'] != 'tree') {
		$renderer->doc .= '<details>';
		$renderer->doc .= '<summary>'.$file['name'].'</summary>';
		$renderer->doc .='<pre>';
	    	$renderer->doc .=$gitlab->getRawFile($file['path']);
	    	$renderer->doc .='</pre></details>';
	}
	    else {
		$subfiles = $gitlab->getRepoTree($file['path']);
		$renderer->doc .= '<details>';
		$renderer->doc .= '<summary><b>'.$file['name'].'/</b></summary>';
		foreach ($subfiles as $key => $subfile) {
			$renderer->doc .= '<div style="margin-left: 25px; margin-top: 10px"><details>';
			$renderer->doc .= '<summary>'.$subfile['name'].'</summary>';
			if (substr($subfile['name'],-3,3) != 'exe') {
				$renderer->doc .='<pre>'.$gitlab->getRawFile($subfile['path']).'</pre>';
			}
			else {
				$renderer->doc .='<pre>This is a binary file</pre>';
			}
			$renderer->doc .= '</details></div>';
		}
		$renderer->doc .= '</details>';
	}
        }
        $renderer->doc .= '</p>';
        $renderer->doc .= '</div>';

        $gitlab->closeClient();
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

    function getDateTime($activity_time) {
        $date_exploded = explode('T', $activity_time);
        $time_exploded = explode('Z', $date_exploded[1]);

        return array('date' => $date_exploded[0], 'time' => substr($time_exploded[0], 0, -4));
    }
}
