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
class apiai extends module {
/**
* apiai
*
* Module class constructor
*
* @access private
*/
function apiai() {
  $this->name="apiai";
  $this->title="API.AI";
  $this->module_category="<#LANG_SECTION_OBJECTS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
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
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['API_URL']=$this->config['API_URL'];
 if (!$out['API_URL']) {
  $out['API_URL']='http://';
 }
 $out['API_KEY']=$this->config['API_KEY'];
 $out['CONFIG_SPEAK_PRIORITY']=$this->config['SPEAK_PRIORITY'];
 $out['CONFIG_SPEAK_UNKNOWN']=$this->config['SPEAK_UNKNOWN'];
 $out['CONFIG_LANGUAGE']=$this->config['LANGUAGE'];


 if ($this->view_mode=='update_settings') {
   global $api_key;
   $this->config['API_KEY']=$api_key;
   global $speak_priority;
   $this->config['SPEAK_PRIORITY']=(int)$speak_priority;
   global $speak_unknown;
   $this->config['SPEAK_UNKNOWN']=(int)$speak_unknown;
   global $language;
   $this->config['LANGUAGE']=$language;
   //  print_r($this->config);exit;
   $this->saveConfig();

   if ($this->config['API_KEY']!='') {
       subscribeToEvent($this->name, 'COMMAND','',100);
   }

   $this->redirect("?");
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }

    if ($this->mode=='test') {
        global $message;
        if ($message) {
            $out['MESSAGE']=htmlspecialchars($message);
            $result=$this->sendQry($message);
            $out['RESPONSE']=json_encode($result);
            $this->processResponse($result);
        }
    }

 if ($this->data_source=='apiai_actions' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_apiai_actions') {
   $this->search_apiai_actions($out);
  }
  if ($this->view_mode=='edit_apiai_actions') {
   $this->edit_apiai_actions($out, $this->id);
  }
  if ($this->view_mode=='delete_apiai_actions') {
   $this->delete_apiai_actions($this->id);
   $this->redirect("?");
  }

 }
}

    function processSubscription($event, &$details) {
        $this->getConfig();
        if ($event=='COMMAND' && $this->config['API_KEY']!='' && $details['message']!='') {
            $message=$details['message'];
            $data=$this->sendQry($message);
            $res=$this->processResponse($data);
            if ($res) {
              $details['BREAK']=1;
              $details['PROCESSED']++;
            }
        }
    }

function apiRequest($command, $data) {
    $url='https://api.api.ai/v1/'.$command.'?v=20170712';
    $this->getConfig();
    $access_token=$this->config['API_KEY'];
    $ch = curl_init($url);
    $headers = array('Authorization: Bearer ' . $access_token,
                     'Content-type: application/json');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result,true);

}

function sendQry($qry) {
    $this->getConfig();
    $data=array();
    $data['query']=$qry;
    if ($this->config['LATEST_SID']!='') {
        $data['sessionId']=$this->config['LATEST_SID'];
    } else {
        $data['sessionId']=session_id();
    }
    $data['lang']=$this->config['LANGUAGE'];
    if (!$data['lang']) $data['lang']='en';
    $data['resetContexts']=true;
    $result = $this->apiRequest('query',$data);
    return $result;
}

function processResponse($data) {
    $this->getConfig();
    if ($data['sessionId'] && $this->config['LATEST_SID']!=$data['sessionId']) {
        $this->config['LATEST_SID']=$data['sessionId'];
        $this->saveConfig();
    }
    //input.unknown
    if ($data['result']['speech']) {
        if ($data['result']['action']!='input.unknown' || $this->config['SPEAK_UNKNOWN']) {
            sayReply($data['result']['speech'],$this->config['SPEAK_PRIORITY']);
        }
    }
    if ($data['result']['action']) {
        $action_name=$data['result']['action'];
        $params=$data['result']['parameters'];
        $this->runAction($action_name,$params);
    }
  
   //Иначе если action не задан, то создать процедуру для кастомного интента
	else if ($data['result']['metadata']['intentName']) {
        $action_name=$data['result']['metadata']['intentName'];
        $params=$data['result']['parameters'];
        $this->runAction($action_name,$params);
    }

    if ($data['result']['action']!='input.unknown' && $data['result']['action']!='') {
        return 1;
    } else {
        return 0;
    }

}

function runAction($action_name,$params) {
    $rec=SQLSelectOne("SELECT * FROM apiai_actions WHERE TITLE LIKE '".DBSafe($action_name)."'");
    if (!$rec['ID']) {
        $rec=array();
        $rec['TITLE']=$action_name;
        $rec['CODE']='';
        foreach($params as $k=>$v) {
            $rec['CODE'].="// \$params['$k']";
            if ($v!='') {
                $rec['CODE'].=" (ex '".str_replace("\n",' ',$v)."');";
            }
            $rec['CODE'].="\n";
        }
        $rec['ID']=SQLInsert('apiai_actions',$rec);
    }
    $rec['LATEST_USAGE']=date('Y-m-d H:i:s');
    $rec['LATEST_PARAMS']='';
    foreach($params as $k=>$v) {
       $rec['LATEST_PARAMS'].="$k = '$v'; ";
    }
    $rec['LATEST_PARAMS']=trim($rec['LATEST_PARAMS']);
    SQLUpdate('apiai_actions',$rec);
    if ($rec['CODE']!='') {
        try {
            $code = $rec['CODE'];
            $success = eval($code);
            if ($success === false) {
                registerError('apiai', sprintf('Error in apiai "%s". Code: %s', $rec['TITLE'], $code));
            }
            return $success;
        } catch (Exception $e) {
            registerError('apiai', sprintf('Error in apiai "%s": '.$e->getMessage(), $rec['TITLE']));
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
function usual(&$out) {
 $this->admin($out);
}
/**
* apiai_actions search
*
* @access public
*/
 function search_apiai_actions(&$out) {
  require(DIR_MODULES.$this->name.'/apiai_actions_search.inc.php');
 }
/**
* apiai_actions edit/add
*
* @access public
*/
 function edit_apiai_actions(&$out, $id) {
  require(DIR_MODULES.$this->name.'/apiai_actions_edit.inc.php');
 }
/**
* apiai_actions delete record
*
* @access public
*/
 function delete_apiai_actions($id) {
  $rec=SQLSelectOne("SELECT * FROM apiai_actions WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM apiai_actions WHERE ID='".$rec['ID']."'");
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {

  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
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
 function dbInstall() {
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
