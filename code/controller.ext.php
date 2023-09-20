<?php
/**
	* Controller Script for SenAds module
	* Version : 1.2.0
	* Author : TGates
	* For Sentora v2.0.0 http://www.sentora.org
	* License http://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

// Normal functions
// Function to retrieve remote XML for update check
function check_remote_xml($xmlurl,$destfile)
{
	if (file_exists($xmlurl))
	{
		$feed = simplexml_load_file($xmlurl);
		if ($feed)
		{
			// $feed is valid, save it
			$feed->asXML($destfile);
		}
		elseif (file_exists($destfile))
		{
			// $feed is not valid, grab the last backup
			$feed = simplexml_load_file($destfile);
		}
		else
		{
			die('Unable to retrieve XML file');
		}
		die('No update data available');
	}
}

class module_controller extends ctrl_module
{
	static $enabled;
	static $disabled;
	static $active;
	static $saved;

    # Load CSS and JS files
    static function getInit()
	{
        global $controller;
			
		$line = '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/senads.css">';
		$line .= "<script type='text/javascript' src='/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/tabs.js'></script>";
		$line .= "<script type='text/javascript' src='/modules/" . $controller->GetControllerRequest('URL', 'module') . "/code/tinymce/tinymce.min.js'></script>";
        $line .= "<script>
					tinymce.init({
						selector: 'textarea#editor',
						plugins: 'image noneditable preview help',
						toolbar: 'image bold italic preview help',
						images_file_types: 'jpg,png,gif,bmp'
					}); 
				</script>";
        $line .= "<style> 
					textarea {
						width: 100%;
						height: 295px;
						padding: 12px 20px;
						box-sizing: border-box;
						border-radius: 4px;
						resize: none;
						overflow-y: scroll;
					}
					</style>";

        return $line;
    }

	// Module update check functions
    static function getModuleVersion()
	{
        global $controller;

        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_version = $mod_config->document->version[0]->tagData;
        return "v".$module_version."";
    }
	
    static function getCheckUpdate()
	{
        global $zdbh, $controller, $zlo;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = check_remote_xml($module_updateurl, $module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml");
		if (!file_exists($myfile))
		{
			return false;
		}
		else
		{
			$update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml"));
			$update_config->Parse();
			$update_url = $update_config->document->downloadurl[0]->tagData;
			$update_version = $update_config->document->latestversion[0]->tagData;

			if($update_version > $module_version) return true;
			return false;
		}
    }

    static function getImageEncodingInfo()
	{
        global $controller;

		$imageEncodingText = "Some servers do not allow URLs as an image source. If images do not show on your ad, use the 'Encode an Image' tab to create code to paste into the 'Image Source' box in the editor.";
		
		$imageEncodingInfo = ui_sysmessage::shout(ui_language::translate($imageEncodingText), "zannounceok");

		return $imageEncodingInfo;
    }

    static function getUploadImage()
    {
		global $controller;

		$imageUploadForm = array();
		$imageUploadForm = '
			<form action="./?module=senads&action=UploadImage" method="post" enctype="multipart/form-data">
				<table class="table table-striped">
					<tr>
						<th>' . ui_language::translate("Upload Image to be encoded") . ':</th>
						<td>
						</td>
					</tr>
						<td>
							<input type="file" name="imageencode" id="imageencode" required/>
							<button class="btn btn-primary" type="submit" name="submit">' . ui_language::translate("Encode Image") . '</button>
						</td>
					</tr>
				</table>
			</form>';

		if(isset($_FILES['imageencode']))
		{
			$errors = array();
			$allowed_ext = array('jpg','jpeg','png','gif');
			$file_name = $_FILES['imageencode']['name'];
			$file_name = strtolower($file_name);
			$file_tmp = explode('.', $file_name);
			$file_ext = end($file_tmp);

			$file_size = $_FILES['imageencode']['size'];
			$file_tmp = $_FILES['imageencode']['tmp_name'];

			$type = pathinfo($file_tmp, PATHINFO_EXTENSION);
			$data = file_get_contents($file_tmp);
			
			if(in_array($file_ext, $allowed_ext) === false)
			{
				$errors[] = ui_language::translate('Extension not allowed');
			}

			if($file_size > 2097152)
			{
				$errors[] = ui_language::translate('File size must be under 2mb');
			}
			if(empty($errors))
			{
				$base64 = '<button class="btn btn-success btn-responsive" id="copyTextBtn">' . ui_language::translate("Click HERE to copy encoded image and paste it into image Source in the editor") . '</button>
							<textarea id="copytextarea">data:image/gif;base64,' . base64_encode($data) . '</textarea>';
				return $base64;
			}
			else
			{
				foreach($errors as $error)
				{
					echo $error , '<br/>'; 
				}
			}
		}
		return $imageUploadForm;
    }
	
	# email subject and message
	static function EmailData()
	{
		global $controller;
		
		# convert to store defaults in DB table? Make Editable?
		$subject = "SenAds Advertisement";
		$body = "Your SenAds advertisement banner at your hosting company is under review. Please contact your hosting provider as soon as possible.";
		
		$subject = htmlspecialchars($subject);
		$body = htmlspecialchars($body);
		
		$email = array("subject" => $subject, "body" => $body);
		
		return $email;
	}

    static function getBannerRules()
    {
        global $controller;

		$path = $controller->GetControllerRequest('URL', 'sentora_root') . "modules/" . $controller->GetControllerRequest('URL', 'module') . "/code/banner_rules.txt";
		
		if (file_exists($path))
		{
			$bannerRules = ui_language::translate(nl2br(file_get_contents($path)));
			
			return $bannerRules;
		}
	}

	# Admin menu
	static function getAdmin()
	{
		$user = ctrl_users::GetUserDetail();
		return ($user['usergroup'] == 'Administrators');
	}

	static function getAdminMenu()
	{
		global $zdbh, $controller;
		
		$email = self::EmailData();
		
		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid'];
		$path = "/modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/";
		
		# get ALL active SenAds
		$getAll = $zdbh->prepare("SELECT sa_rid_in, sa_pid_in FROM x_senads ORDER BY sa_rid_in ASC");
		$getAll->execute();
		$arids = $getAll->fetchAll();
		$rids = array_column($arids, 'sa_rid_in');
		$pids = array_column($arids, 'sa_pid_in');
	
		# create the form if Reseller has any adverts
		if (!empty($rids))
		{
			$adminMenu = "<form id='adminMenu' name='EnableDisable' action='/?module=senads&action=EnableDisable' method='post'>";
			$adminMenu .= "<table class='table-striped'>
			<tr>
			<th>" . ui_language::translate("Reseller") . ":</th>
			<th>" . ui_language::translate("Send Warning Email") . ":</th>
			<th>" . ui_language::translate("View Advert") . "</th>
			<th>" . ui_language::translate("Disable Ad") . "</th>
			</tr>";
			
			# list reseller only once			
			$rids = array_unique($rids);
			
			foreach($rids as $index => $value)
			{
				$rid = $rids[$index];
				$pid = $pids[$index];

				# get reseller's name and email
				$getdata = $zdbh->prepare("SELECT ac_user_vc, ac_email_vc FROM x_accounts WHERE ac_id_pk = :rid");
				$getdata->bindParam(':rid', $rid);
				$getdata->execute();
				$row = $getdata->fetch();
				
				$adminMenu .= "<tr>";
				$adminMenu .= "<td align='left'><button class='btn btn-link btn-xs' type='submit' formaction='?module=manage_clients&show=Edit&other=$rid'>" . $row['ac_user_vc'] . "</button></td>";
				$adminMenu .= "<td align='middle'><a class='btn btn-link' role='button' href='mailto:" . $row['ac_email_vc'] . "?subject=" . $email['subject'] . "&body=" . $email['body'] . "'>" . $row['ac_email_vc'] . "</a></td>";
				$adminMenu .= "<td align='middle'><a class='btn btn-link' role='button' target='_blank' href=" . $path . $rid . '_senads.html' .  ">" . ui_language::translate("View Advert") . "</a></td>";
				$adminMenu .= "<td align='middle'><button class='btn btn-link btn-xs' type='submit' name='inAction' value='Disable'>" . ui_language::translate("Disable") . "</button>
				<input type='hidden' name='inPackage' value='$pid'></td>";
				$adminMenu .= "</tr>";
			}
			$adminMenu .= "</table>" . runtime_csfr::Token() . "</form>";
		}
		else
		{
			$adminMenu = ui_language::translate("<h3>You do not have any Resellers using SenAds.</h3>");
		}
		return $adminMenu;
	}

    static function getCreateDefault()
    {
        global $zdbh, $controller;

		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid'];
		$path = $controller->GetControllerRequest('URL', 'sentora_root') . "modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/";
		
		if (!file_exists($path . $rid . '_senads.html'))
		{
			$toReturn = "<form id='senads' name='CreateDefault' action='/?module=senads&action=CreateDefault' method='post'>
			<button class='btn btn-warning' type='submit' name='inAction' value='CreateDefault'>" . ui_language::translate("Create Default Page First") . "</button></form>";
			
			return $toReturn;
		}
	}

	static function doCreateDefault()
	{
		global $controller;
		$formvars = $controller->GetAllControllerRequests('FORM');
		
		if (self::CreateDefault($formvars['inAction']))
		return true;
	}

	static function CreateDefault($action)
	{
		global $zdbh, $controller;
		
		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid'];
		$path = $controller->GetControllerRequest('URL', 'sentora_root') . "modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/";
			
		if ($action == 'CreateDefault')
		{
			# convert to store default page in DB table?
			copy($path . 'tpl_senads.html', $path . $rid . '_senads.html');
			
			self::$saved = true;
            return true;
		}
		else
		{
			self::$error;
			return false;
		}
	}

	static function getEditAdvert()
	{
		global $controller;
		
		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid']; # set reseller's ID
		$path = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/";
		
		if (file_exists($path . $rid . '_senads.html'))
		{
			$text = file_get_contents($path . $rid . '_senads.html');
			# save page 
			if ((isset($_POST['editor'])) && (isset($_POST['save'])) && ($_POST['save'] == 'Save'))
			{
				$text = $_POST['editor'];
				$page = $rid . '_senads.html';
				$file = $path . $page;
				
				# save the file
				file_put_contents($file, $text);
				
				# preview saved page
				$editor = "<div align='center'>";
				$editor .= "<form name='preview' action='?module=senads&action=edit' method='post'>
				<input class='btn btn-warning' type='submit' value='Edit' /></form>";
				$editor .= "</div><br>";
				$editor .= "<div>$text</div>";
			}
			else
			{
				# Editor
				$editor =  "<div align='center'>";
				$editor .=  "<form name='editor' action='?module=senads&action=save' method='post'>";
				$editor .=  "<textarea class='textarea' name='editor' id='editor'>" . htmlspecialchars($text) . "</textarea><br />
				<input type='hidden' name='page' value='$rid'>";
				$editor .=  "<input class='btn btn-success' type='submit' name='save' value='Save' />";
				$editor .=  "<input class='btn btn-danger' type='submit' name='cancel' value='Cancel' onclick='?module=senads&action=cancel' /><br />";
				$editor .=  "</form></div>";
			}
			return $editor;
		}
	}
	
    static function getEnableDisableForm()
    {
        global $zdbh, $controller;

		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid'];
		$path = $controller->GetControllerRequest('URL', 'sentora_root') . "modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/";

		# enable/disable form with package select
		# get reseller's available packages
		$res = $zdbh->prepare("SELECT COUNT(*) FROM x_packages WHERE pk_reseller_fk=:uid AND pk_deleted_ts IS NULL");
		$res->execute(array(':uid' => $rid));
		$packages_exists = ($res->fetchColumn() > 0) ? true : false;

		if ($packages_exists == 'true')
		{
			# get available packages
			$stmt = $zdbh->prepare("SELECT * FROM x_packages WHERE pk_reseller_fk=:uid AND pk_deleted_ts IS NULL");

			# create the form
			$toReturn = "<form id='senads' name='EnableDisable' action='/?module=senads&action=EnableDisable' method='post'>";
			$toReturn .= "<table>
			<tr>
			<th>" . ui_language::translate("Package") . ":</th>
			<th>" . ui_language::translate("Ads Enabled") . ":</th>
			<th>" . ui_language::translate("Change?") . "</th>
			</tr>";

			$stmt->execute(array(':uid' => $rid));
			while ($packageData = $stmt->fetch())
			{
				$package = $packageData['pk_name_vc'];
				$packageID = $packageData['pk_id_pk'];

				$stmt2 = $zdbh->prepare("SELECT * FROM x_senads WHERE sa_pid_in = :pid LIMIT 1");
				$stmt2->bindParam(':pid', $packageID, PDO::PARAM_INT);
				$stmt2->execute();
				$row = $stmt2->fetch(PDO::FETCH_ASSOC);

				if (!$row ? $pkg_enabled = 'No' : $pkg_enabled = 'Yes');
				
				$toReturn .= "<tr>
				<td align='left'>" . $package . "</td>
				<td align='middle'>" . $pkg_enabled;
				$toReturn .= "</td>
				<td align='middle'>
				<input type='radio' name='inPackage' value=" . $packageID . ">
				</td>
				</tr>";
			}
			$toReturn .= "</table>";
			$toReturn .= "<p></p>
						<button class='btn btn-success' type='submit' name='inAction' value='Enable'>" . ui_language::translate("Enable Selected") . "</button>
						<button class='btn btn-danger' type='submit' name='inAction' value='Disable'>" . ui_language::translate("Disable Selected") . "</button>
					</form>";
		}
		else
		{
			$toReturn = "<p>" . ui_language::translate("No packages exist for this Account.") . "</p>";
		}
		return $toReturn;
	}

	static function doEnableDisable()
	{
		global $controller;
		
		$formvars = $controller->GetAllControllerRequests('FORM');
		if (self::ExecuteEnableDisable($formvars['inAction'], $formvars["inPackage"]))
		return true;
	}
	
	static function ExecuteEnableDisable($action, $package)
	{
		global $zdbh, $controller;
		
		$currentuser = ctrl_users::GetUserDetail();
		$rid = $currentuser['userid']; # set reseller's ID

		# SenAds custom vhost entry
		$senAds = "# SenAds - START" . fs_filehandler::NewLine();
		$senAds .= "php_value auto_prepend_file " . ctrl_options::GetSystemOption('sentora_root') . "modules/" . $controller->GetControllerRequest('URL', 'module') . "/pages/" . $rid . "_senads.html" . fs_filehandler::NewLine();
		$senAds .= "AddType application/x-httpd-php htm" . fs_filehandler::NewLine();
		$senAds .= "AddType application/x-httpd-php html" . fs_filehandler::NewLine();
		$senAds .= "# SenAds - END" . fs_filehandler::NewLine();

		# get all accounts using selected package
		$stmt = $zdbh->prepare("SELECT ac_id_pk FROM x_accounts WHERE ac_package_fk = :package_id");
		$stmt->bindParam(':package_id', $package);
		$stmt->execute();
		$cids = $stmt->fetchAll();
		$cids = array_column($cids, 'ac_id_pk');
		
		# check to see if package already enabled
		$verify = $zdbh->prepare("SELECT * FROM x_senads WHERE sa_pid_in = :pid LIMIT 1");
		$verify->bindParam(':pid', $package, PDO::PARAM_INT);
		$verify->execute();
		$vresults = $verify->fetch(PDO::FETCH_ASSOC);
				
		# Enable Ads per package
		if ($action == 'Enable' && $package != '' && $vresults == NULL)
		{
			# add package to x_senads DB table
			$stmt = $zdbh->prepare("INSERT IGNORE INTO x_senads (sa_rid_in, sa_pid_in, sa_enabled_in) VALUES (:rid, :pid, '1')");
			$stmt->bindParam(':rid', $rid);
			$stmt->bindParam(':pid', $package);
			$stmt->execute();

			# select matching uid/vhost entries
			foreach ($cids as $cid)
			{
				# get client's vhost ID and name
				$get_vhosts = $zdbh->prepare("SELECT vh_name_vc FROM x_vhosts WHERE vh_acc_fk = :cid");
				$get_vhosts->bindParam(':cid', $cid, PDO::PARAM_INT);
				$get_vhosts->execute();
				$vhnames = $get_vhosts->fetchAll();
				$vhnames = array_column($vhnames, 'vh_name_vc');

				foreach ($vhnames as $vhname)
				{
					# get custom vhost entries for client
					$get_vhost = $zdbh->prepare("SELECT vh_custom_tx FROM x_vhosts WHERE vh_name_vc = :vhname");
					$get_vhost->bindParam(':vhname', $vhname);
					$get_vhost->execute();
					$custom_vh = $get_vhost->fetch(PDO::FETCH_ASSOC);
					
					# add SenAds vhost entries
					$custom_vh = $custom_vh['vh_custom_tx'];
					$input = $custom_vh . "\n" . $senAds;
					
					# update client's custom vhost data
					$update = $zdbh->prepare("UPDATE x_vhosts SET vh_custom_tx = :input WHERE vh_name_vc = :vhname");
					$update->bindParam(':input', $input);							
					$update->bindParam(':vhname', $vhname);
					$update->execute();
					
					unset($input);
				}
			}
			# update apache vhost
			self::SetWriteApacheConfigTrue();
			
			self::$enabled = true;
            return true;
		}
		# Disable Ads
		elseif ($action == 'Disable' && $package != '')
		{
			foreach ($cids as $cid)
			{
				# get custom vhost entries for client
				$get_vhosts = $zdbh->prepare("SELECT vh_name_vc FROM x_vhosts WHERE vh_acc_fk = :cid");
				$get_vhosts->bindParam(':cid', $cid, PDO::PARAM_INT);
				$get_vhosts->execute();
				$vhnames = $get_vhosts->fetchAll();
				$vhnames = array_column($vhnames, 'vh_name_vc');

				foreach ($vhnames as $vhname)
				{
					$get_vhost = $zdbh->prepare("SELECT vh_custom_tx FROM x_vhosts WHERE vh_name_vc = :vhname");
					$get_vhost->bindParam(':vhname', $vhname);
					$get_vhost->execute();
					$custom_vh = $get_vhost->fetch(PDO::FETCH_ASSOC);
					
					$custom_vh = $custom_vh['vh_custom_tx'];

					# remove SenAds vhost entries
					$input = str_replace($senAds, '', $custom_vh);
					$input = trim($input);
					$input = !empty($input) ? $input : NULL;
			
					# update client's custom vhost data
					$update = $zdbh->prepare("UPDATE x_vhosts SET vh_custom_tx = :input WHERE vh_name_vc = :vhname");
					$update->bindParam(':input', $input);
					$update->bindParam(':vhname', $vhname);
					$update->execute();
				}
			}
			# remove package from x_senads DB table
			$stmt2 = $zdbh->prepare("DELETE FROM x_senads WHERE sa_pid_in = :pid");
			$stmt2->bindParam(':pid', $package);
			$stmt2->execute();
			
			# update apache vhost
			self::SetWriteApacheConfigTrue();
			
			self::$disabled = true;
            return false;
		}
		else
		{
			self::$active = true;
            return true;
		}
	}

	static function SetWriteApacheConfigTrue()
	{
		global $zdbh;
		$sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'");
		$sql->execute();
	}

    static function getResult()
    {
        if (!fs_director::CheckForEmptyValue(self::$enabled))
		{
            return ui_sysmessage::shout(ui_language::translate("Package Enabled."), "zannounceok");
        }
        if (!fs_director::CheckForEmptyValue(self::$disabled))
		{
            return ui_sysmessage::shout(ui_language::translate("Package Disabled."), "zannounceok");
        }
        if (!fs_director::CheckForEmptyValue(self::$active))
		{
            return ui_sysmessage::shout(ui_language::translate("Package already enabled."), "zannounceok");
        }
        if (!fs_director::CheckForEmptyValue(self::$saved))
		{
            return ui_sysmessage::shout(ui_language::translate("Your default page was created successfully!"), "zannounceok");
        }
		else
		{
            return NULL;
        }
        return;
    }

    static function getCopyright()
	{
        $copyright = '<font face="ariel" size="2">' . ui_module::GetModuleName() . ' v1.2.0 &copy; 2021-' . date("Y") . ' by <a target="_blank" href="#">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a>&nbsp;&#8212;&nbsp;' . ui_language::translate("Help support future development of this module and donate today!") . '</font> ';
		
        return $copyright;
    }

    static function getDonation()
	{
        $donation = '<br />' . ui_language::translate("Donate to module developer:") . '&nbsp;
		<form action="https://www.paypal.com/donate" method="post" target="_blank">
			<input type="hidden" name="hosted_button_id" value="MCDRPGAZFNEMY" />
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" height="15" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
			<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
		</form>';
		
        return $donation;
    }
}
?>