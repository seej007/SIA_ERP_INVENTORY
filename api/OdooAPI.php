<?php
class OdooAPI {
    private $url;
    private $db;
    private $username;
    private $apikey;
    private $uid;
    private $lastError = '';
    
    public function __construct($url, $db, $username, $apikey) {
        $this->url = rtrim($url, '/');
        $this->db = $db;
        $this->username = $username;
        $this->apikey = $apikey;
        
        $this->uid = $this->authenticate();
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    private function jsonRpcRequest($url, $params) {
        $data = array(
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => $params,
            'id' => mt_rand(1, 999999)
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $this->lastError = 'cURL error: ' . curl_error($ch) . ' (Code: ' . $httpCode . ')';
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        $result = json_decode($response, true);
        
        if (!$result) {
            $this->lastError = 'Invalid JSON response';
            return false;
        }
        
        if (isset($result['error'])) {
            $error = $result['error'];
            $this->lastError = 'Odoo error: ' . $error['message'];
            if (isset($error['data']) && isset($error['data']['message'])) {
                $this->lastError .= ' - ' . $error['data']['message'];
            }
            return false;
        }
        
        return isset($result['result']) ? $result['result'] : false;
    }
    
    private function authenticate() {
        $params = array(
            'service' => 'common',
            'method' => 'login',
            'args' => array(
                $this->db,
                $this->username,
                $this->apikey
            )
        );
        
        $result = $this->jsonRpcRequest($this->url . '/jsonrpc', $params);
        
        if ($result === false) {
            return false;
        }
        
        return $result;
    }
    
    public function isConnected() {
        return $this->uid !== false;
    }
    
    public function getUid() {
        return $this->uid;
    }
    
    public function execute($model, $method, $args = array()) {
        $params = array(
            'service' => 'object',
            'method' => 'execute',
            'args' => array_merge(array(
                $this->db,
                $this->uid,
                $this->apikey,
                $model,
                $method
            ), $args)
        );
        
        return $this->jsonRpcRequest($this->url . '/jsonrpc', $params);
    }
    
    public function search($model, $criteria = array()) {
        return $this->execute($model, 'search', array($criteria));
    }
    
    public function read($model, $ids, $fields = array()) {
        return $this->execute($model, 'read', array($ids, $fields));
    }
    
    public function searchRead($model, $criteria = array(), $fields = array(), $offset = 0, $limit = 0) {
        return $this->execute($model, 'search_read', array($criteria, $fields, $offset, $limit));
    }
    
    public function searchCount($model, $criteria = array()) {
        return $this->execute($model, 'search_count', array($criteria));
    }
    
    public function create($model, $data) {
        try {
            $result = $this->execute($model, 'create', array($data));
            if ($result === false) {
                error_log("Failed to create record in $model: " . $this->getLastError());
                return false;
            }
            return $result;
        } catch (Exception $e) {
            $this->lastError = "Exception creating record: " . $e->getMessage();
            error_log($this->lastError);
            return false;
        }
    }
    
    public function write($model, $ids, $data) {
        return $this->execute($model, 'write', array($ids, $data));
    }
    
    public function unlink($model, $ids) {
        return $this->execute($model, 'unlink', array($ids));
    }
    
    public function getFields($model) {
        return $this->execute($model, 'fields_get', array(array(), array('string', 'help', 'type')));
    }
}
