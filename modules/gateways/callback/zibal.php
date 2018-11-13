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

$success = FALSE;

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

if (isset($_POST['success']) && isset($_POST['trackId']) && isset($_POST['orderId']) && isset($_GET['secure'])) {

	$flag = FALSE;

	$success       = @mysql_real_escape_string($_POST['success']);
	$trackId      = @mysql_real_escape_string($_POST['trackId']);
	$orderId = @mysql_real_escape_string($_POST['orderId']);
	$secure       = @mysql_real_escape_string($_GET['secure']);

	$query = @mysql_query("select * from tblinvoices where id = '$orderId' AND status = 'Paid'");

	if (@mysql_num_rows($query) == 1) {
		
		$flag = TRUE;
	}

	if (isset($success) && $success == 1) {

		if ($flag) {

			$errorMessage = 'تایید تراکنش در گذشته با موفقیت انجام شده است';

			logTransaction($gatewayParams['name'], array(

				'Code'    => 'Double Spending',
				'Message' => $errorMessage

			), 'Failure');

		} else {

			$apiKey    = $gatewayParams['apiKey'];
			$invoiceId = checkCbInvoiceID($orderId, $gatewayParams['name']);

			$params = array (

				'merchant'     => $apiKey,
				'trackId' => $trackId
			);

			$result = postToZibal('verify', $params);

			if ($result && isset($result->result) && $result->result == 100) {


				$amount = $result->amount;
				$hash = md5($orderId . $amount . $apiKey);

				if ($secure == $hash) {

					$success = TRUE;
					$message = 'تراکنش با موفقیت انجام شد';

					if ($gatewayParams['currencyType'] == 'Toman') {

						$amount = round($amount / 10);
					}

					addInvoicePayment($invoiceId, $trackId, $amount, 0, 'zibal');

					logTransaction($gatewayParams['name'], array(


						'Transaction' => $trackId,
						'Invoice'     => $orderId,
						'Amount'      => $amount

					), 'Success');

				} else {

					$errorMessage = 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';

					logTransaction($gatewayParams['name'], array(

						'Code'        => 'Invalid Amount',
						'Message'     => $errorMessage,
						'Transaction' => $trackId,
						'Invoice'     => $orderId,
						'Amount'      => $amount,


					), 'Failure');
				}

			} else {

				$errorMessage = 'در ارتباط با وب سرویس زیبال خطایی رخ داده است';

				$errorCode    = isset($result->result) ? $result->result : 'Verify';
				$errorMessage = isset($result->message) ? $result->message : $errorMessage;

				logTransaction($gatewayParams['name'], array(

					'Code'        => $errorCode,
					'Message'     => $errorMessage,
					'Transaction' => $trackId,
					'Invoice'     => $orderId

				), 'Failure');
			}
		}

	} else {

		if ($message) {

			logTransaction($gatewayParams['name'], array(

				'Code'        => 'Invalid Payment',
				'Message'     => $message,
				'Transaction' => $trackId,
				'Invoice'     => $orderId

			), 'Failure');

		} else {

			$errorMessage = 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';

			logTransaction($gatewayParams['name'], array(

				'Code'        => 'Invalid Payment',
				'Message'     => $errorMessage,
				'Transaction' => $trackId, 
				'Invoice'     => $orderId

			), 'Failure');
		}
	}

} else {

	$errorMessage = 'اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است';

	logTransaction($gatewayParams['name'], array(

		'Code'    => 'Invalid Data',
		'Message' => $errorMessage

	), 'Failure');
}

if (isset($orderId) && $orderId) {

	header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $orderId);
	exit;

} else {

	header('Location: ' . $gatewayParams['systemurl'] . '/clientarea.php?action=invoices');
	exit;
}

