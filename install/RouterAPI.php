<?php
/**
 * RouterOS API Wrapper Class
 * Handles connection, error handling, and basic operations
 */

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class RouterAPI {
    
    private $client;
    private $host;
    private $user = 'admin';
    private $pass;
    private $port = 8728;
    private $timeout = 30;
    private $lastError = null;
    
    // =========================================================================
    // Connection
    // =========================================================================
    
    public function __construct($host, $pass, $user = 'admin', $port = 8728) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
    }
    
    /**
     * Connect to router
     * @return bool
     */
    public function connect() {
        try {
            $config = new Config([
                'host'           => $this->host,
                'user'           => $this->user,
                'pass'           => $this->pass,
                'port'           => $this->port,
                'timeout'        => $this->timeout,
                'socket_timeout' => 300,
            ]);
            
            $this->client = new Client($config);
            return true;
            
        } catch (Exception $e) {
            $this->lastError = "Connection failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Check if connected
     * @return bool
     */
    public function isConnected() {
        return $this->client !== null;
    }
    
    /**
     * Get last error
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    // =========================================================================
    // Query Operations
    // =========================================================================
    
    /**
     * Run API command with parameters
     * @param string $path - API path (e.g., '/ip/firewall/filter/add')
     * @param array $params - Parameters as key => value
     * @return array|false
     */
    public function run($path, $params = []) {
        if (!$this->isConnected()) {
            $this->lastError = "Not connected to router";
            return false;
        }
        
        try {
            $query = new Query($path);
            
            foreach ($params as $k => $v) {
                if ($v === null || $v === '') continue;
                $query->equal($k, $v);
            }
            
            $result = $this->client->query($query)->read();
            return $result ?: true;
            
        } catch (Exception $e) {
            $this->lastError = "API error [{$path}]: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Get list of items from API path
     * @param string $path - API path (e.g., '/ip/firewall/filter')
     * @return array|false
     */
    public function getList($path) {
        if (!$this->isConnected()) {
            $this->lastError = "Not connected to router";
            return false;
        }
        
        try {
            $result = $this->client->query(new Query("$path/print"))->read();
            return is_array($result) ? $result : [];
            
        } catch (Exception $e) {
            $this->lastError = "Get list error [{$path}]: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Find item in list by matching key/value
     * @param string $path
     * @param string $key
     * @param mixed $value
     * @return array|null
     */
    public function findItem($path, $key, $value) {
        $items = $this->getList($path);
        
        if (!$items) return null;
        
        foreach ($items as $item) {
            if (($item[$key] ?? null) === $value) {
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * Add or update item
     * @param string $path
     * @param string $matchKey - Key to match for updates
     * @param mixed $matchValue - Value to match
     * @param array $params - Parameters to set
     * @return bool
     */
    public function ensure($path, $matchKey, $matchValue, $params) {
        $existing = $this->findItem($path, $matchKey, $matchValue);
        
        if ($existing && isset($existing['.id'])) {
            // Update existing
            return $this->run("$path/set", array_merge(
                ['.id' => $existing['.id']],
                $params
            ));
        } else {
            // Add new
            return $this->run("$path/add", array_merge(
                [$matchKey => $matchValue],
                $params
            ));
        }
    }
    
    /**
     * Remove items by comment prefix
     * @param string $path
     * @param string $prefix
     * @return int - Number removed
     */
    public function removeByComment($path, $prefix = 'inovatech') {
        // Don't delete hotspot configs
        if (in_array($path, ['/ip/hotspot', '/ip/hotspot/profile'])) {
            return 0;
        }
        
        $items = $this->getList($path);
        if (!$items) return 0;
        
        $count = 0;
        foreach ($items as $item) {
            if (isset($item['.id']) && str_starts_with($item['comment'] ?? '', $prefix)) {
                if ($this->run("$path/remove", ['.id' => $item['.id']])) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    // =========================================================================
    // Helper: Get identity
    // =========================================================================
    
    public function getIdentity() {
        $result = $this->getList('/system/identity');
        return $result[0] ?? null;
    }
}
