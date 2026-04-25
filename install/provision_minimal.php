<?php
$router_id = $_GET['router_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Provision Router</title>
</head>
<body>

<h2>Step 1 — Run this in WinBox Terminal</h2>

<textarea rows="10" cols="80" id="cmd">
/ip firewall filter add chain=input connection-state=established,related action=accept comment="core-established"
/ip firewall filter add chain=input in-interface=wg-billing action=accept comment="core-wg"
/ip firewall filter add chain=input protocol=tcp dst-port=8728 action=accept comment="core-api"
/ip firewall filter add chain=input action=drop comment="core-drop"
</textarea>

<br><br>
<button onclick="copy()">Copy</button>

<hr>

<h2>Step 2 — Test Connection</h2>
<button onclick="test()">Test API</button>

<pre id="output"></pre>

<hr>

<h2>Step 3 — Setup Network</h2>
<button onclick="setup()">Setup Base Network</button>

<script>
const router_id = "<?= $router_id ?>";

function copy() {
    navigator.clipboard.writeText(document.getElementById('cmd').value);
    alert("Copied");
}

function test() {
    fetch('test_api.php', {
        method: 'POST',
        body: new URLSearchParams({router_id})
    })
    .then(r => r.text())
    .then(t => document.getElementById('output').innerText = t);
}

function setup() {
    fetch('script.php', {
        method: 'POST',
        body: new URLSearchParams({router_id})
    })
    .then(r => r.text())
    .then(t => document.getElementById('output').innerText = t);
}
</script>

</body>
</html>