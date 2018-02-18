<?php
/**
 * Rave by Flutterwave Payment Gateway Module
 *
 * This Payment Gateway module allows you to integrate Rave payment solutions with the
 * WHMCS platform.
 *
 * For more information, please refer to the online documentation.
 *
 * @author Oluwole Adebiyi <flamekeed@gmail.com>
 *
 * @copyright Copyright (c) Oluwole Adebiyi 2017
 */



if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function rave_MetaData()
{
    return array(
        'DisplayName' => 'Rave by Flutterwave',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function rave_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Rave by Flutterwave',
        ),

        'cBname' => array(
            'FriendlyName' => 'Company/Business Name',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter company/business name here',
        ),

        'cBdescription' => array(
            'FriendlyName' => 'Company/Business Description',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter company/business description here',
        ),

        'whmcsLogo' => array(
            'FriendlyName' => 'Logo',
            'Type' => 'text',
            'Size' => '80',
            'Default' => '',
            'Description' => 'Enter the link to your logo, square size',
        ),

        'paymentMethod' => array(
            'FriendlyName' => 'Payment Method',
            'Type' => 'radio',
            'Options' => 'card,account,both',
            'Description' => 'Choose your payment method!',
        ),

        'PBFPubKey' => array(
            'FriendlyName' => 'Public Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter public key here',
        ),

        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),

        'payButtonText' => array(
            'FriendlyName' => 'Pay Button Text',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'Pay Now',
            'Description' => 'Text to display on your payment button',
        ),

        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
        'gatewayLogs' => array(
            'FriendlyName' => 'Gateway logs',
            'Type' => 'yesno',
            'Description' => 'Select to enable gateway logs',
            'Default' => '0'
        ),
    );
}

function rave_link($params)
{
    $stagingUrl = 'https://rave-api-v2.herokuapp.com';
    $liveUrl = 'https://api.ravepay.co';
    $isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
 
    
    $PBFPubKey = $params['PBFPubKey'];
    $secretKey = $params['secretKey'];
    $payButtonText = $params['payButtonText'];
    $cBname = $params['cBname'];
    $cBdescription = $params['cBdescription'];
    $whmcsLink = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] .substr(str_replace('/admin/', '/', $_SERVER['REQUEST_URI']), 0, strrpos($_SERVER['REQUEST_URI'], '/'));
    $whmcsLogo = $params['whmcsLogo'];
    $paymentMethod = $params['paymentMethod'];


    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    $strippedAmount = $amount + 0;


    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    $txref = $invoiceId . '_' .time();
    
    $country = 'NG';
    switch($currencyCode) {
    case 'KES':
      $country = 'KE';
      break;
    case 'GHS':
      $country = 'GH';
      break;
    default:
      $country = 'NG';
      break;
    }

    $postfields = array();
    $postfields['PBFPubKey'] = $PBFPubKey;
    $postfields['customer_email'] = $email;
    $postfields['customer_firstname'] = $firstname;
    $postfields['custom_logo'] = $whmcsLogo;
    $postfields['customer_lastname'] = $lastname;
    $postfields['custom_description'] = $cBdescription;
    $postfields['custom_title'] = $cBname;
    $postfields['customer_phone'] = $phone;
    $postfields['country'] = $country;
    $postfields['redirect_url'] = $whmcsLink . '/modules/gateways/callback/rave.php';
    $postfields['txref'] = $invoiceId . '_' .time();
    $postfields['payment_method'] = $paymentMethod;
    $postfields['amount'] = $strippedAmount;
    $postfields['currency'] = $currencyCode;
    $postfields['hosted_payment'] = 1;

    ksort($postfields);
    $stringToHash ="";
    foreach ($postfields as $key => $val) {
        $stringToHash .= $val;
    }


    $stringToHash .= $secretKey;

    $hashedValue = hash('sha256', $stringToHash);

    $env = "staging";

    $baseUrl = $stagingUrl;

    if ($params['testMode'] != 'on') {
        $baseUrl = $liveUrl;
    }

    $meta = array();

    array_push($meta, array('metaname' => 'invoiceID', 'metavalue' => $invoiceId));
    array_push($meta, array('metaname' => 'amount', 'metavalue' => $amount));

    $transactionData = array_merge($postfields, array('integrity_hash' => $hashedValue), array('meta' => $meta));
    $json = json_encode($transactionData);

    $htmlOutput = "<form onsubmit='event.preventDefault(); pay();'>
      <button type='submit' class='btn btn-primary' style='cursor:pointer;' value='".$payButtonText."' id='ravepaybutton'>".$payButtonText."</button>
    </form>
    <script type='text/javascript' src='" . $baseUrl . "/flwv3-pug/getpaidx/api/flwpbf-inline.js'></script>
    <script>
    function pay() {
    var data = JSON.parse('" . json_encode($transactionData = array_merge($postfields, array('integrity_hash' => $hashedValue))) . "');
    getpaidSetup(data);}
    </script>
    ";

    return $htmlOutput;
}