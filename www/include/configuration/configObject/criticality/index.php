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

if (!isset($oreon)) {
    exit();
}

$critId = null;
if (isset($_REQUEST['crit_id'])) {
    $critId = $_REQUEST['crit_id'];
}

$select = null;
if (isset($_REQUEST['select'])) {
    $select = $_REQUEST['select'];
}

$dupNbr = null;
if (isset($_REQUEST['dupNbr'])) {
    $dupNbr = $_REQUEST['dupNbr'];
}

/*
 * Pear library
 */
require_once 'HTML/QuickForm.php';
require_once 'HTML/QuickForm/advmultiselect.php';
require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';
require_once './class/centreonCriticality.class.php';

/*
 * Path to the configuration dir
 */
$path = "./include/configuration/configObject/criticality/";
$criticality = new CentreonCriticality($pearDB);

/*
 * PHP functions
 */
switch ($o) {
    case "a" :
        require_once($path . "form.php");
        break;
    case "w" :
        require_once($path . "form.php");
        break;
    case "c" :
        require_once($path . "form.php");
        break;    
    case "d" :
        $criticality->delete($select);
        require_once($path . "list.php");
        break;
    default :
        require_once($path . "list.php");
        break;
}
?>