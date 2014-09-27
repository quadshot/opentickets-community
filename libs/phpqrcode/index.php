<?php    

$ref = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : '';
$host = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ( isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '' ));
$same_server = isset($_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR']) && $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'];

if (!$same_server && !(isset($ref['host']) && $host == strtolower($ref['host']))) die();
if (!isset($_GET['d']) || empty($_GET['d'])) die();
$d = strrev(@base64_decode($_GET['d']));
if (empty($d)) die();

include_once 'qrlib.php';
//QRCode::png($d, false, 'L', 3, 1);
$enc = QRencode::factory('L', 3, 1);

$outfile = false;
try {
	ob_start();
	$tab = $enc->encode($d);
	$err = ob_get_contents();
	ob_end_clean();

	if ($err != '')
		QRtools::log($outfile, $err);

	$maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2 * $enc->margin));

	QRimage::jpg($tab, $outfile, min(max(1, $enc->size), $maxSize), $enc->margin, 100);
} catch (Exception $e) {
	QRtools::log($outfile, $e->getMessage());
}
