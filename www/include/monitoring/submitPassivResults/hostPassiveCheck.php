<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 * 
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0  
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * For more information : contact@centreon.com
 * 
 */

	$o = "hd";

	if (!isset ($oreon))
		exit ();

	require_once $centreon_path . "www/class/centreonHost.class.php";
	require_once $centreon_path . "www/class/centreonDB.class.php";
	require_once $centreon_path . "www/class/centreonACL.class.php";

	isset($_GET["host_name"]) ? $host_name = $_GET["host_name"] : $host_name = NULL;
	isset($_GET["cmd"]) ? $cmd = $_GET["cmd"] : $cmd = NULL;

	$hObj = new CentreonHost($pearDB);
	$path = "./include/monitoring/submitPassivResults/";
	$broker = $oreon->broker->getBroker();
	if ($broker == "ndo") {
	    $pearDBndo = new CentreonDB("ndo");
	} elseif ($broker == "broker") {
        $pearDBndo = new CentreonDB("centstorage");
	}
	$aclObj = new CentreonACL($oreon->user->get_id());

	# HOST LCA
	if (!$is_admin){
		$hostTab = explode(',', $oreon->user->access->getHostsString('NAME', $pearDBndo));
		foreach ($hostTab as $value) {
			if ($value == "'".$host_name."'")
				$flag_acl = 1;
		}
	}

	//$res = $pearDBndo->query($query);
	$hostTab = array();

	if ($is_admin || ($flag_acl && !$is_admin)){

		#Pear library
		require_once "HTML/QuickForm.php";
		require_once 'HTML/QuickForm/advmultiselect.php';
		require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

		$form = new HTML_QuickForm('select_form', 'GET', "?p=".$p);
		$form->addElement('header', 'title', _("Command Options"));

		$hosts = array($host_name=>$host_name);

		$form->addElement('select', 'host_name', _("Host Name"), $hosts, array("onChange" =>"this.form.submit();"));

		$form->addRule('host_name', _("Required Field"), 'required');

		$return_code = array("0" => "UP", "1" => "DOWN", "2" => "UNREACHABLE");

		$form->addElement('select', 'return_code', _("Check result"),$return_code);
		$form->addElement('text', 'output', _("Check output"), array("size"=>"100"));
		$form->addElement('text', 'dataPerform', _("Performance data"), array("size"=>"100"));

		$form->addElement('hidden', 'author', $oreon->user->get_alias());
		$form->addElement('hidden', 'cmd', $cmd);
		$form->addElement('hidden', 'p', $p);

		$form->addElement('submit', 'submit', _("Save"));
		$form->addElement('reset', 'reset', _("Reset"));

		# Smarty template Init
		$tpl = new Smarty();
		$tpl = initSmartyTpl($path, $tpl);

		#Apply a template definition
		$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
		$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
		$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
		$form->accept($renderer);

		$tpl->assign('form', $renderer->toArray());
		$tpl->display("hostPassiveCheck.ihtml");
	}
?>