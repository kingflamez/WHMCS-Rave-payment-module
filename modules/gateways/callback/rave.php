<?php
/**
 * Rave Callback File
 *
 *
 * For more information, please refer to the online documentation.
 *
 * @author Oluwole Adebiyi <flamekeed@gmail.com>
 *
 * @copyright Copyright (c) Oluwole Adebiyi 2017
 */
// Require Database Capsule for query
use WHMCS\Database\Capsule;
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');
// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$invoiceId = $_GET["inv"];
$transactionId = $_GET["txre"];
$paymentAmount = $_GET["a"];
$success = false;


if ($gatewayParams['testMode'] == 'on') {
    $apiLink = "http://flw-pms-dev.eu-west-1.elasticbeanstalk.com/";
} else {
    $apiLink = "https://api.ravepay.co/";
}

/**
 * Validate Callback Invoice ID.
 */

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
/**
 * Check Callback Transaction ID.
 */
checkCbTransID($transactionId);
/**
 * Gets the amount from the server
 */
$cashquery = Capsule::table('tblinvoices')
   ->where('id', '=', $invoiceId)->first();
    $cash = $cashquery->subtotal;

/**
 * Gets the currency ID and Code from the server
 */
$result = select_query("tblclients", "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code", array("tblinvoices.id" => $invoiceId), "", "", "", "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency");
$data = mysql_fetch_array($result);
$invoice_currency_id = $data['currency'];
$invoice_currency_code = $data['code'];


/**
 * Converts to the Currency set if on
 */
if ($gatewayParams['convertto']) {
    $converto_amount = convertCurrency($amount, $gatewayParams['convertto'], $invoice_currency_id);
    $cash = format_as_currency($converto_amount);
}
//Removes trailing zeros
$money = $cash + 0;

//Verify Transaction
if (isset($_GET['flw_ref'])) {
    $ref = $_GET['flw_ref'];
    $amount = $money; //Correct Amount from Server
    $currency = $invoice_currency_code; //Correct Currency from Server

    $query = array(
        "SECKEY" => $gatewayParams['secretKey'],
        "flw_ref" => $ref,
        "normalize" => "1"
    );

    $data_string = json_encode($query);
    $ch = curl_init($apiLink.'/flwv3-pug/getpaidx/api/verify');                                                                      
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                              
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    curl_close($ch);

    $resp = json_decode($response, true);
    $chargeResponse = $resp['data']['flwMeta']['chargeResponse'];
    $chargeAmount = $resp['data']['amount'];
    $chargeCurrency = $resp['data']['transaction_currency'];

    //If successful
    if (($chargeResponse == "00" || $chargeResponse == "0") && ($chargeAmount == $amount)  && ($chargeCurrency == $currency)) {
      addInvoicePayment(
        $invoiceId,
        $_GET['flw_ref'],
        $paymentAmount,
        "",
        $gatewayModuleName
    );

      //WHMCS Log transaction
      logTransaction($gatewayParams['name'], $_GET, "Successful payment of #{$invoiceId}");
      //Redirect
    $invoice_url = $gatewayParams['whmcsLink'] .
        'viewinvoice.php?id='.
        rawurlencode($invoiceId);

    header('Location: '.$invoice_url);
    }

    //If not successful
    else{
        //WHMCS Log transaction
        logTransaction($gatewayParams['name'], $_GET, "Unsuccessful payment of #{$invoiceId}");

        $invoice_url = $gatewayParams['whmcsLink'] .
        'viewinvoice.php?id='.
        rawurlencode($invoiceId);

        echo '<!DOCTYPE html>
    <html lang="">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet">
            <title>Failed Transaction</title>
    
            <!-- Bootstrap CSS -->
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    
            <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
            <!-- WARNING: Respond.js doesn\'t work if you view the page via file:// -->
            <!--[if lt IE 9]>
                <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.2/html5shiv.min.js"></script>
                <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
            <![endif]-->
            <style>
            html, body{
                font-family: \'Raleway\', sans-serif;
            }
            h1{
                font-weight: bold;
                color: #f00;
            }
            </style>
        </head>
        <body class="text-center" style="padding-top=20%;">
            <h1>Failed Transaction</h1>
            <p>Error code - '.$chargeResponse.'</p>
            <a class="btn btn-primary" href="'.$invoice_url.'">Back to Invoice</a>
    
            <!-- jQuery -->
            <script src="//code.jquery.com/jquery.js"></script>
            <!-- Bootstrap JavaScript -->
            <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
            <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
             <script src="Hello World"></script>
        </body>
    </html>';
    die();
    }

}

