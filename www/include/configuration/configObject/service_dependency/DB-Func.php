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

	if (!isset ($oreon))
		exit ();

	function testServiceDependencyExistence ($name = null)
	{
		global $pearDB;
		global $form;

		CentreonDependency::purgeObsoleteDependencies($pearDB);

		$id = null;
		if (isset($form))
			$id = $form->getSubmitValue('dep_id');
		$DBRESULT = $pearDB->query("SELECT dep_name, dep_id FROM dependency WHERE dep_name = '".htmlentities($name, ENT_QUOTES, "UTF-8")."'");
		$dep = $DBRESULT->fetchRow();
		#Modif case
		if ($DBRESULT->numRows() >= 1 && $dep["dep_id"] == $id)
			return true;
		#Duplicate entry
		else if ($DBRESULT->numRows() >= 1 && $dep["dep_id"] != $id)
			return false;
		else
			return true;
	}

	function testCycleH ($childs = null)
	{
		global $pearDB;
		global $form;
		$parents = array();
		$childs = array();
		if (isset($form))	{
			$parents = $form->getSubmitValue('dep_hSvPar');
			$childs = $form->getSubmitValue('dep_hSvChi');
			$childs = array_flip($childs);
		}
		foreach ($parents as $parent)
			if (array_key_exists($parent, $childs))
				return false;
		return true;
	}

	function deleteServiceDependencyInDB ($dependencies = array())
	{
		global $pearDB, $oreon;
		foreach($dependencies as $key=>$value)	{
			$DBRESULT2 = $pearDB->query("SELECT dep_name FROM `dependency` WHERE `dep_id` = '".$key."' LIMIT 1");
			$row = $DBRESULT2->fetchRow();

			$DBRESULT = $pearDB->query("DELETE FROM dependency WHERE dep_id = '".$key."'");
			$oreon->CentreonLogAction->insertLog("service dependency", $key, $row['dep_name'], "d");
		}
	}

	function multipleServiceDependencyInDB ($dependencies = array(), $nbrDup = array())
	{
		foreach($dependencies as $key=>$value)	{
			global $pearDB, $oreon;
			$DBRESULT = $pearDB->query("SELECT * FROM dependency WHERE dep_id = '".$key."' LIMIT 1");
			$row = $DBRESULT->fetchRow();
			$row["dep_id"] = '';
			for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
				$val = null;
				foreach ($row as $key2=>$value2)	{
					$key2 == "dep_name" ? ($dep_name = $value2 = $value2."_".$i) : null;
					$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=null?("'".$value2."'"):"NULL");
					if ($key2 != "dep_id")
						$fields[$key2] = $value2;
					if (isset($dep_name)) {
                        $fields["dep_name"] = $dep_name;
					}
				}
				if (isset($dep_name) && testServiceDependencyExistence($dep_name))	{
					$val ? $rq = "INSERT INTO dependency VALUES (".$val.")" : $rq = null;
					$pearDB->query($rq);
					$DBRESULT = $pearDB->query("SELECT MAX(dep_id) FROM dependency");
					$maxId = $DBRESULT->fetchRow();
					if (isset($maxId["MAX(dep_id)"]))	{
						$DBRESULT = $pearDB->query("SELECT * FROM dependency_serviceParent_relation WHERE dependency_dep_id = '".$key."'");
						$fields["dep_hSvPar"] = "";
						while($service = $DBRESULT->fetchRow())	{
							$DBRESULT2 = $pearDB->query("INSERT INTO dependency_serviceParent_relation VALUES ('', '".$maxId["MAX(dep_id)"]."', '".$service["service_service_id"]."', '".$service["host_host_id"]."')");
							$fields["dep_hSvPar"] .= $service["service_service_id"] . ",";
						}
						$fields["dep_hSvPar"] = trim($fields["dep_hSvPar"], ",");
						$DBRESULT = $pearDB->query("SELECT * FROM dependency_serviceChild_relation WHERE dependency_dep_id = '".$key."'");
						$fields["dep_hSvChi"] = "";
						while($service = $DBRESULT->fetchRow())	{
							$DBRESULT2 = $pearDB->query("INSERT INTO dependency_serviceChild_relation VALUES ('', '".$maxId["MAX(dep_id)"]."', '".$service["service_service_id"]."', '".$service["host_host_id"]."')");
							$fields["dep_hSvChi"] .= $service["service_service_id"] . ",";
						}
						$fields["dep_hSvChi"] = trim($fields["dep_hSvChi"], ",");
						$oreon->CentreonLogAction->insertLog("service dependency", $maxId["MAX(dep_id)"], $dep_name, "a", $fields);
					}
				}
			}
		}
	}

	function updateServiceDependencyInDB ($dep_id = null)
	{
		if (!$dep_id) {
		    exit();
		}
		updateServiceDependency($dep_id);
		updateServiceDependencyServiceParents($dep_id);
		updateServiceDependencyServiceChilds($dep_id);
		updateServiceDependencyHostChildren($dep_id);
	}

	function insertServiceDependencyInDB ($ret = array())
	{
		$dep_id = insertServiceDependency($ret);
		updateServiceDependencyServiceParents($dep_id, $ret);
		updateServiceDependencyServiceChilds($dep_id, $ret);
		updateServiceDependencyHostChildren($dep_id, $ret);
		return ($dep_id);
	}

	function insertServiceDependency($ret = array())
	{
		global $form;
		global $pearDB, $oreon;
		if (!count($ret)) {
			$ret = $form->getSubmitValues();
		}
		$rq = "INSERT INTO dependency ";
		$rq .= "(dep_name, dep_description, inherits_parent, execution_failure_criteria, notification_failure_criteria, dep_comment) ";
		$rq .= "VALUES (";
		isset($ret["dep_name"]) && $ret["dep_name"] != NULL ? $rq .= "'".htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8")."', " : $rq .= "NULL, ";
		isset($ret["dep_description"]) && $ret["dep_description"] != NULL ? $rq .= "'".htmlentities($ret["dep_description"], ENT_QUOTES, "UTF-8")."', " : $rq .= "NULL, ";
		isset($ret["inherits_parent"]["inherits_parent"]) && $ret["inherits_parent"]["inherits_parent"] != NULL ? $rq .= "'".$ret["inherits_parent"]["inherits_parent"]."', " : $rq .= "NULL, ";
		isset($ret["execution_failure_criteria"]) && $ret["execution_failure_criteria"] != NULL ? $rq .= "'".implode(",", array_keys($ret["execution_failure_criteria"]))."', " : $rq .= "NULL, ";
		isset($ret["notification_failure_criteria"]) && $ret["notification_failure_criteria"] != NULL ? $rq .= "'".implode(",", array_keys($ret["notification_failure_criteria"]))."', " : $rq .= "NULL, ";
		isset($ret["dep_comment"]) && $ret["dep_comment"] != NULL ? $rq .= "'".htmlentities($ret["dep_comment"], ENT_QUOTES, "UTF-8")."' " : $rq .= "NULL ";
		$rq .= ")";
		$DBRESULT = $pearDB->query($rq);
		$DBRESULT = $pearDB->query("SELECT MAX(dep_id) FROM dependency");
		$dep_id = $DBRESULT->fetchRow();

		$fields["dep_name"] = htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8");
		$fields["dep_description"] = htmlentities($ret["dep_description"], ENT_QUOTES, "UTF-8");
		if (isset($ret["inherits_parent"]["inherits_parent"])) {
		    $fields["inherits_parent"] = $ret["inherits_parent"]["inherits_parent"];
		}
		if (isset($ret["execution_failure_criteria"])) {
            $fields["execution_failure_criteria"] = implode(",", array_keys($ret["execution_failure_criteria"]));
		}
		if (isset($ret["notification_failure_criteria"])) {
		    $fields["notification_failure_criteria"] = implode(",", array_keys($ret["notification_failure_criteria"]));
		}
		$fields["dep_comment"] = htmlentities($ret["dep_comment"], ENT_QUOTES, "UTF-8");
		$fields["dep_hSvPar"] = "";
		if (isset($ret["dep_hSvPar"])) {
			$fields["dep_hSvPar"] = implode(",", $ret["dep_hSvPar"]);
		}
		$fields["dep_hSvChi"] = "";
		if (isset($ret["dep_hSvChi"])) {
			$fields["dep_hSvChi"] = implode(",", $ret["dep_hSvChi"]);
		}
		$oreon->CentreonLogAction->insertLog("service dependency", $dep_id["MAX(dep_id)"], htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8"), "a", $fields);
		return ($dep_id["MAX(dep_id)"]);
	}

	function updateServiceDependency($dep_id = null)
	{
		if (!$dep_id) {
		    exit();
		}
		global $form;
		global $pearDB, $oreon;
		$ret = array();
		$ret = $form->getSubmitValues();
		$rq = "UPDATE dependency SET ";
		$rq .= "dep_name = ";
		isset($ret["dep_name"]) && $ret["dep_name"] != NULL ? $rq .= "'".htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8")."', " : $rq .= "NULL, ";
		$rq .= "dep_description = ";
		isset($ret["dep_description"]) && $ret["dep_description"] != NULL ? $rq .= "'".htmlentities($ret["dep_description"], ENT_QUOTES, "UTF-8")."', " : $rq .= "NULL, ";
		$rq .= "inherits_parent = ";
		isset($ret["inherits_parent"]["inherits_parent"]) && $ret["inherits_parent"]["inherits_parent"] != NULL ? $rq .= "'".$ret["inherits_parent"]["inherits_parent"]."', " : $rq .= "NULL, ";
		$rq .= "execution_failure_criteria = ";
		isset($ret["execution_failure_criteria"]) && $ret["execution_failure_criteria"] != NULL ? $rq .= "'".implode(",", array_keys($ret["execution_failure_criteria"]))."', " : $rq .= "NULL, ";
		$rq .= "notification_failure_criteria = ";
		isset($ret["notification_failure_criteria"]) && $ret["notification_failure_criteria"] != NULL ? $rq .= "'".implode(",", array_keys($ret["notification_failure_criteria"]))."', " : $rq .= "NULL, ";
		$rq .= "dep_comment = ";
		isset($ret["dep_comment"]) && $ret["dep_comment"] != NULL ? $rq .= "'".htmlentities($ret["dep_comment"], ENT_QUOTES, "UTF-8")."' " : $rq .= "NULL ";
		$rq .= "WHERE dep_id = '".$dep_id."'";
		$DBRESULT = $pearDB->query($rq);

		$fields["dep_name"] = htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8");
		$fields["dep_description"] = htmlentities($ret["dep_description"], ENT_QUOTES, "UTF-8");
		$fields["inherits_parent"] = $ret["inherits_parent"]["inherits_parent"];
		if (isset($ret["execution_failure_criteria"])) {
			$fields["execution_failure_criteria"] = implode(",", array_keys($ret["execution_failure_criteria"]));
		}
		if (isset($ret["notification_failure_criteria"])) {
			$fields["notification_failure_criteria"] = implode(",", array_keys($ret["notification_failure_criteria"]));
		}
		$fields["dep_comment"] = htmlentities($ret["dep_comment"], ENT_QUOTES, "UTF-8");
		$fields["dep_hSvPar"] = "";
		if (isset($ret["dep_hSvPar"])) {
			$fields["dep_hSvPar"] = implode(",", $ret["dep_hSvPar"]);
		}
		$fields["dep_hSvChi"] = "";
		if (isset($ret["dep_hSvChi"])) {
			$fields["dep_hSvChi"] = implode(",", $ret["dep_hSvChi"]);
		}
		$oreon->CentreonLogAction->insertLog("service dependency", $dep_id, htmlentities($ret["dep_name"], ENT_QUOTES, "UTF-8"), "c", $fields);
	}

	function updateServiceDependencyServiceParents($dep_id = null, $ret = array())
	{
		if (!$dep_id) {
		    exit();
		}
		global $form;
		global $pearDB;
		$rq = "DELETE FROM dependency_serviceParent_relation ";
		$rq .= "WHERE dependency_dep_id = '".$dep_id."'";
		$DBRESULT = $pearDB->query($rq);
		if (isset($ret["dep_hSvPar"])) {
			$ret1 = $ret["dep_hSvPar"];
		} else {
			$ret1 = CentreonUtils::mergeWithInitialValues($form, "dep_hSvPar");
		}
		for($i = 0; $i < count($ret1); $i++)	{
			$exp = explode("_", $ret1[$i]);
			if (count($exp) == 2) {
				$rq = "INSERT INTO dependency_serviceParent_relation ";
				$rq .= "(dependency_dep_id, service_service_id, host_host_id) ";
				$rq .= "VALUES ";
				$rq .= "('".$dep_id."', '".$exp[1]."', '".$exp[0]."')";
				$DBRESULT = $pearDB->query($rq);
			}
		}
	}

	function updateServiceDependencyServiceChilds($dep_id = null, $ret = array())
	{
		if (!$dep_id) {
		    exit();
		}
		global $form;
		global $pearDB;
		$rq = "DELETE FROM dependency_serviceChild_relation ";
		$rq .= "WHERE dependency_dep_id = '".$dep_id."'";
		$DBRESULT = $pearDB->query($rq);
		if (isset($ret["dep_hSvChi"])) {
			$ret1 = $ret["dep_hSvChi"];
		} else {
			$ret1 = CentreonUtils::mergeWithInitialValues($form, "dep_hSvChi");
		}
		for ($i = 0; $i < count($ret1); $i++)	{
			$exp = explode("_", $ret1[$i]);
			if (count($exp) == 2) {
				$rq = "INSERT INTO dependency_serviceChild_relation ";
				$rq .= "(dependency_dep_id, service_service_id, host_host_id) ";
				$rq .= "VALUES ";
				$rq .= "('".$dep_id."', '".$exp[1]."', '".$exp[0]."')";
				$DBRESULT = $pearDB->query($rq);
			}
		}
	}

	/**
	 * Update Service Dependency Host Children
	 */
	function updateServiceDependencyHostChildren($dep_id = null, $ret = array())
	{
		if (!$dep_id) {
		    exit();
		}
		global $form;
		global $pearDB;
		$rq = "DELETE FROM dependency_hostChild_relation ";
		$rq .= "WHERE dependency_dep_id = '".$dep_id."'";
		$DBRESULT = $pearDB->query($rq);
		if (isset($ret["dep_hHostChi"])) {
			$ret1 = $ret["dep_hHostChi"];
		} else {
			$ret1 = CentreonUtils::mergeWithInitialValues($form, "dep_hHostChi");
		}
		for ($i = 0; $i < count($ret1); $i++)	{
            $rq = "INSERT INTO dependency_hostChild_relation ";
            $rq .= "(dependency_dep_id, host_host_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$dep_id."', '".$ret1[$i]."')";
			$DBRESULT = $pearDB->query($rq);
		}
	}
?>
