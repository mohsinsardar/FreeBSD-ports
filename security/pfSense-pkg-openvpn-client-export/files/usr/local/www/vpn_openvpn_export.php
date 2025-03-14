<?php
/*
 * vpn_openvpn_export.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008 Shrew Soft Inc
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("openvpn-client-export.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("certs.inc");
require_once("classes/Form.class.php");

global $current_openvpn_version, $current_openvpn_version_rev;
global $previous_openvpn_version, $previous_openvpn_version_rev;
global $legacy_openvpn_version, $legacy_openvpn_version_rev;
global $dyndns_split_domain_types, $p12_encryption_levels;

$pgtitle = array("OpenVPN", "Client Export Utility");

$a_server = config_get_path('openvpn/openvpn-server', []);
$a_user = config_get_path('system/user', []);
$a_cert = config_get_path('cert', []);

$ras_server = array();
foreach ($a_server as $server) {
	if (isset($server['disable'])) {
		continue;
	}
	$vpnid = $server['vpnid'];
	$ras_user = array();
	$ras_certs = array();
	if (stripos($server['mode'], "server") === false) {
		continue;
	}
	$ecdsagood = array();
	foreach (config_get_path('cert', []) as $cert) {
		if (!empty($cert['prv']) &&
		    !cert_check_pkey_compatibility($cert['prv'], 'OpenVPN')) {
			continue;
		} else {
			$ecdsagood[] = $cert['refid'];
		}
	}

	if (($server['mode'] == "server_tls_user") && ($server['authmode'] == "Local Database")) {
		foreach ($a_user as $uindex => $user) {
			if (!is_array($user['cert'])) {
				continue;
			}
			foreach ($user['cert'] as $cindex => $cert) {
				// If $cert is not an array, it's a certref not a cert.
				if (!is_array($cert)) {
					$cert = lookup_cert($cert);
					$cert = $cert['item'];
				}

				$purpose = cert_get_purpose($cert['crt']);
				if (($cert['caref'] != $server['caref']) ||
				    !in_array($cert['refid'], $ecdsagood) ||
				    ($purpose['server'] == 'Yes')) {
					continue;
				}
				$ras_userent = array();
				$ras_userent['uindex'] = $uindex;
				$ras_userent['cindex'] = $cindex;
				$ras_userent['name'] = $user['name'];
				$ras_userent['certname'] = $cert['descr'];
				$ras_userent['cert'] = $cert;
				$ras_user[] = $ras_userent;
			}
		}
	} elseif (($server['mode'] == "server_tls") ||
			(($server['mode'] == "server_tls_user") && ($server['authmode'] != "Local Database"))) {
		foreach ($a_cert as $cindex => $cert) {

			$purpose = cert_get_purpose($cert['crt']);
			if (($cert['caref'] != $server['caref']) ||
			    ($cert['refid'] == $server['certref']) ||
			    !in_array($cert['refid'], $ecdsagood) ||
			    ($purpose['server'] == 'Yes')) {
				continue;
			}
			$ras_cert_entry['cindex'] = $cindex;
			$ras_cert_entry['certname'] = $cert['descr'];
			$ras_cert_entry['certref'] = $cert['refid'];
			$ras_certs[] = $ras_cert_entry;
		}
	}

	$ras_serverent = array();
	$prot = $server['protocol'];
	$port = $server['local_port'];
	if ($server['description']) {
		$name = "{$server['description']} {$prot}:{$port}";
	} else {
		$name = "Server {$prot}:{$port}";
	}
	$ras_serverent['index'] = $vpnid;
	$ras_serverent['name'] = $name;
	$ras_serverent['users'] = $ras_user;
	$ras_serverent['certs'] = $ras_certs;
	$ras_serverent['mode'] = $server['mode'];
	$ras_serverent['crlref'] = $server['crlref'];
	$ras_serverent['authmode'] = $server['authmode'] != "Local Database" ? 'other' : 'local';
	$ras_server[$vpnid] = $ras_serverent;
}

$id = $_POST['id'];
$act = $_POST['act'];

global $simplefields;
$simplefields = array('server','useaddr','useaddr_hostname','verifyservercn','blockoutsidedns','legacy','bindmode',
	'usepkcs11','pkcs11providers',
	'usetoken','usepass',
	'useproxy','useproxytype','proxyaddr','proxyport', 'silent','useproxypass','proxyuser');
	//'pass','proxypass','advancedoptions'

$cfg_path = 'installedpackages/vpn_openvpn_export/defaultsettings';

if (isset($_POST['save'])) {
	$vpnid = $_POST['server'];
	$index = count(config_get_path('installedpackages/vpn_openvpn_export/serverconfig/item', []));
	foreach(config_get_path('installedpackages/vpn_openvpn_export/serverconfig/item', []) as $key => $cfg) {
		if ($cfg['server'] == $vpnid) {
			$index = $key;
			break;
		}
	}
	$cfg_path = "installedpackages/vpn_openvpn_export/serverconfig/item/{$index}";
	if ($_POST['pass'] <> DMYPWD) {
		if ($_POST['pass'] <> $_POST['pass_confirm']) {
			$input_errors[] = "Different certificate passwords entered.";
		}
		config_set_path("{$cfg_path}/pass", $_POST['pass']);
	}
	if ($_POST['proxypass'] <> DMYPWD) {
		if ($_POST['proxypass'] <> $_POST['proxypass_confirm']) {
			$input_errors[] = "Different Proxy passwords entered.";
		}
		config_set_path("{$cfg_path}/proxypass", $_POST['proxypass']);
	}

	foreach ($simplefields as $value) {
		config_set_path("{$cfg_path}/{$value}", $_POST[$value]);
	}
	config_set_path("{$cfg_path}/advancedoptions", base64_encode($_POST['advancedoptions']));
	if (empty($input_errors)) {
		write_config("Save openvpn client export defaults");
	}
}

foreach(config_get_path('installedpackages/vpn_openvpn_export/serverconfig/item', []) as $i => $item) {
	config_set_path("installedpackages/vpn_openvpn_export/serverconfig/item/{$i}/advancedoptions",
	    base64_decode($item['advancedoptions']));
}

if (!empty($act)) {

	$srvid = $_POST['srvid'];
	$usrid = $_POST['usrid'];
	$crtid = $_POST['crtid'];
	$srvcfg = get_openvpnserver_by_id($srvid);
	if ($srvid === false) {
		pfSenseHeader("vpn_openvpn_export.php");
		exit;
	} else if (($srvcfg['mode'] != "server_user") &&
		(($usrid === false) || ($crtid === false))) {
		pfSenseHeader("vpn_openvpn_export.php");
		exit;
	}

	if ($srvcfg['mode'] == "server_user") {
		$nokeys = true;
	} else {
		$nokeys = false;
	}

	$useaddr = '';
	if (isset($_POST['useaddr']) && !empty($_POST['useaddr'])) {
		$useaddr = trim($_POST['useaddr']);
	}

	if (!(is_ipaddr($useaddr) || is_hostname($useaddr) ||
		in_array($useaddr, array("serveraddr", "servermagic", "servermagichost", "serverhostname")))) {
		$input_errors[] = "An IP address or hostname must be specified.";
	}

	$advancedoptions = $_POST['advancedoptions'];

	$verifyservercn = $_POST['verifyservercn'];
	$blockoutsidedns = $_POST['blockoutsidedns'];
	$legacy = $_POST['legacy'];
	$silent = $_POST['silent'];
	$bindmode = $_POST['bindmode'];
	$usetoken = $_POST['usetoken'];
	if ($usetoken && (substr($act, 0, 10) == "confinline")) {
		$input_errors[] = "Microsoft Certificate Storage cannot be used with an Inline configuration.";
	}
	if ($usetoken && (($act == "conf_yealink_t28") || ($act == "conf_yealink_t38g") || ($act == "conf_yealink_t38g2") || ($act == "conf_snom"))) {
		$input_errors[] = "Microsoft Certificate Storage cannot be used with a Yealink or SNOM configuration.";
	}
	$usepkcs11 = $_POST['usepkcs11'];
	$pkcs11providers = $_POST['pkcs11providers'];
	if ($usepkcs11 && !$pkcs11providers) {
		$input_errors[] = "You must provide the PKCS#11 providers.";
	}
	$pkcs11id = $_POST['pkcs11id'];
	if ($usepkcs11 && !$pkcs11id) {
		$input_errors[] = "You must provide the PKCS#11 ID.";
	}
	$password = "";
	if ($_POST['password']) {
		if ($_POST['password'] != DMYPWD) {
			$password = $_POST['password'];
		} else {
			$password = config_get_path("{$cfg_path}/pass");
		}
	}
	if (isset($_POST['p12encryption']) &&
	    array_key_exists($_POST['p12encryption'], $p12_encryption_levels)) {
		$p12encryption = $_POST['p12encryption'];
	} else {
		$p12encryption = 'high';
	}

	$want_cert = false;
	if (($srvcfg['mode'] == "server_tls_user") && ($srvcfg['authmode'] == "Local Database")) {
		if (array_key_exists($usrid, $a_user) &&
		    array_key_exists('cert', $a_user[$usrid]) &&
		    array_key_exists($crtid, $a_user[$usrid]['cert'])) {
			$want_cert = true;
			$cert = lookup_cert($a_user[$usrid]['cert'][$crtid]);
			$cert = $cert['item'];
		} else {
			$input_errors[] = "Invalid user/certificate index value.";
		}
	} elseif ($srvcfg['mode'] != "server_user") {
		$want_cert = true;
		$cert = config_get_path("cert/{$crtid}");
	}

	if ($want_cert) {
		if (empty($cert)) {
			$input_errors[] = "Unable to locate the requested certificate.";
		} elseif (($srvcfg['mode'] != "server_user") &&
			  !$usepkcs11 &&
			  !$usetoken &&
			  empty($cert['prv'])) {
			$input_errors[] = "A private key cannot be empty if PKCS#11 or Microsoft Certificate Storage is not used.";
		}
	}

	$proxy = "";
	if (!empty($_POST['proxy_addr']) || !empty($_POST['proxy_port'])) {
		$proxy = array();
		if (empty($_POST['proxy_addr'])) {
			$input_errors[] = "An address for the proxy must be specified.";
		} else {
			$proxy['ip'] = $_POST['proxy_addr'];
		}
		if (empty($_POST['proxy_port'])) {
			$input_errors[] = "A port for the proxy must be specified.";
		} else {
			$proxy['port'] = $_POST['proxy_port'];
		}
		$proxy['proxy_type'] = $_POST['proxy_type'];
		$proxy['proxy_authtype'] = $_POST['proxy_authtype'];
		if ($_POST['proxy_authtype'] != "none") {
			if (empty($_POST['proxy_user'])) {
				$input_errors[] = "A username for the proxy configuration must be specified.";
			} else {
				$proxy['user'] = $_POST['proxy_user'];
			}
			if (!empty($_POST['proxy_user']) && empty($_POST['proxy_password'])) {
				$input_errors[] = "A password for the proxy user must be specified.";
			} else {
				if ($_POST['proxy_password'] != DMYPWD) {
					$proxy['password'] = $_POST['proxy_password'];
				} else {
					$proxy['password'] = config_get_path("{$cfg_path}/proxypass");
				}
			}
		}
	}

	$exp_name = openvpn_client_export_prefix($srvid, $usrid, $crtid);

	if (substr($act, 0, 4) == "conf") {
		switch ($act) {
			case "confzip":
				$exp_name = urlencode($exp_name . "-config.zip");
				$expformat = "zip";
				break;
			case "conf_yealink_t28":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t28";
				break;
			case "conf_yealink_t38g":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t38g";
				break;
			case "conf_yealink_t38g2":
				$exp_name = urlencode("client.tar");
				$expformat = "yealink_t38g2";
				break;
			case "conf_snom":
				$exp_name = urlencode("vpnclient.tar");
				$expformat = "snom";
				break;
			case "confinline":
				$exp_name = urlencode($exp_name . "-config.ovpn");
				$expformat = "inline";
				break;
			case "confinlinedroid":
				$exp_name = urlencode($exp_name . "-android-config.ovpn");
				$expformat = "inlinedroid";
				break;
			case "confinlineconnect":
				$exp_name = urlencode($exp_name . "-connect-config.ovpn");
				$expformat = "inlineconnect";
				break;
			case "confinlinevisc":
				$exp_name = urlencode($exp_name . "-viscosity-config.ovpn");
				$expformat = "inlinevisc";
				break;
			default:
				$exp_name = urlencode($exp_name . "-config.ovpn");
				$expformat = "baseconf";
		}
		$exp_path = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $nokeys, $proxy, $expformat, $password, $p12encryption, false, false, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	}

	if ($act == "visc") {
		$exp_name = urlencode($exp_name . "-Viscosity.visc.zip");
		$exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $password, $p12encryption, $proxy, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	}

	if (substr($act, 0, 4) == "inst") {
		$openvpn_version = substr($act, 5);
		$exp_name = "openvpn-{$exp_name}-install-";
		switch ($openvpn_version) {
			case "Win7":
				$legacy = true;
				$exp_name .= "{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win7.exe";
				break;
			case "Win10":
				$legacy = true;
				$exp_name .= "{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win10.exe";
				break;
			case "x86-previous":
				$exp_name .= "{$previous_openvpn_version}-I{$previous_openvpn_version_rev}-x86.exe";
				break;
			case "x64-previous":
				$exp_name .= "{$previous_openvpn_version}-I{$previous_openvpn_version_rev}-amd64.exe";
				break;
			case "x86-current":
				$exp_name .= "{$current_openvpn_version}-I{$current_openvpn_version_rev}-x86.exe";
				break;
			case "x64-current":
			default:
				$exp_name .= "{$current_openvpn_version}-I{$current_openvpn_version_rev}-amd64.exe";
				break;
		}

		$exp_name = urlencode($exp_name);
		$exp_path = openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $password, $p12encryption, $proxy, $advancedoptions, substr($act, 5), $usepkcs11, $pkcs11providers, $pkcs11id, $silent);
	}

	/* pfSense >= 2.5.0 with OpenVPN >= 2.5.0 has ciphers not compatible with
	 * legacy clients, check for those and warn */
	if ($legacy) {
		global $legacy_incompatible_ciphers;
		$settings = get_openvpnserver_by_id($srvid);
		if (in_array($settings['data_ciphers_fallback'], $legacy_incompatible_ciphers)) {
			$input_errors[] = gettext("The Fallback Data Encryption Algorithm for the selected server is not compatible with Legacy clients.");
		}
	}

	if (!$exp_path) {
		$input_errors[] = "Failed to export config files!";
	}

	if (empty($input_errors)) {
		if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
			$exp_size = strlen($exp_path);
		} else {
			$exp_size = filesize($exp_path);
		}
		header('Pragma: ');
		header('Cache-Control: ');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename={$exp_name}");
		header("Content-Length: $exp_size");
		if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
			echo $exp_path;
		} else {
			readfile($exp_path);
			@unlink($exp_path);
		}
		exit;
	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}
$tab_array = array();
$tab_array[] = array(gettext("Server"), false, "vpn_openvpn_server.php");
$tab_array[] = array(gettext("Client"), false, "vpn_openvpn_client.php");
$tab_array[] = array(gettext("Client Specific Overrides"), false, "vpn_openvpn_csc.php");
$tab_array[] = array(gettext("Wizards"), false, "wizard.php?xml=openvpn_wizard.xml");
add_package_tabs("OpenVPN", $tab_array);
display_top_tabs($tab_array);

$cfg = config_get_path($cfg_path, []);

$form = new Form("Save as default");

$section = new Form_Section('OpenVPN Server');

$serverlist = array();
foreach ($ras_server as $server) {
	$serverlist[$server['index']] = $server['name'];
}

$section->addInput(new Form_Select(
	'server',
	'Remote Access Server',
	$cfg['server'],
	$serverlist
	));

$form->add($section);

$section = new Form_Section('Client Connection Behavior');

$useaddrlist = array(
	"serveraddr" => "Interface IP Address",
	"servermagic" => "Automagic Multi-WAN IPs (port forward targets)",
	"servermagichost" => "Automagic Multi-WAN DDNS Hostnames (port forward targets)",
	"serverhostname" => "Installation hostname"
);

foreach (config_get_path('dyndnses/dyndns', []) as $ddns) {
	if (in_array($ddns['type'], $dyndns_split_domain_types)) {
		$useaddrlist[$ddns["host"] . '.' . $ddns["domainname"]] = $ddns["host"] . '.' . $ddns["domainname"];
	} else {
		$useaddrlist[$ddns["host"]] = $ddns["host"];
	}
}

foreach (config_get_path('dnsupdates/dnsupdate', []) as $ddns) {
	$useaddrlist[$ddns["host"]] = $ddns["host"];
}

$useaddrlist["other"] = "Other";

$section->addInput(new Form_Select(
	'useaddr',
	'Host Name Resolution',
	$cfg['useaddr'],
	$useaddrlist
	));

$section->addInput(new Form_Input(
	'useaddr_hostname',
	'Host Name',
	'text',
	$cfg['useaddr_hostname']
))->setHelp('Enter the hostname or IP address the client will use to connect to this server.');


$section->addInput(new Form_Select(
	'verifyservercn',
	'Verify Server CN',
	$cfg['verifyservercn'],
	array(
		"auto" => "Automatic - Use verify-x509-name where possible",
		"none" => "Do not verify the server CN")
))->setHelp("Optionally verify the server certificate Common Name (CN) when the client connects. ");

$section->addInput(new Form_Checkbox(
	'blockoutsidedns',
	'Block Outside DNS',
	'Block access to DNS servers except across OpenVPN while connected, forcing clients to use only VPN DNS servers.',
	$cfg['blockoutsidedns']
))->setHelp("Requires Windows 10 and OpenVPN 2.3.9 or later. Only Windows 10 is prone to DNS leakage in this way, other clients will ignore the option as they are not affected.");

$section->addInput(new Form_Checkbox(
	'legacy',
	'Legacy Client',
	'Do not include OpenVPN 2.5 and later settings in the client configuration.',
	$cfg['legacy']
))->setHelp("When using an older client (OpenVPN 2.4.x), check this option to prevent the exporter from placing known-incompatible settings into the client configuration.");

$section->addInput(new Form_Checkbox(
	'silent',
	'Silent Installer',
	'Create Windows installer for unattended deploy.',
	$cfg['silent']
))->setHelp("Create a silent Windows installer for unattended deploy; installer must be run with elevated permissions. Since this installer is not signed, you may need special software to deploy it correctly.");

$section->addInput(new Form_Select(
	'bindmode',
	'Bind Mode',
	$cfg['bindmode'],
	array(
		"nobind" => "Do not bind to the local port",
		"lport0" => "Use a random local source port",
		"bind" => "Bind to the default OpenVPN port")
))->setHelp("If OpenVPN client binds to the default OpenVPN port (1194), two clients may not run concurrently.");

$form->add($section);

$section = new Form_Section('Certificate Export Options');

$section->addInput(new Form_Checkbox(
	'usepkcs11',
	'PKCS#11 Certificate Storage',
	'Use PKCS#11 storage device (cryptographic token, HSM, smart card) instead of local files.',
	$cfg['usepkcs11']
));

$section->addInput(new Form_Input(
	'pkcs11providers',
	'PKCS#11 Providers',
	'text',
	$cfg['pkcs11providers']
))->setHelp('Enter the client local path to the PKCS#11 provider(s) (DLL, module), multiple separated by a space character.');

$section->addInput(new Form_Input(
	'pkcs11id',
	'PKCS#11 ID',
	'text'
))->setHelp('Enter the object\'s ID on the PKCS#11 device.');

$section->addInput(new Form_Checkbox(
	'usetoken',
	'Microsoft Certificate Storage',
	'Use Microsoft Certificate Storage instead of local files.',
	$cfg['usetoken']
));

$section->addInput(new Form_Checkbox(
	'usepass',
	'Password Protect Certificate',
	'Use a password to protect the PKCS#12 file contents or key in Viscosity bundle.',
	$cfg['usepass']
));

$section->addPassword(new Form_Input(
	'pass',
	'Certificate Password',
	'password',
	$cfg['pass']
))->setHelp('Password used to protect the certificate file contents.');

$section->addInput(new Form_Select(
	'p12encryption',
	'PKCS#12 Encryption',
	'high',
	$p12_encryption_levels
))->setHelp('Select the level of encryption to use when exporting a PKCS#12 archive. ' .
		'Encryption support varies by Operating System and program');

$form->add($section);

$section = new Form_Section('Proxy Options');

$section->addInput(new Form_Checkbox(
	'useproxy',
	'Use A Proxy',
	'Use proxy to communicate with the OpenVPN server.',
	$cfg['useproxy']
));

$section->addInput(new Form_Select(
	'useproxytype',
	'Proxy Type',
	$cfg['useproxytype'],
	array(
		"http" => "HTTP",
		"socks" => "SOCKS")
));

$section->addInput(new Form_Input(
	'proxyaddr',
	'Proxy IP Address',
	'text',
	$cfg['proxyaddr']
))->setHelp('Hostname or IP address of proxy server.');

$section->addInput(new Form_Input(
	'proxyport',
	'Proxy Port',
	'text',
	$cfg['proxyport']
))->setHelp('Port where proxy server is listening.');

$section->addInput(new Form_Select(
	'useproxypass',
	'Proxy Authentication',
	$cfg['useproxypass'],
	array(
		"none" => "None",
		"basic" => "Basic",
		"ntlm" => "NTLM")
))->setHelp('Choose proxy authentication method, if any.');

$section->addInput(new Form_Input(
	'proxyuser',
	'Proxy Username',
	'text',
	$cfg['proxyuser']
))->setHelp('Username for authentication to proxy server.');

$section->addPassword(new Form_Input(
	'proxypass',
	'Proxy Password',
	'password',
	$cfg['proxypass']
))->setHelp('Password for authentication to proxy server.');
$form->add($section);

$section = new Form_Section('Advanced');

	$section->addInput(new Form_Textarea(
		'advancedoptions',
		'Additional configuration options',
		$cfg['advancedoptions']
	))->setHelp('Enter any additional options to add to the OpenVPN client export configuration here, separated by a line break or semicolon.<br/><br/>EXAMPLE: remote-random;');

$form->add($section);

print($form);
?>

<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa-solid fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to search.')?></span>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("OpenVPN Clients")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="users">
				<thead>
					<tr>
						<td width="25%" class="listhdrr"><?=gettext("User")?></td>
						<td width="35%" class="listhdrr"><?=gettext("Certificate Name")?></td>
						<td width="40%" class="listhdrr"><?=gettext("Export")?></td>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
		</div>
	</div>
</div>
<span class="help-block"><?=gettext('Only OpenVPN-compatible user certificates are shown')?>
<br />
<br />
<?= print_info_box(gettext("If a client is missing from the list it is likely due to a CA mismatch between the OpenVPN server instance and the client certificate, the client certificate does not exist on this firewall, or a user certificate is not associated with a user when local database authentication is enabled." .
"<br /><br />" .
"Clients using OpenSSL 3.0 may not work with older or weaker ciphers and hashes, such as SHA1, including when those were used to sign CA and certificate entries." .
"<br /><br />" .
"OpenVPN 2.4.8+ requires Windows 7 or later"), 'info', false); ?>

Links to OpenVPN clients for various platforms:<br />
<br />
<a href="http://openvpn.net/index.php/open-source/downloads.html"><?= gettext("OpenVPN Community Client") ?></a> - <?=gettext("Binaries for Windows, Source for other platforms. Packaged above in the Windows Installers")?>
<br/><a href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn"><?= gettext("OpenVPN For Android") ?></a> - <?=gettext("Recommended client for Android")?>
<br/><?= gettext("OpenVPN Connect") ?>: <a href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn"><?=gettext("Android (Google Play)")?></a> or <a href="https://itunes.apple.com/us/app/openvpn-connect/id590379981"><?=gettext("iOS (App Store)")?></a> - <?= gettext("Recommended client for iOS") ?>
<br/><a href="https://www.sparklabs.com/viscosity/"><?= gettext("Viscosity") ?></a> - <?= gettext("Recommended commercial client for Mac OS X and Windows") ?>
<br/><a href="https://tunnelblick.net"><?= gettext("Tunnelblick") ?></a> - <?= gettext("Free client for OS X") ?>
<br/><a href="https://community.openvpn.net/openvpn/wiki/OpenvpnSoftwareRepos"><?= gettext("Using the Latest OpenVPN on Linux Distros") ?></a> - <?= gettext("Install OpenVPN using the OpenVPN apt repositories to get the latest version, rather than one included with distributions.") ?>

<script type="text/javascript">
//<![CDATA[
var viscosityAvailable = false;

var servers = new Array();
<?php
foreach ($ras_server as $sindex => $server): ?>
servers[<?=$sindex?>] = new Array();
servers[<?=$sindex?>][0] = '<?=$server['index']?>';
servers[<?=$sindex?>][1] = new Array();
servers[<?=$sindex?>][2] = '<?=$server['mode']?>';
servers[<?=$sindex?>][3] = new Array();
servers[<?=$sindex?>][4] = '<?=$server['authmode']?>';
<?php
	$c=0;
	foreach ($server['users'] as $uindex => $user): ?>
<?php		if (!$server['crlref'] || !is_cert_revoked($user['cert'], $server['crlref'])): ?>
servers[<?=$sindex?>][1][<?=$c?>] = new Array();
servers[<?=$sindex?>][1][<?=$c?>][0] = '<?=$user['uindex']?>';
servers[<?=$sindex?>][1][<?=$c?>][1] = '<?=$user['cindex']?>';
servers[<?=$sindex?>][1][<?=$c?>][2] = '<?=$user['name']?>';
servers[<?=$sindex?>][1][<?=$c?>][3] = '<?=str_replace("'", "\\'", $user['certname'])?>';
<?php
			$c++;
		endif;
	endforeach;
	$c=0;
	foreach ($server['certs'] as $cert): ?>
<?php
		if (!$server['crlref'] || !is_cert_revoked(config_get_path("cert/{$cert['cindex']}"), $server['crlref'])): ?>
servers[<?=$sindex?>][3][<?=$c?>] = new Array();
servers[<?=$sindex?>][3][<?=$c?>][0] = '<?=$cert['cindex']?>';
servers[<?=$sindex?>][3][<?=$c?>][1] = '<?=str_replace("'", "\\'", $cert['certname'])?>';
<?php
			$c++;
		endif;
	endforeach;
endforeach;
?>

serverdefaults = <?=json_encode(config_get_path('installedpackages/vpn_openvpn_export/serverconfig/item', []))?>;

function make_form_variable(varname, varvalue) {
	var exportinput = document.createElement("input");
	exportinput.type = "hidden";
	exportinput.name = varname;
	exportinput.value = varvalue;
	return exportinput;
}

function download_begin(act, i, j) {
	var index = document.getElementById("server").value;
	var users = servers[index][1];
	var certs = servers[index][3];
	var useaddr;

	var advancedoptions;

	if (document.getElementById("useaddr").value == "other") {
		if (document.getElementById("useaddr_hostname").value == "") {
			alert("Please specify an IP address or hostname.");
			return;
		}
		useaddr = document.getElementById("useaddr_hostname").value;
	} else {
		useaddr = document.getElementById("useaddr").value;
	}

	advancedoptions = document.getElementById("advancedoptions").value;

	var verifyservercn;
	verifyservercn = document.getElementById("verifyservercn").value;

	var blockoutsidedns = 0;
	if (document.getElementById("blockoutsidedns").checked) {
		blockoutsidedns = 1;
	}
	var legacy = 0;
	if (document.getElementById("legacy").checked) {
		legacy = 1;
	}
	var silent = 0;
	if (document.getElementById("silent").checked) {
		silent = 1;
	}

	var bindmode = 0;
	bindmode = document.getElementById("bindmode").value;

	var usetoken = 0;
	if (document.getElementById("usetoken").checked) {
		usetoken = 1;
	}
	var usepkcs11 = 0;
	if (document.getElementById("usepkcs11").checked) {
		usepkcs11 = 1;
	}
	var silent = 0;
	if (document.getElementById("silent").checked) {
		silent = 1;
	}
	var pkcs11providers = document.getElementById("pkcs11providers").value;
	var pkcs11id = document.getElementById("pkcs11id").value;
	var usepass = 0;
	if (document.getElementById("usepass").checked) {
		usepass = 1;
	}

	var pass = document.getElementById("pass").value;
	var pass_confirm = document.getElementById("pass_confirm").value;
	if (usepass && (act.substring(0, 4) == "inst")) {
		if (!pass || !pass_confirm) {
			alert("The password or confirm field is empty");
			return;
		}
		if (pass != pass_confirm) {
			alert("The password and confirm fields must match");
			return;
		}
	}

	var p12encryption = document.getElementById("p12encryption").value;

	var useproxy = 0;
	var useproxypass = 0;
	if (document.getElementById("useproxy").checked) {
		useproxy = 1;
	}

	var proxyaddr = document.getElementById("proxyaddr").value;
	var proxyport = document.getElementById("proxyport").value;
	if (useproxy) {
		if (!proxyaddr || !proxyport) {
			alert("The proxy ip and port cannot be empty");
			return;
		}

		if (document.getElementById("useproxypass").value != 'none') {
			useproxypass = 1;
		}

		var proxytype = document.getElementById("useproxytype").value;

		var proxyauth = document.getElementById("useproxypass").value;
		var proxyuser = document.getElementById("proxyuser").value;
		var proxypass = document.getElementById("proxypass").value;
		var proxypass_confirm = document.getElementById("proxypass_confirm").value;
		if (useproxypass) {
			if (!proxyuser) {
				alert("Please fill the proxy username and password.");
				return;
			}
			if (!proxypass || !proxypass_confirm) {
				alert("The proxy password or confirm field is empty");
				return;
			}
			if (proxypass != proxypass_confirm) {
				alert("The proxy password and confirm fields must match");
				return;
			}
		}
	}

	var exportform = document.createElement("form");
	exportform.method = "POST";
	exportform.action = "/vpn_openvpn_export.php";
	exportform.target = "_self";
	exportform.style.display = "none";

	exportform.appendChild(make_form_variable("act", act));
	exportform.appendChild(make_form_variable("srvid", servers[index][0]));
	if (users[i]) {
		exportform.appendChild(make_form_variable("usrid", users[i][0]));
		exportform.appendChild(make_form_variable("crtid", users[i][1]));
	}
	if (certs[j]) {
		exportform.appendChild(make_form_variable("usrid", ""));
		exportform.appendChild(make_form_variable("crtid", certs[j][0]));
	}
	exportform.appendChild(make_form_variable("useaddr", useaddr));
	exportform.appendChild(make_form_variable("verifyservercn", verifyservercn));
	exportform.appendChild(make_form_variable("blockoutsidedns", blockoutsidedns));
	exportform.appendChild(make_form_variable("legacy", legacy));
	exportform.appendChild(make_form_variable("silent", silent));
	exportform.appendChild(make_form_variable("bindmode", bindmode));
	exportform.appendChild(make_form_variable("usetoken", usetoken));
	exportform.appendChild(make_form_variable("usepkcs11", usepkcs11));
	exportform.appendChild(make_form_variable("pkcs11providers", pkcs11providers));
	exportform.appendChild(make_form_variable("pkcs11id", pkcs11id));
	if (usepass) {
		exportform.appendChild(make_form_variable("password", pass));
	}
	exportform.appendChild(make_form_variable("p12encryption", p12encryption));
	if (useproxy) {
		exportform.appendChild(make_form_variable("proxy_type", proxytype));
		exportform.appendChild(make_form_variable("proxy_addr", proxyaddr));
		exportform.appendChild(make_form_variable("proxy_port", proxyport));
		exportform.appendChild(make_form_variable("proxy_authtype", proxyauth));
		if (useproxypass) {
			exportform.appendChild(make_form_variable("proxy_user", proxyuser));
			exportform.appendChild(make_form_variable("proxy_password", proxypass));
		}
	}
	exportform.appendChild(make_form_variable("advancedoptions", advancedoptions));

	exportform.appendChild(make_form_variable(csrfMagicName, csrfMagicToken));
	document.body.appendChild(exportform);
	exportform.submit();
}

function server_changed() {

	var table = document.getElementById("users");
	table = table.tBodies[0];

	while (table.rows.length > 0 ) {
		table.deleteRow(0);
	}

	function setFieldValue(field, value) {
		checkboxes = $("input[type=checkbox]#"+field);
		checkboxes.prop('checked', value == 'yes').trigger("change");

		inputboxes = $("input[type!=checkbox]#"+field);
		inputboxes.val(value);

		selectboxes = $("select#"+field);
		selectboxes.val(value);

		textareaboxes = $("textarea#"+field);
		textareaboxes.val(value);
	}

	var index = document.getElementById("server").value;
	for(i = 0; i < serverdefaults.length; i++) {
		if (serverdefaults[i]['server'] !== index) {
			continue;
		}
		fields = serverdefaults[i];
		fieldnames = Object.getOwnPropertyNames(fields);
		for (fieldnr = 0; fieldnr < fieldnames.length; fieldnr++) {
			fieldname = fieldnames[fieldnr];
			setFieldValue(fieldname, fields[fieldname]);
		}
		setFieldValue('pass_confirm', fields['pass']);
		setFieldValue('proxypass_confirm', fields['proxypass']);
		break;
	}


	var users = servers[index][1];
	var certs = servers[index][3];
	for (i = 0; i < users.length; i++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		cell0.innerHTML = users[i][2];
		cell1.className = "listr";
		cell1.innerHTML = users[i][3];
		cell2.className = "listr";
		cell2.innerHTML = "- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Most Clients<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Android<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineconnect\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "<br\/>- Bundled Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Config File Only<\/a>";
		cell2.innerHTML += "<br\/>- Current Windows Installers (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-current\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-current\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "<br\/>- Previous Windows Installers (<?=$previous_openvpn_version . '-Ix' . $previous_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-previous\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-previous\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "<br\/>- Legacy Windows Installers (<?=$legacy_openvpn_version . '-Ix' . $legacy_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win10\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 10/2016/2019<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win7\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 7/8/8.1/2012r2<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\"," + i + ", -1)' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Inline Config<\/a>";
	}
	for (j = 0; j < certs.length; j++) {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		if (servers[index][2] == "server_tls") {
			cell0.innerHTML = "Certificate (SSL/TLS, no Auth)";
		} else {
			cell0.innerHTML = "Certificate with External Auth";
		}
		cell1.className = "listr";
		cell1.innerHTML = certs[j][1];
		cell2.className = "listr";
		cell2.innerHTML = "- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Most Clients<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Android<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineconnect\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "<br\/>- Bundled Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Config File Only<\/a>";
		cell2.innerHTML += "<br\/>- Current Windows Installer (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-current\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-current\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<br\/>- Previous Windows Installer (<?=$previous_openvpn_version . '-Ix' . $previous_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-previous\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-previous\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<br\/>- Legacy Windows Installers (<?=$legacy_openvpn_version . '-Ix' . $legacy_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win10\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 10/2016/2019<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win7\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 7/8/8.1/2012r2<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Inline Config<\/a>";
		if (servers[index][2] == "server_tls") {
			cell2.innerHTML += "<br\/>- Yealink SIP Handsets:<br\/>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t28\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> T28<\/a>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t38g\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> T38G (1)<\/a>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_yealink_t38g2\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> T38G (2) / V83<\/a>";
			cell2.innerHTML += "<br\/>- Snom SIP Handsets:<br\/>";
			cell2.innerHTML += "&nbsp;&nbsp; ";
			cell2.innerHTML += "<a href='javascript:download_begin(\"conf_snom\", -1," + j + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> SNOM<\/a>";
		}
	}
	if (servers[index][2] == 'server_user') {
		var row = table.insertRow(table.rows.length);
		var cell0 = row.insertCell(0);
		var cell1 = row.insertCell(1);
		var cell2 = row.insertCell(2);
		cell0.className = "listlr";
		cell0.innerHTML = "Authentication Only (No Cert)";
		cell1.className = "listr";
		cell1.innerHTML = "none";
		cell2.className = "listr";
		cell2.innerHTML = "- Inline Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinline\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Most Clients<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinedroid\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Android<\a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlineconnect\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> OpenVPN Connect (iOS/Android)<\/a>";
		cell2.innerHTML += "<br\/>- Bundled Configurations:<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confzip\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Archive<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"conf\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Config File Only<\/a>";
		cell2.innerHTML += "<br\/>- Current Windows Installer (<?=$current_openvpn_version . '-Ix' . $current_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-current\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-current\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "<br\/>- Previous Windows Installer (<?=$previous_openvpn_version . '-Ix' . $previous_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x64-previous\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 64-bit<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-x86-previous\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 32-bit<\/a>";
		cell2.innerHTML += "<br\/>- Legacy Windows Installers (<?=$legacy_openvpn_version . '-Ix' . $legacy_openvpn_version_rev?>):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win10\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 10/2016/2019<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"inst-Win7\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> 7/8/8.1/2012r2<\/a>";
		cell2.innerHTML += "<br\/>- Viscosity (Mac OS X and Windows):<br\/>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"visc\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Bundle<\/a>";
		cell2.innerHTML += "&nbsp;&nbsp; ";
		cell2.innerHTML += "<a href='javascript:download_begin(\"confinlinevisc\"," + i + ")' class=\"btn btn-sm btn-primary\"><i class=\"fa-solid fa-download\"></i> Viscosity Inline Config<\/a>";
	}
}

function useaddr_changed() {
	if ($('#useaddr').val() == "other") {
		hideInput('useaddr_hostname', false);
	} else {
		hideInput('useaddr_hostname', true);
	}
}

function usepkcs11_changed() {
	if ($('#usepkcs11').prop('checked')) {
		hideInput('pkcs11id', false);
		hideInput('pkcs11providers', false);
	} else {
		hideInput('pkcs11id', true);
		hideInput('pkcs11providers', true);
	}
}

function usepass_changed() {
	if ($('#usepass').prop('checked')) {
		hideInput('pass', false);
		hideInput('pass_confirm', false);
	} else {
		hideInput('pass', true);
		hideInput('pass_confirm', true);
	}
}

function useproxy_changed() {
	if ($('#useproxy').prop('checked')) {
		hideInput('useproxytype', false);
		hideInput('proxyaddr', false);
		hideInput('proxyport', false);
		hideInput('useproxypass', false);
	} else {
		hideInput('useproxytype', true);
		hideInput('proxyaddr', true);
		hideInput('proxyport', true);
		hideInput('useproxypass', true);
		hideInput('proxyuser', true);
		hideInput('proxypass', true);
		hideInput('proxypass_confirm', true);
	}
	if ($('#useproxy').prop('checked') && ($('#useproxypass').val() != 'none')) {
		hideInput('proxyuser', false);
		hideInput('proxypass', false);
		hideInput('proxypass_confirm', false);
	} else {
		hideInput('proxyuser', true);
		hideInput('proxypass', true);
		hideInput('proxypass_confirm', true);
	}
}

events.push(function(){
	// ---------- OnChange handlers ---------------------------------------------------------

	$('#server').on('change', function() {
		server_changed();
	});
	$('#useaddr').on('change', function() {
		useaddr_changed();
	});
	$('#usepkcs11').on('change', function() {
		usepkcs11_changed();
	});
	$('#usepass').on('change', function() {
		usepass_changed();
	});
	$('#useproxy').on('change', function() {
		useproxy_changed();
	});
	$('#useproxypass').on('change', function() {
		useproxy_changed();
	});

	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the package name and/or description
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				username = $tds.eq(0).text().trim().toLowerCase(),
				certname = $tds.eq(1).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(username)) && !(regexp.test(certname))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
	    if (event.keyCode == 13) {
	        $("#btnsearch").get(0).click();
	    }
	});

	// ---------- On initial page load ------------------------------------------------------------

	server_changed();
	useaddr_changed();
	usepkcs11_changed();
	usepass_changed();
	useproxy_changed();
});
//]]>
</script>

<?php
include("foot.inc");
