<?php
/*
* @version 0.1 (wizard)
*/
 global $session;
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $qry="1";
  // search filters
  // QUERY READY
  global $save_qry;
  if ($save_qry) {
   $qry=$session->data['apiai_entities_qry'];
  } else {
   $session->data['apiai_entities_qry']=$qry;
  }
  if (!$qry) $qry="1";
  $sortby_apiai_entities="ID";
  $out['SORTBY']=$sortby_apiai_entities;
  // SEARCH RESULTS
  $res=SQLSelect("SELECT * FROM apiai_entities WHERE $qry ORDER BY ".$sortby_apiai_entities);
  if ($res[0]['ID']) {
   //paging($res, 100, $out); // search result paging
   $total=count($res);
   for($i=0;$i<$total;$i++) {
    // some action for every record if required
   }
   $out['RESULT']=$res;
  }
