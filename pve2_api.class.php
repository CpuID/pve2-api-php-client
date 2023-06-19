<?php
// Take noVNC request from WHMCS Client Area,
// assign PVE Ticket as Browser Cookie, then
// redirect to the noVNC page inc. VNC path.
if (isset($_GET['pveticket']) && isset($_GET['host']) && isset($_GET['path'])) {
	$pveticket = $_GET['pveticket'];
	$host = $_GET['host'];
	$path = $_GET['path'];

    // Get the requesting hostname/domain from request
    $whmcsdomain = parse_url($_SERVER['HTTP_HOST']);
    $domainonly = preg_replace("/^(.*?)\.(.*)$/","$2",$whmcsdomain['path']);

    // Set browser cookie with PVE Auth ticket (vnc user)
	setrawcookie('PVEAuthCookie', rawurldecode($pveticket), 0, '/', $domainonly);

	// $path includes the VNC Ticket, so dual auth is handled
	$hostname = gethostbyaddr($host);
	$redirect_url = '/modules/servers/pvewhmcs/novnc/vnc.html?host=' . $hostname . '&port=8006&path=' . urlencode($path);

	// Redirect the user to the noVNC system with query strings inc.
	header('Location: ' . $redirect_url);
	exit;
} else {
	echo 'Error: Missing required info to route your request. Please try again.';
}
?>
