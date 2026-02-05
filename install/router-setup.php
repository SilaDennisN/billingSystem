<?php
/**
 * RouterOS Configuration Script
 * This will automatically configure MikroTik for hotspot billing
 */

function configureRouterOS($router_ip, $username, $password, $port = 8728) {
    $api = new RouterosAPI();
    
    if ($api->connect($router_ip, $username, $password, $port)) {
        // 1. Configure DHCP Server for Hotspot
        echo "Configuring DHCP Server...\n";
        $api->write('/ip/pool/add', false);
        $api->write('=name=hotspot_pool', false);
        $api->write('=ranges=192.168.100.10-192.168.100.254');
        $api->read();
        
        $api->write('/ip/dhcp-server/add', false);
        $api->write('=name=dhcp_hotspot', false);
        $api->write('=interface=ether2', false);
        $api->write('=address-pool=hotspot_pool', false);
        $api->write('=disabled=no');
        $api->read();
        
        $api->write('/ip/dhcp-server/network/add', false);
        $api->write('=address=192.168.100.0/24', false);
        $api->write('=gateway=192.168.100.1', false);
        $api->write('=dns-server=8.8.8.8,8.8.4.4');
        $api->read();
        
        // 2. Configure Hotspot Server
        echo "Configuring Hotspot Server...\n";
        $api->write('/ip/hotspot/add', false);
        $api->write('=name=hotspot1', false);
        $api->write('=interface=ether2', false);
        $api->write('=address-pool=hotspot_pool', false);
        $api->write('=profile=default', false);
        $api->write('=disabled=no');
        $api->read();
        
        // 3. Configure Hotspot User Profile
        $api->write('/ip/hotspot/user/profile/add', false);
        $api->write('=name=default', false);
        $api->write('=session-timeout=none', false);
        $api->write('=idle-timeout=none', false);
        $api->write('=keepalive-timeout=2m', false);
        $api->write('=status-autorefresh=1m', false);
        $api->write('=shared-users=1', false);
        $api->write('=rate-limit=');
        $api->read();
        
        // 4. Configure Firewall/NAT
        echo "Configuring Firewall Rules...\n";
        $api->write('/ip/firewall/nat/add', false);
        $api->write('=chain=srcnat', false);
        $api->write('=action=masquerade', false);
        $api->write('=out-interface=ether2');
        $api->read();
        
        // 5. Allow API access
        $api->write('/ip/firewall/filter/add', false);
        $api->write('=chain=input', false);
        $api->write('=protocol=tcp', false);
        $api->write('=dst-port=8728', false);
        $api->write('=action=accept', false);
        $api->write('=comment="API Access"');
        $api->read();
        
        // 6. Enable services
        $api->write('/ip/service/set', false);
        $api->write('=api', false);
        $api->write('=disabled=no');
        $api->read();
        
        $api->write('/ip/service/set', false);
        $api->write('=winbox', false);
        $api->write('=disabled=no');
        $api->read();
        
        // 7. Create test user
        echo "Creating test user...\n";
        $api->write('/ip/hotspot/user/add', false);
        $api->write('=name=testuser', false);
        $api->write('=password=test123', false);
        $api->write('=profile=default', false);
        $api->write('=server=hotspot1');
        $api->read();
        
        echo "Router configuration completed successfully!\n";
        
        $api->disconnect();
        return true;
    }
    
    return false;
}

// Usage in the installer
if (isset($_POST['configure_router'])) {
    if (configureRouterOS(
        $_SESSION['router_ip'],
        $_SESSION['router_user'],
        $_SESSION['router_pass'],
        $_SESSION['router_port']
    )) {
        $_SESSION['router_configured'] = true;
    }
}
?>