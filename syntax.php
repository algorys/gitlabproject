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
        $date_time = $this->getDateTime($project['last_activity_at']);
        $namespace = $project['namespace']['name'];

        // Members
        $kind = $project['namespace']['kind'];
        $unwanted_members = $this->getConf('unwanted.users');
        $members = $gitlab->getProjectMembers($kind, $unwanted_members);
        
        $img_url = 'lib/plugins/gitlabproject/images/gitlab.png';

        // Renderer
        $renderer->doc .= '<div class="gitlab">';
        $renderer->doc .= '<span><img src="'.$img_url.'" class="gitlab"></span>';
        $renderer->doc .= '<b class="gitlab">'.$this->getLang('gitlab.project').'</b><br>';
        $renderer->doc .= '<hr class="gitlab">';
        $renderer->doc .= '<a href="'.$project_url.'" class="gitlab">'.$project_name.'</a>';
        $renderer->doc .= ' - <b>Namespace:</b> <a href="'.$data['server'].'/'.$namespace.'"> '.$namespace.'</a>';
        $renderer->doc .= '<p><b>'.$this->getLang('gitlab.activity').':</b> '.$date_time['date'].' - '.$date_time['time'].'</p>';
        $renderer->doc .= '<p><b>'.$this->getLang('gitlab.members').':</b>';
        $total_members = count($members);
        $i = 0;
        foreach ($members as $key => $member) {
            $i++;
            $renderer->doc .= ' <a href="'.$member['web_url'].'">'.$member['username'].'</a> ';
            $renderer->doc .= '('.$gitlab->getRoleName($member['access_level']).')';
            if ($i != $total_members) $renderer->doc .= ',';
        }
        $renderer->doc .= '</p>';
        $renderer->doc .= '</div>';

        $gitlab->closeClient();
    }

    function getDateTime($activity_time) {
        $date_exploded = explode('T', $activity_time);
        $time_exploded = explode('Z', $date_exploded[1]);

        return ['date' => $date_exploded[0], 'time' => substr($time_exploded[0], 0, -4)];
    }
}
