<?php
/**
	* OnDaemonRun Hook Script for SenAds module
	* Version : 1.0.0
	* Author : TGates
	* For Sentora v2.0.0 http://www.sentora.org
	* License http://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */
 
function addClientAdverts()
{
	global $zdbh, $controller;
/*
	# get reseller's clients
	# add custom vhost entries for header and/or footer advertising
	
	return $result; 
*/
}
function deleteClientAdverts()
{
	global $zdbh, $controller;
/*
	# get reseller's clients
	# remove custom vhost entries for header and/or footer advertising
	
	return $result; 
*/
}
echo fs_filehandler::NewLine() . "START SenAds Update Hook." . fs_filehandler::NewLine();
if (ui_module::CheckModuleEnabled('SenAds')) {
	
    echo "SenAds module ENABLED..." . fs_filehandler::NewLine();
	echo "Adding Advertising vHost Entries..." . fs_filehandler::NewLine();
		# addClientAdverts();
	echo fs_filehandler::NewLine()."Advertising vHosts Added." . fs_filehandler::NewLine();

	echo "Removing Advertising vHosts Entries..." . fs_filehandler::NewLine();
		# deleteClientAdverts();
	echo fs_filehandler::NewLine()."Advertising vHosts Removed." . fs_filehandler::NewLine();
	
} else {
	
    echo "SenAds module DISABLED...nothing to do." . fs_filehandler::NewLine();
}

echo "END SenAds Update Hook." . fs_filehandler::NewLine();

?>