<?php
/**
 * API.AI 
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 14:01:43 [Jan 26, 2017])
 */
//
//
class apiai extends module
{
    /**
     * apiai
     *
     * Module class constructor
     *
     * @access private
     */
    function apiai()
    {
        $this->name            = "apiai";
        $this->title           = "API.AI";
        $this->module_category = "<#LANG_SECTION_OBJECTS#>";
        $this->api_endpoint    = "https://api.dialogflow.com/v1/";
        $this->api_version     = "20170712";
        $this->checkInstalled();
    }
    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }
    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }
    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE']      = $this->mode;
        $out['ACTION']    = $this->action;
        $out['TAB']       = $this->tab;
        $this->data       = $out;
        $p                = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result     = $p->result;
    }
    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();
        
        $out['API_KEY']                = $this->config['API_KEY'];
        $out['CONFIG_SPEAK_PRIORITY']  = $this->config['SPEAK_PRIORITY'];
        $out['CONFIG_SPEAK_UNKNOWN']   = $this->config['SPEAK_UNKNOWN'];
        $out['CONFIG_LANGUAGE']        = $this->config['LANGUAGE'];
        $out['CONFIG_SESSION_TIMEOUT'] = $this->config['SESSION_TIMEOUT'];
        
        
        if ($this->view_mode == 'update_settings') {
            global $api_key;
            $this->config['API_KEY'] = $api_key;
            global $speak_priority;
            $this->config['SPEAK_PRIORITY'] = (int) $speak_priority;
            global $speak_unknown;
            $this->config['SPEAK_UNKNOWN'] = (int) $speak_unknown;
            global $language;
            $this->config['LANGUAGE'] = $language;
            global $session_timeout;
            $this->config['SESSION_TIMEOUT'] = (int) $session_timeout;
            //  print_r($this->config);exit;
            $this->saveConfig();
            
            if ($this->config['API_KEY'] != '') {
                subscribeToEvent($this->name, 'COMMAND', '', 100);
            }
            
            $this->redirect("?");
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        
        if ($this->mode == 'test') {
            global $message;
            if ($message) {
                $out['MESSAGE']  = htmlspecialchars($message);
                $result          = $this->sendQry($message);
                $out['RESPONSE'] = json_encode($result);
                $this->processResponse($result);
            }
        }
        
        if ($this->data_source == 'apiai_actions' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_apiai_actions') {
                $this->search_apiai_actions($out);
            }
            if ($this->view_mode == 'edit_apiai_actions') {
                $this->edit_apiai_actions($out, $this->id);
            }
            if ($this->view_mode == 'delete_apiai_actions') {
                $this->delete_apiai_actions($this->id);
                $this->redirect("?");
            }
            
        }
    }
    
    function processSubscription($event, &$details)
    {
        $this->getConfig();
        if ($event == 'COMMAND' && $this->config['API_KEY'] != '' && $details['message'] != '') {
            $message = $details['message'];
            
            $t_contexts = array();
            $source     = $details['source'];
            if (preg_match('/^terminal(\\d+)$/', $source, $terminal_id)) {
                $terminal_id = $terminal_id[1];
                $rec         = SQLSelectOne("select * from terminals where ID = $terminal_id;");
                if ($terminal_id && $rec['ID']) {
                    $term_params = array(
                        'terminalid' => $rec['ID'],
                        'terminal' => $rec['NAME'],
                        'terminal.original' => $rec['TITLE']
                    );
                    $t_contexts  = array(
                        array(
                            'name' => 'known-terminal',
                            'parameters' => $term_params
                        ),
                        array(
                            'name' => 'terminal-' . $rec['NAME'],
                            'parameters' => $term_params
                        )
                    );
                } else {
                    $t_contexts = array(
                        'unknown-terminal'
                    );
                }
            }
            
            $u_contexts = array();
            $member_id  = (int) $details['member_id'];
            $rec        = SQLSelectOne("select * from users where ID = $member_id;");
            if ($member_id && $rec['ID']) {
                $user_params = array(
                    'userid' => $rec['ID'],
                    'user' => $rec['USERNAME'],
                    'user.original' => $rec['NAME']
                );
                $u_contexts  = array(
                    array(
                        'name' => 'known-user',
                        'parameters' => $user_params
                    ),
                    array(
                        'name' => 'user-' . $rec['USERNAME'],
                        'parameters' => $user_params
                    )
                );
            } else {
                $u_contexts = array(
                    'unknown-user'
                );
            }
            
            $data = $this->sendQry($message, $source, array_merge($t_contexts, $u_contexts));
            $res  = $this->processResponse($data);
            if ($res) {
                $details['BREAK'] = 1;
                $details['PROCESSED']++;
            }
        }
    }
    
    function getSession($source)
    {
        $this->getConfig();
        $sessions = $this->config['SESSIONS'];
        if (!$sessions)
            $sessions = array();
        
        $session_timeout = (int) $this->config['SESSION_TIMEOUT'];
        
        $session = $sessions[$source];
        if (!$session || (($session_timeout > 0) && ($session['time'] < time() - $session_timeout))) {
            $session = array(
                'id' => uniqid("$source.")
            );
        }
        
        $session['time']          = time();
        $sessions[$source]        = $session;
        $this->config['SESSIONS'] = $sessions;
        $this->saveConfig();
        
        return $session['id'];
    }
    
    function getSource($sessionId)
    {
        $this->getConfig();
        $sessions = $this->config['SESSIONS'];
        if (!$sessions)
            $sessions = array();
        
        $source = '';
        foreach ($sessions as $key => $value) {
            if ($value == $sessionId) {
                $source = $key;
                break;
            }
        }
        return $source;
    }
    
    function apiRequest($command)
    {
        $this->getConfig();
        
        $url          = $this->api_endpoint . $command;
        $access_token = $this->config['API_KEY'];
        $ch           = curl_init($url);
        $headers      = array(
            'Authorization: Bearer ' . $access_token,
            'Content-type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
        
    }

    function apiPostRequest($command, $data)
    {
        $this->getConfig();
        
        $url          = $this->api_endpoint . $command;
        $access_token = $this->config['API_KEY'];
        $ch           = curl_init($url);
        $headers      = array(
            'Authorization: Bearer ' . $access_token,
            'Content-type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    function apiDeleteRequest($command)
    {
        $this->getConfig();
        
        $url          = $this->api_endpoint . $command;
        $access_token = $this->config['API_KEY'];
        $ch           = curl_init($url);
        $headers      = array(
            'Authorization: Bearer ' . $access_token,
            'Content-type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
        
    }
    
    function sendQry($qry, $source = '', $contexts = array())
    {
        $this->getConfig();
        
        $data              = array();
        $data['query']     = $qry;
        $data['sessionId'] = $this->getSession($source);
        $data['lang']      = $this->config['LANGUAGE'];
        if (!$data['lang'])
            $data['lang'] = 'en';
        
        $data['contexts'] = array_merge($this->globalContexts(), $contexts);
        
        $result = $this->apiPostRequest('query?v=' . $this->api_version, $data);
        return $result;
    }
    
    function setContext($name, $source = '', $data = array())
    {
        $sid          = $this->getSession($source);
        $data['name'] = $name;
        $result       = $this->apiPostRequest("contexts?sessionId=$sid", array(
            $data
        ));
        return $result;
    }
    
    function deleteContext($name = '', $source = '')
    {
        $sid = $this->getSession($source);
        if ($name)
            $name = '/' . urlencode($name);
        $result = $this->apiDeleteRequest("contexts$name?sessionId=$sid");
        return $result;
    }
    
    function globalContexts()
    {
        $this->getConfig();
        $contexts = $this->config['GLOBAL_CONTEXTS'];
        if (!$contexts)
            $contexts = array();
        
        $cntx = array();
        foreach ($contexts as $key => $value) {
            $value['name'] = $key;
            $cntx[]        = $value;
        }
        
        return $cntx;
    }
    
    function setGlobalContext($name, $data = array())
    {
        $this->getConfig();
        $contexts = $this->config['GLOBAL_CONTEXTS'];
        if (!$contexts)
            $contexts = array();
        
        $contexts[$name]                 = $data;
        $this->config['GLOBAL_CONTEXTS'] = $contexts;
        $this->saveConfig();
    }
    
    function deleteGlobalContext($name)
    {
        $this->getConfig();
        $contexts = $this->config['GLOBAL_CONTEXTS'];
        if (!$contexts)
            return;
        
        unset($contexts[$name]);
        $this->config['GLOBAL_CONTEXTS'] = $contexts;
        $this->saveConfig();
    }
    
    function processResponse($data)
    {
        $this->getConfig();
        
        $source = $this->getSource($data['sessionId']);
        
        if ($data['result']['action']) {
            $action_name = $data['result']['action'];
            $this->runAction($action_name, $data);
        } else if ($data['result']['metadata']['intentName']) {
            $action_name = $data['result']['metadata']['intentName'];
            $this->runAction($action_name, $data);
        }
        
        $message = $data['result']['fulfillment']['speech'];
        if ($message != '') {
            if ($data['result']['action'] != 'input.unknown' || $this->config['SPEAK_UNKNOWN']) {
                $incomplete = $data['result']['actionIncomplete'];
                $majordroid = false;
                if ($incomplete) {
                    $rec        = SQLSelectOne("select MAJORDROID_API from terminals where NAME like '" . DBSafe($source) . "'");
                    $majordroid = $rec['MAJORDROID_API'] == 1;
                }
                
                if ($majordroid)
                    ask($message, $source);
                else if (!sayTo($message, $this->config['SPEAK_PRIORITY'], $source))
                    sayReply($message, $this->config['SPEAK_PRIORITY']);
            }
        }
        
        if ($data['result']['action'] != 'input.unknown' && $data['result']['action'] != '') {
            return 1;
        } else {
            return 0;
        }
        
    }
    
    function runAction($action_name, &$data)
    {
        $params = $data['result']['parameters'];
        $rec    = SQLSelectOne("SELECT * FROM apiai_actions WHERE TITLE LIKE '" . DBSafe($action_name) . "'");
        if (!$rec['ID']) {
            $rec          = array();
            $rec['TITLE'] = $action_name;
            $rec['CODE']  = "// \$action_name\n// \$data\n";
            foreach ($params as $k => $v) {
                $rec['CODE'] .= "// \$params['$k']";
                if ($v != '') {
                    if (is_array($v)) {
                        $s = implode("','", $v);
                        if (count($v) > 0) {
                            $s = "'$s'";
                        }
                        $s = "[$s]";
                    } else {
                        $s = "'$v'";
                    }
                    $rec['CODE'] .= " (ex " . str_replace("\n", ' ', $s) . ");";
                }
                $rec['CODE'] .= "\n";
            }
            $rec['ID'] = SQLInsert('apiai_actions', $rec);
        }
        $rec['LATEST_USAGE']  = date('Y-m-d H:i:s');
        $rec['LATEST_PARAMS'] = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $s = implode("','", $v);
                if (count($v) > 0) {
                    $s = "'$s'";
                }
                $s = "[$s]";
            } else {
                $s = "'$v'";
            }
            $rec['LATEST_PARAMS'] .= "$k = '$s'; ";
        }
        $rec['LATEST_PARAMS'] = trim($rec['LATEST_PARAMS']);
        SQLUpdate('apiai_actions', $rec);
        
        
        if ($rec['CODE'] != '') {
            try {
                $source  = $this->getSource($data['sessionId']);
                $code    = $rec['CODE'];
                $success = eval($code);
                if ($success === false) {
                    registerError('apiai', sprintf('Error in apiai "%s". Code: %s', $rec['TITLE'], $code));
                }

                return $success;
            }
            catch (Exception $e) {
                registerError('apiai', sprintf('Error in apiai "%s": ' . $e->getMessage(), $rec['TITLE']));
            }
        }
    }
    
    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }
    /**
     * apiai_actions search
     *
     * @access public
     */
    function search_apiai_actions(&$out)
    {
        require(DIR_MODULES . $this->name . '/apiai_actions_search.inc.php');
    }
    /**
     * apiai_actions edit/add
     *
     * @access public
     */
    function edit_apiai_actions(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/apiai_actions_edit.inc.php');
    }
    /**
     * apiai_actions delete record
     *
     * @access public
     */
    function delete_apiai_actions($id)
    {
        $rec = SQLSelectOne("SELECT * FROM apiai_actions WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM apiai_actions WHERE ID='" . $rec['ID'] . "'");
    }
    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        
        parent::install();
    }
    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS apiai_actions');
        parent::uninstall();
    }
    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall()
    {
        /*
        apiai_actions - 
        */
        $data = <<<EOD
 apiai_actions: ID int(10) unsigned NOT NULL auto_increment
 apiai_actions: TITLE varchar(100) NOT NULL DEFAULT ''
 apiai_actions: LATEST_PARAMS varchar(255) NOT NULL DEFAULT '' 
 apiai_actions: LATEST_USAGE datetime
 apiai_actions: CODE text
EOD;
        parent::dbInstall($data);
    }
    // --------------------------------------------------------------------
}
/*
 *
 * TW9kdWxlIGNyZWF0ZWQgSmFuIDI2LCAyMDE3IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
 *
 */
