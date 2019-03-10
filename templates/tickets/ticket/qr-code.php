<?php if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) die( header( 'Location: /') );
$size = 175;
$div_name = uniqid('qrcode-');
$code_content = $ticket->qr_code_data;
$debug = $ticket->qr_code;
if ($multiple && isset($index) && isset($ticket->qr_codes_data[$index])) {
	$code_content = $ticket->qr_codes_data[ $index ];
	$debug = $ticket->qr_codes[$index];
}
?>
<div style="height:<?php echo $size ?>px; width:<?php echo $size ?>px;" id="<?php echo $div_name ?>"></div>
<script type="text/javascript">new QRCode(
	document.getElementById("<?php echo $div_name ?>"),
	{
		text: "<?php echo $code_content ?>",
		width: <?php echo $size ?>,
		height: <?php echo $size ?>,
		correctLevel: QRCode.CorrectLevel.L
	}
);</script>
