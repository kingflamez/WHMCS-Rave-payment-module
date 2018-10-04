<?php

/**
 * Rave Webhook File
 *
 *
 * For more information, please refer to the online documentation.
 *
 * @author Oluwole Adebiyi <flamekeed@gmail.com>
 *
 * @copyright Copyright (c) Oluwole Adebiyi 2018
 */
// Require Database Capsule for query
use WHMCS\Database\Capsule;
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables('rave');
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$isSSL = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
$whmcsLink = 'http' . ($isSSL ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . substr(str_replace('/admin/', '/', $_SERVER['REQUEST_URI']), 0, strrpos($_SERVER['REQUEST_URI'], '/'));

// Retrieve the request's body
$body = @file_get_contents("php://input");

// retrieve the signature sent in the reques header's.
$signature = (isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '');
// Store the same signature on your server as an env variable and check against what was sent in the headers
$local_signature = $gatewayParams['webhookHash'];

/* It is a good idea to log all events received. Add code *
 * here to log the signature and body to db or file       */
if ($signature== $local_signature) {
    http_response_code(200); // PHP 5.4 or greater
    // parse event (which is json string) as object
    // Give value to your customer but don't give any output
    // Remember that this is a call from rave's servers and 
    // Your customer is not seeing the response here at all
    $response = json_decode($body);
    $invoiceId = explode('_', $response->txRef);
    $invoiceId = $invoiceId[0];
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    /**
     * Check Callback Transaction ID, checks for any existing transactions.
     */
    checkCbTransID($transactionId);

    /**
     * Gets the amount from the server
     */
    $cashquery = Capsule::table('tblinvoices')
        ->where('id', '=', $invoiceId)->first();
    $cash = $cashquery->total;

    /**
     * Gets the currency ID and Code from the server
     */
    $result = select_query("tblclients", "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code", array("tblinvoices.id" => $invoiceId), "", "", "", "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency");
    $data = mysql_fetch_array($result);
    $invoice_currency_id = $data['currency'];
    $currency = $data['code'];
    $amount = $cash + 0;

    if (($response->amount == $amount) && ($response->currency == $currency)) {
        addInvoicePayment(
            $invoiceId,
            $response->txRef,
            $amount,
            null,
            'rave'
        );
    }
    
    exit;
}