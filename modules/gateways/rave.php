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
        'DisplayName' => 'Rave by Flutterwave Payment Gateway',
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
            'Value' => 'Rave by Flutterwave Payment Gateway',
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

        'whmcsLink' => array(
            'FriendlyName' => 'WHMCS Link',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter whmcs website link here',
        ),

        'whmcsLogo' => array(
            'FriendlyName' => 'Logo',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter the link to your logo, square size',
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
    );
}

function rave_link($params)
{

    // Gateway Configuration Parameters
    if ($params['testMode'] == 'on') {
        $apiLink = "http://flw-pms-dev.eu-west-1.elasticbeanstalk.com/";
    } else {
        $apiLink = "https://api.ravepay.co/";
    }
    $PBFPubKey = $params['PBFPubKey'];
    $secretKey = $params['secretKey'];
    $payButtonText = $params['payButtonText'];
    $cBname = $params['cBname'];
    $cBdescription = $params['cBdescription'];
    $whmcsLink = $params['whmcsLink'];
    $whmcsLogo = $params['whmcsLogo'];

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
    $postfields['txref'] = $invoiceId . '_' .time();
    $postfields['customer_email'] = $email;
    $postfields['customer_firstname'] = $firstname;
    $postfields['custom_logo'] = $whmcsLogo;
    $postfields['customer_lastname'] = $lastname;
    $postfields['custom_description'] = $cBdescription;
    $postfields['custom_title'] = $cBname;
    $postfields['customer_phone'] = $phone;
    $postfields['country'] = $country;
    $postfields['payment_method'] = "both";
    $postfields['amount'] = $strippedAmount;
    $postfields['currency'] = $currencyCode;

    ksort($postfields);
    $stringToHash ="";
    foreach ($postfields as $key => $val) {
        $stringToHash .= $val;
    }

    $stringToHash .= $secretKey;

    $hashedValue = hash('sha256', $stringToHash);

    $htmlOutput = '<form>
      <button type="button" class="btn btn-primary" style="cursor:pointer;" value="'.$payButtonText.'" id="ravepaybutton">'.$payButtonText.'</button>
    </form>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script type="text/javascript" src="'.$apiLink.'flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>
    <script>
         document.addEventListener("DOMContentLoaded", function(event) {
      document.getElementById("ravepaybutton").addEventListener("click", function(e) {

         var flw_ref = "", chargeResponse = "", trxref = "rave-'.$invoiceId.'", PBFKey = "'.$PBFPubKey.'";
        getpaidSetup({';
          foreach ($postfields as $key => $val) {
            if ($key == "amount") {
                $htmlOutput .= $key.": ".$val.",";
            }
            else{
                $htmlOutput .= $key.': "'.$val.'",';
            }
          }
          $htmlOutput .='integrity_hash: "'.$hashedValue.'",
          onclose: function() {
          },
          callback: function(response) {
            flw_ref = response.tx.flwRef; 
            if ( response.tx.chargeResponse == "00" || response.tx.chargeResponse == "0" ) {
              window.location = "'.$whmcsLink.'modules/gateways/callback/rave.php?inv='.$invoiceId.'&a='.$strippedAmount.'&txre='.$txref.'&flw_ref="+flw_ref; 
            } 
            else {
              window.location = "'.$whmcsLink.'modules/gateways/callback/rave.php?inv='.$invoiceId.'&a='.$strippedAmount.'&txre='.$txref.'&flw_ref="+flw_ref; 
            }
          }
          });
        });
      });
    </script>';
    // $htmlOutput = '
    //                 <a class="flwpug_getpaid" data-PBFPubKey="'.$PBFPubKey.'" data-txref="rave-checkout-'.$invoiceId.'" data-amount="'.$amount.'" data-customer_email="'.$email.'" data-currency = "NGN" data-pay_button_text = "'.$payButtonText.'" data-country="NG" data-custom_title = "'.$cBname.'" data-custom_description = "'.$cBdescription.'" data-redirect_url = "'.$whmcsLink.'modules/gateways/callback/rave.php" data-custom_logo = "'.$whmcsLogo.'" data-payment_method = "both" data-integrity_hash="" data-exclude_banks=""></a>   

    //             <script type="text/javascript" src="http://flw-pms-dev.eu-west-1.elasticbeanstalk.com/flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>
    // ';

    return $htmlOutput;
}