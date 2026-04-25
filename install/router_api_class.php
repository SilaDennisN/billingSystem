<?php

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class RouterAPI
{
    private Client $client;

    public function __construct($host, $user, $pass, $port = 8728)
    {
        $config = new Config([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port,
            'timeout' => 10,
            'socket_timeout' => 20,
        ]);

        $this->client = new Client($config);
    }

    public function run($path, $params = [])
    {
        $query = new Query($path);

        foreach ($params as $k => $v) {
            if ($v !== null && $v !== '') {
                $query->equal($k, $v);
            }
        }

        $res = $this->client->query($query)->read();

        foreach ($res as $r) {
            if (isset($r['!trap'])) {
                throw new Exception($r['message'] ?? 'RouterOS error');
            }
        }

        return $res;
    }

    public function get($path)
    {
        return $this->client->query(new Query("$path/print"))->read();
    }
}