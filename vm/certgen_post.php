<pre>
<?php
// Simple example to get started

// This can be run by a cronjob for example (no need to time it exactly,
// just run it often enough). It checks if the already existing certificate
// needs to be renewed before making any connection to the CA

// Require ACMECert
require 'ACMECert/ACMECert.php';
use skoerfgen\ACMECert\ACMECert;

error_reporting(E_ALL);
ini_set('log_errors',1);
ini_set("error_log", "php-error.log");

function call_kas($KasAction, $Params, $CredentialToken) {
	try {
		$SoapRequest = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl');

		echo "Issuing SOAP Request to $KasAction with session $CredentialToken<br>";
		$req = $SoapRequest->KasApi(json_encode(array(
				  'kas_login' => 'w0060714',                // KAS-User
				  'kas_auth_type' => 'session',             // Auth per Sessiontoken
				  'kas_auth_data' => $CredentialToken,      // Auth-Token
				  'kas_action' => $KasAction,      			// API-Funktion
				  'KasRequestParams' => $Params          	// Parameter an die API-Funktion
				  )));
		return $req;
	}

	// Fehler abfangen und ausgeben
	catch (SoapFault $fault) {
		echo "Fehler beim KAS-API-Call $KasAction.<br>";
		echo " - Fehlernummer: {$fault->faultcode}<br>";
		echo " - Fehlermeldung: {$fault->faultstring}<br>";
		echo " - Verursacher: {$fault->faultactor}<br>";
		echo " - Details: {$fault->detail}";
		exit();
	}
};

// Logon to KAS-API
echo "Generating KAS session token<br>";
$kas_user = 'w0060714';
$LogonParams=array(
	'kas_login' => $kas_user,              	// KAS-User
	'kas_auth_type' => 'plain',             // Auth per Sessiontoken
	'kas_auth_data' => $_POST["password"],  // Auth-Token
	'session_lifetime' => 600,              // Gültigkeit des Tokens in Sekunden
	'session_update_lifetime' => 'Y',       // Y|N: bei Y verlängert sich die Session mit jedem Request
	'session_2fa' => $_POST["otp"]               // optional: falls aktiviert, die One-Time-Pin für die 2FA
);

try {
	$SoapLogon = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl');
	$CredentialToken = $SoapLogon->KasAuth(json_encode($LogonParams));
	echo "KAS session token generated: $CredentialToken <br>";
}

// Fehler abfangen und ausgeben
catch (SoapFault $fault) {
	echo "Fehler beim KAS-Login.<br>";
	echo " - Fehlernummer: {$fault->faultcode}<br>";
	echo " - Fehlermeldung: {$fault->faultstring}<br>";
	echo " - Verursacher: {$fault->faultactor}<br>";
	echo " - Details: {$fault->detail}";
	exit();
}

// Choose Certificate Authority (CA)

// Let's Encrypt Staging CA
//$ac=new ACMECert('https://acme-staging-v02.api.letsencrypt.org/directory');
//echo "Open Let's Encrypt Staging CA<br>";

// Let's Encrypt Live CA
$ac=new ACMECert('https://acme-v02.api.letsencrypt.org/directory');
echo "Open Let's Encrypt Live CA<br>";

$ac->setLogger(function($txt){
	echo "[[ACME]] ".$txt."<br>";
});

// Check if the previous certificate needs to be renewed (if there is one already)
if (file_exists(__DIR__.'/certs/vulnerads.de.fullchain.pem')){
  $days=$ac->getRemainingDays('file://'.__DIR__.'/certs/vulnerads.de.fullchain.pem');
  if ($days>80) { // renew 30 days before expiry
    echo 'Certificate for vulnerads.de is still good (>80 days remaining), exiting..<br>';
    exit();
  } 
}// Check if the previous certificate needs to be renewed (if there is one already)
if (file_exists(__DIR__.'/certs/attacat.de.fullchain.pem')){
  $days=$ac->getRemainingDays('file://'.__DIR__.'/certs/attacat.de.fullchain.pem');
  if ($days>80) { // renew 30 days before expiry
    echo 'Certificate for attacat.de is still good (>80 days remaining), exiting..<br>';
    exit();
  } 
}

// Check if account_key.pem exists. If not generate new key and
// register it with the CA and save it.
if (!file_exists(__DIR__.'/account_key.pem')){
  
  // Generate RSA Private Key
  echo 'Generating RSA Account Key<br>';
  $key=$ac->generateRSAKey(2048);
  
  // load new key into ACMECert
  echo 'Loading RSA Account Key<br>';
  $ac->loadAccountKey($key);
  
  // Register Account Key with CA
  echo 'Registering RSA Account Key<br>';
  $ac->register(true,'mail@sosec.de');
  
  // Registration succeeded, save key to account_key.pem
  echo 'Saving successfully registered RSA Account Key to disk<br>';
  file_put_contents(__DIR__.'/account_key.pem',$key); 
}else{
  // load existing account key into ACMECert
  echo 'Loading existing RSA Account Key<br>';
  $ac->loadAccountKey('file://'.__DIR__.'/account_key.pem');
}

function get_cert_for_domain($domain, $ac, $CredentialToken) {
	echo "<br>====<br><b>Generating Certificate for $domain</b><br>====<br>";
	// Get Certificate using dns-01 challenge
	$domain_config=array(
	  $domain=>array('challenge'=>'dns-01')
	);

	$handler=function($opts) use ($CredentialToken, $domain) {
		echo 'Create DNS-TXT-Record '.$opts['key'].' with value '.$opts['value'].' for domain '.$domain.'.'.'<br>';
		
		$Params=array(
			'record_type' => 'TXT',
			'record_name' => '_acme-challenge',
			'record_data' => $opts['value'],
			'zone_host' => $domain.'.',
			'record_aux' => '0'
		);
		call_kas('add_dns_settings', $Params, $CredentialToken);


	  return function($opts) use ($CredentialToken, $domain) {
		echo 'Remove DNS-TXT-Record '.$opts['key'].' with value '.$opts['value']."<br>";
		$Params=array(
			'nameserver' => 'ns5.kasserver.com',
			'zone_host' => $domain.'.'
		);
		$req = call_kas('get_dns_settings', $Params, $CredentialToken);
		
		//print_r($req);
		
		echo "RecordID: ".$req["Response"]["ReturnInfo"][0]["record_id"]."<br>";
		
		foreach ($req["Response"]["ReturnInfo"] as $DnsEntry) {
			if ($DnsEntry["record_name"] === "_acme-challenge") {
				echo "Deleting ".$DnsEntry["record_name"]." entry ".$DnsEntry["record_data"]." with record id ".$DnsEntry["record_id"]."<br>";
				$Params=array(
					'record_id' => $DnsEntry["record_id"]
				);
				call_kas('delete_dns_settings', $Params, $CredentialToken);
				sleep(1); //Flood Prevention KAS API
			}
		}
	  };
	};

	// Generate new certificate key
	echo 'Generating certificate RSA keys<br>';
	$private_key=$ac->generateRSAKey(2048);

	// echo "$private_key<br>";

	echo 'Retrieving certificate<br>';
	$fullchain=$ac->getCertificateChain($private_key,$domain_config,$handler);

	//echo $fullchain;
	
	// Success! Save the certificate chain and private key
	echo "Writing fullchain of $domain to disk<br>";
	file_put_contents(__DIR__.'/certs/'.$domain.'.fullchain.pem',$fullchain);
	echo "Writing private key of $domain to disk<br>";
	file_put_contents(__DIR__.'/certs/'.$domain.'.private_key.pem',$private_key);
}

get_cert_for_domain("vulnerads.de", $ac, $CredentialToken);
sleep(1);
get_cert_for_domain("attacat.de", $ac, $CredentialToken);

echo "Creating tarball<br>";
$tarball = "certs.tar";
$pd = new \PharData($tarball);
echo "Adding directory to tarball<br>";
$pd->buildFromDirectory(__DIR__.'/certs');

echo "<br><br>====<br><b>Seems like everything went through smoooooothly.</b><br>====<br>";
echo "</pre?>";