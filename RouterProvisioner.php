<?php
/**
 * Router Provisioner
 * Handles provisioning in steps with proper error handling
 */

class RouterProvisioner {
    
    private $api;
    private $config;
    private $steps = [];
    private $currentStep = null;
    
    public function __construct(RouterAPI $api, $config = []) {
        $this->api = $api;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    // =========================================================================
    // Configuration
    // =========================================================================
    
    private function getDefaultConfig() {
        return [
            'bridge'        => 'bridge',
            'lan_ip'        => '192.168.50.1',
            'lan_net'       => '192.168.50.0/24',
            'wan_interface' => 'ether1',
            'dns_servers'   => '8.8.8.8,8.8.4.4',
            'prefix'        => 'inovatech',
        ];
    }
    
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }
    
    public function getConfig($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    // =========================================================================
    // Event Streaming (for SSE)
    // =========================================================================
    
    /**
     * Send SSE message (for real-time updates)
     * @param string $step
     * @param string $status - 'running', 'ok', 'error'
     * @param string $message
     */
    private function sendEvent($step, $status, $message) {
        $this->steps[$step] = ['status' => $status, 'message' => $message];
        
        // For SSE output
        if (php_sapi_name() !== 'cli') {
            echo "data: " . json_encode([
                'step'    => $step,
                'status'  => $status,
                'message' => $message
            ]) . "\n\n";
            ob_flush();
            flush();
        }
    }
    
    public function getSteps() {
        return $this->steps;
    }
    
    // =========================================================================
    // STEP 1: Firewall Rules
    // =========================================================================
    
    /**
     * Configure firewall - INPUT chain
     */
    public function stepFirewallInput() {
        $this->sendEvent('firewall:input', 'running', 'Configuring INPUT chain...');
        
        try {
            // Clear existing rules
            $removed = $this->api->removeByComment('/ip/firewall/filter', $this->config['prefix']);
            if ($removed > 0) {
                $this->sendEvent('firewall:input', 'running', "Cleared {$removed} existing rules");
            }
            
            $rules = [
                [
                    'chain' => 'input',
                    'connection-state' => 'established,related',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-input-est',
                ],
                [
                    'chain' => 'input',
                    'in-interface' => 'wg-billing',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-wg-accept',
                ],
                [
                    'chain' => 'input',
                    'protocol' => 'tcp',
                    'dst-port' => '8728',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-api-local',
                ],
                [
                    'chain' => 'input',
                    'protocol' => 'udp',
                    'dst-port' => '53',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-dns-udp',
                ],
                [
                    'chain' => 'input',
                    'protocol' => 'tcp',
                    'dst-port' => '53',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-dns-tcp',
                ],
                [
                    'chain' => 'input',
                    'action' => 'drop',
                    'comment' => $this->config['prefix'] . '-input-drop',
                ],
            ];
            
            foreach ($rules as $rule) {
                if (!$this->api->run('/ip/firewall/filter/add', $rule)) {
                    throw new Exception("Failed to add rule: " . $this->api->getLastError());
                }
            }
            
            $this->sendEvent('firewall:input', 'ok', 'INPUT firewall rules configured');
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('firewall:input', 'error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configure firewall - FORWARD chain
     */
    public function stepFirewallForward() {
        $this->sendEvent('firewall:forward', 'running', 'Configuring FORWARD chain...');
        
        try {
            $rules = [
                [
                    'chain' => 'forward',
                    'in-interface' => $this->config['bridge'],
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-forward-bridge',
                ],
                [
                    'chain' => 'forward',
                    'connection-state' => 'established,related',
                    'action' => 'accept',
                    'comment' => $this->config['prefix'] . '-forward-est',
                ],
                [
                    'chain' => 'forward',
                    'action' => 'drop',
                    'comment' => $this->config['prefix'] . '-forward-drop',
                ],
            ];
            
            foreach ($rules as $rule) {
                if (!$this->api->run('/ip/firewall/filter/add', $rule)) {
                    throw new Exception("Failed to add rule: " . $this->api->getLastError());
                }
            }
            
            $this->sendEvent('firewall:forward', 'ok', 'FORWARD firewall rules configured');
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('firewall:forward', 'error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configure firewall - NAT (masquerade)
     */
    public function stepFirewallNAT() {
        $this->sendEvent('firewall:nat', 'running', 'Configuring NAT...');
        
        try {
            // Remove existing NAT rules
            $this->api->removeByComment('/ip/firewall/nat', $this->config['prefix']);
            
            // Add masquerade
            if (!$this->api->run('/ip/firewall/nat/add', [
                'chain' => 'srcnat',
                'out-interface' => $this->config['wan_interface'],
                'action' => 'masquerade',
                'comment' => $this->config['prefix'] . '-masquerade',
            ])) {
                throw new Exception("Failed to add NAT rule: " . $this->api->getLastError());
            }
            
            $this->sendEvent('firewall:nat', 'ok', 'NAT masquerade configured');
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('firewall:nat', 'error', $e->getMessage());
            return false;
        }
    }
    
    // =========================================================================
    // STEP 2: DNS & Identity
    // =========================================================================
    
    public function stepDNS() {
        $this->sendEvent('dns', 'running', 'Configuring DNS...');
        
        try {
            if (!$this->api->run('/ip/dns/set', [
                'allow-remote-requests' => 'yes',
                'servers' => $this->config['dns_servers'],
            ])) {
                throw new Exception($this->api->getLastError());
            }
            
            $this->sendEvent('dns', 'ok', 'DNS configured: ' . $this->config['dns_servers']);
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('dns', 'error', $e->getMessage());
            return false;
        }
    }
    
    public function stepIdentity($name) {
        $this->sendEvent('identity', 'running', 'Setting identity...');
        
        try {
            if (!$this->api->run('/system/identity/set', ['name' => $name])) {
                throw new Exception($this->api->getLastError());
            }
            
            $this->sendEvent('identity', 'ok', "Identity set to: {$name}");
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('identity', 'error', $e->getMessage());
            return false;
        }
    }
    
    // =========================================================================
    // STEP 3: Bridge & Network (Base)
    // =========================================================================
    
    public function stepBridge($ports = ['ether2', 'ether3', 'ether4', 'ether5', 'wlan1']) {
        $this->sendEvent('bridge', 'running', 'Creating bridge...');
        
        try {
            // Check if bridge exists
            $existing = $this->api->findItem('/interface/bridge', 'name', $this->config['bridge']);
            
            if (!$existing) {
                if (!$this->api->run('/interface/bridge/add', ['name' => $this->config['bridge']])) {
                    throw new Exception("Failed to create bridge: " . $this->api->getLastError());
                }
            }
            
            // Add ports
            foreach ($ports as $port) {
                $this->sendEvent('bridge', 'running', "Adding port: {$port}");
                
                // Check if already in bridge
                $existing = $this->api->findItem('/interface/bridge/port', 'interface', $port);
                
                if (!$existing) {
                    if (!$this->api->run('/interface/bridge/port/add', [
                        'bridge' => $this->config['bridge'],
                        'interface' => $port,
                    ])) {
                        // Non-fatal, continue
                        $this->sendEvent('bridge', 'running', "Skipped port {$port} (may already exist)");
                    }
                }
            }
            
            $this->sendEvent('bridge', 'ok', 'Bridge created with ports');
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('bridge', 'error', $e->getMessage());
            return false;
        }
    }
    
    public function stepBridgeIP() {
        $this->sendEvent('bridge:ip', 'running', 'Setting bridge IP...');
        
        try {
            // Check if IP exists
            $existing = $this->api->findItem('/ip/address', 'address', $this->config['lan_ip'] . '/24');
            
            if (!$existing) {
                if (!$this->api->run('/ip/address/add', [
                    'address' => $this->config['lan_ip'] . '/24',
                    'interface' => $this->config['bridge'],
                ])) {
                    throw new Exception($this->api->getLastError());
                }
            }
            
            $this->sendEvent('bridge:ip', 'ok', 'Bridge IP set: ' . $this->config['lan_ip'] . '/24');
            return true;
            
        } catch (Exception $e) {
            $this->sendEvent('bridge:ip', 'error', $e->getMessage());
            return false;
        }
    }
    
    // =========================================================================
    // Public: Run All Basic Steps
    // =========================================================================
    
    public function provisionBasics($routerName) {
        $success = true;
        
        $success = $this->stepFirewallInput() && $success;
        $success = $this->stepFirewallForward() && $success;
        $success = $this->stepFirewallNAT() && $success;
        $success = $this->stepDNS() && $success;
        $success = $this->stepIdentity($routerName) && $success;
        $success = $this->stepBridge() && $success;
        $success = $this->stepBridgeIP() && $success;
        
        return $success;
    }
}
