<?php

/***************************************************************************
 *
 *	 OUGC Block Profile Fields plugin (/inc/languages/english/ougc_bloreprofi.lang.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *   Stop certain profile fields from being edited.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

define('OUGC_BLOREPROFI_GROUPS', ''); // Comma separated list of groups that can edit (admins can always edit).
define('OUGC_BLOREPROFI_FIELDS', '3'); // Comma separated list of fields to block.

// Run the ACP hooks.
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_formcontainer_end', 'ougc_bloreprofi_formcontainer');
	$plugins->add_hook('admin_config_profile_fields_add_commit', 'ougc_bloreprofi_commit');
	$plugins->add_hook('admin_config_profile_fields_edit_commit', 'ougc_bloreprofi_commit');
}
else
{
	$plugins->add_hook('datahandler_user_validate', 'ougc_bloreprofi_validate');
	$plugins->add_hook('usercp_profile_start', 'ougc_bloreprofi_hide');
	$plugins->add_hook('modcp_editprofile_start', 'ougc_bloreprofi_hide');
}

// Necessary plugin information for the ACP plugin manager.
function ougc_bloreprofi_info()
{
	return array(
		'name'			=> 'OUGC Block Profile Fields',
		'description'	=> 'Stop certain profile fields from being edited.',
		'website'		=> 'http://mods.mybb.com/profile/25096',
		'author'		=> 'Omar Gonzalez',
		'authorsite'	=> 'http://community.mybb.com/user-25096.html',
		'version'		=> '1.0',
		'compatibility'	=> '16*',
		'guid'			=> ''
	);
}

// _install
function ougc_bloreprofi_install()
{
	global $db;

	if(!ougc_bloreprofi_is_installed())
	{
		$db->add_column('profilefields', 'block', 'smallint(1) NOT NULL DEFAULT \'0\'');
	}
}

// _is_installed
function ougc_bloreprofi_is_installed()
{
	global $db;

	static $installed = null;

	if($installed === null)
	{
		$installed = $db->field_exists('block', 'profilefields');
	}

	return $installed;
}

// _uninstall
function ougc_bloreprofi_uninstall()
{
	global $db;

	if(ougc_bloreprofi_is_installed())
	{
		$db->drop_column('profilefields', 'block');
	}
}

// Form container
function ougc_bloreprofi_formcontainer(&$args)
{
	global $run_module, $form_container, $lang, $mybb;

	if($mybb->input['action'] == 'add')
	{
		$lang_val = 'add_new_profile_field';
	}
	else
	{
		$lang_val = 'edit_profile_field';
	}

	if($run_module == 'config' && !empty($form_container->_title) && !empty($lang->$lang_val) && $form_container->_title == $lang->$lang_val)
	{
		global $form, $profile_field;
		isset($lang->ougc_bloreprofi) or $lang->load('ougc_bloreprofi');

		$form_container->output_row($lang->ougc_bloreprofi_container, $lang->ougc_bloreprofi_container_d, $form->generate_yes_no_radio('block', (!empty($mybb->input['block']) ? 1 : (!empty($profile_field['block']) ? 1 : 0))), '', array(), array('id' => 'block'));

		echo '<script type="text/javascript">
			Event.observe(window, "load", function() {
				new Peeker($("fieldtype"), $("block"), /^(select|radio|1|yes)$/, false);
			});
		</script>';
	}
}

// Commit changes
function ougc_bloreprofi_commit()
{
	global $mybb, $fid, $db;
	$fid = (int)(isset($fid) ? $fid : $mybb->input['fid']);
	$block = (int)!empty($mybb->input['block']);

	$db->update_query('profilefields', array('block' => $block), 'fid=\''.$fid.'\'');
}

// Stop field change from UCP.
function ougc_bloreprofi_validate(&$dh)
{
	global $mybb, $db, $lang;

	// Deactivated or no valid file/action/request/whatnot
	if(!($dh->method == 'update' && $mybb->request_method == 'post' && in_array($mybb->input['action'], array('do_profile', 'do_editprofile')) && in_array(THIS_SCRIPT, array('usercp.php', 'modcp.php')) && !empty($dh->data['uid'])))
	{
		return;
	}

	$dh->data['uid'] = (int)$dh->data['uid'];
	$user = get_user($dh->data['uid']);

	// Stop if is admin or function doesn't exists
	// Check edit permissions
	if(ougc_bloreprofi_canedit($uid))
	{
		return;
	}

	if(!($fields = ougc_bloreprofi_fields()))
	{
		return;
	}

	$userfields_cache = $userfields_cache_clean = array();
	$fids = 'fid'.implode(', fid', array_keys($fields));
	$query = $db->simple_select('userfields', $fids, 'ufid=\''.$dh->data['uid'].'\'');
	while($userfield = $db->fetch_array($query))
	{
		$userfields_cache[] = $userfield;
	}

	foreach($userfields_cache as $userfield)
	{
		foreach($userfield as $fid => $value)
		{
			$userfields_cache_clean[$fid] = my_strtolower($value);
		}
	}
	unset($userfields_cache);

	foreach($fields as $fid => $field)
	{
		$key = 'fid'.(int)$fid;
		if(!isset($dh->data['profile_fields'][$key]) || !isset($userfields_cache_clean[$key]))
		{
			continue;
		}

		if($userfields_cache_clean[$key] == my_strtolower($dh->data['profile_fields'][$key]))
		{
			continue;
		}

		// User is trying to change value
		if(in_array($userfields_cache_clean[$key], $field['values']))
		{
			isset($lang->ougc_bloreprofi) or $lang->load('ougc_bloreprofi');
			$lang_val = 'ougc_bloreprofi_error_'.$key;
			$dh->set_error((isset($lang->$lang_val) ? $lang->$lang_val : $lang->ougc_bloreprofi_error));
		}
	}
}

// Hide them from the UCP / ModCP
function ougc_bloreprofi_hide()
{
	global $mybb;

	if(!ougc_bloreprofi_canedit($uid))
	{
		if(!($fields = ougc_bloreprofi_fields()))
		{
			return;
		}

		$fids = implode(',', array_keys($fields));
		// What to search / replace?
		if(THIS_SCRIPT == 'usercp.php')
		{
			$search = 'editable=1';
			$replace = 'editable=1 AND fid NOT IN ('.$fids.')';
		}
		else
		{
			$search = 'profilefields';
			$replace = 'profilefields WHERE fid NOT IN ('.$fids.')';
		}

		// Dark magic
		ougc_bloreprofi_control_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0)
			{
				static $done = false;
				if(!$done && !$write_query && strpos($string, \'profilefields\'))
				{
					$done = true;
					$string = strtr($string, array(
						\''.$search.'\' => \''.$replace.'\'
					));
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}
}

// Get a comma separated list of fields to affect
function ougc_bloreprofi_fields()
{
	static $cache = null;

	if(!isset($cache))
	{
		global $db;
		$cache = array();

		$query = $db->simple_select('profilefields', 'fid, type', 'required=\'1\' AND  editable=\'1\'');
		while($field = $db->fetch_array($query))
		{
			$type = explode("\n", $field['type']);
			if(!empty($type[0]))
			{
				if($type[0] == 'select' || $type[0] == 'radio')
				{
					$cache[$field['fid']] = $field;
					$cache[$field['fid']]['type'] = $type[0];
					unset($type[0], $cache[$field['fid']]['fid']);
					$cache[$field['fid']]['values'] = array_map('my_strtolower', $type);
					//"fid,name,description,disporder,type,length,maxlength,required,editable,hidden,postnum,block"
				}
			}
		}
	}

	return $cache;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
function ougc_bloreprofi_control_object(&$obj, $code)
{
	static $cnt = 0;
	$newname = '_objcont_'.(++$cnt);
	$objserial = serialize($obj);
	$classname = get_class($obj);
	$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
	$checkstr_len = strlen($checkstr);
	if(substr($objserial, 0, $checkstr_len) == $checkstr)
	{
		$vars = array();
		// grab resources/object etc, stripping scope info from keys
		foreach((array)$obj as $k => $v)
		{
			if($p = strrpos($k, "\0"))
			{
				$k = substr($k, $p+1);
			}
			$vars[$k] = $v;
		}
		if(!empty($vars))
		{
			$code .= '
				function ___setvars(&$a) {
					foreach($a as $k => &$v)
						$this->$k = $v;
				}
			';
		}
		eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
		$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
		if(!empty($vars))
		{
			$obj->___setvars($vars);
		}
	}
	// else not a valid object or PHP serialize has changed
}

// Quick function
function ougc_bloreprofi_canedit($uid)
{
	global $mybb;

	if(!$mybb->usergroup['cancp'])
	{
		$user = get_user($uid);

		$gids = $user['usergroup'];
		if($user['additionalgroups'])
		{
			$gids .= ','.$user['additionalgroups'];
		}
		$usergroup = usergroup_permissions($gids);

		if($usergroup['cancp'] || !$mybb->usergroup['issupermod'])
		{
			return false;
		}
	}

	return true;
}