<?php
/**
 * zibal online gateway for whmcs 
 *
 * @website		zibal.ir
 * @copyright	(c) 2018 - Zibal Team
 * @author	zamanzadeh@zibal.ir
 */
require_once(__DIR__ . '/../../../init.php');
require_once(__DIR__ . '/../../../includes/gatewayfunctions.php');
require_once(__DIR__ . '/../../../includes/invoicefunctions.php');

$gatewayParams = getGatewayVariables('zibal');

if ($gatewayParams['type'] == FALSE) {

	die('Module Not Activated');
}

$failure = FALSE;

/**
 * connects to zibal's rest api
 * @param $path
 * @param $parameters
 * @return stdClass
 */
function postToZibal($path, $parameters)
{
    $url = 'https://gateway.zibal.ir/'.$path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response  = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

if(extension_loaded('curl'))
{
	$apiKey        = isset($_POST['api_key']) ? $_POST['api_key'] : NULL;
		$zibalDirect        = isset($_POST['zibalDirect']) ? $_POST['zibalDirect'] : 'no';

	$paymentAmount = isset($_POST['paymentAmount']) ? $_POST['paymentAmount'] : NULL;
	$callbackUrl   = isset($_POST['callback_url']) ? $_POST['callback_url'] : NULL;
	$invoiceId     = isset($_POST['invoice_id']) ? $_POST['invoice_id'] : NULL;


$parameters = array(
    "merchant"=> $apiKey,//required
    "callbackUrl"=> urlencode($callbackUrl),//required
    "amount"=> 1000,//required

    "orderId"=> $invoiceId,//optional

);

$result = postToZibal('request', $parameters);


	if ($result && isset($result->result) && $result->result == 100) {

		$gatewayUrl = 'https://gateway.zibal.ir/start/' . $result->trackId;
		
		if($zibalDirect == 'yes')
            $gatewayUrl.='/direct';

		header('Location: ' . $gatewayUrl);
		exit;

	} else {

		$failure      = TRUE;
		$errorMessage = 'در ارتباط با وب سرویس زیبال خطایی رخ داده است';

		$errorCode    = isset($result->result) ? $result->result : 'Send';
		$errorMessage = isset($result->message) ? $result->message : $errorMessage;

		logTransaction($gatewayParams['name'], array(

			'Code'    => $errorCode,
			'Message' => $errorMessage,
			'Invoice' => $invoiceId

		), 'Failure');
	}

} else {

	$failure      = TRUE;
	$errorMessage = 'تابع cURL در سرور فعال نمی باشد';

	logTransaction($gatewayParams['name'], array(

		'Code'    => 'cURL',
		'Message' => $errorMessage

	), 'Failure');
}

if ($failure) {

	if (isset($invoiceId) && $invoiceId) {

		header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $invoiceId);
		exit;

	} else {

		header('Location: ' . $gatewayParams['systemurl'] . '/clientarea.php?action=invoices');
		exit;
	}
}

