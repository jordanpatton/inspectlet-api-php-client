<?php
/**
 * Inspectlet API PHP Client
 * @author Jordan Patton <jordanpatton@gmail.com>
 * @package default
 */
class Inspectlet {
    
    /**
     * @var string username
     * @access protected
     */
    protected $_username;
    
    /**
     * @var string password
     * @access protected
     */
    protected $_password;
    
    /**
     * @var string API protocol
     * @access protected
     */
    protected $_protocol;
    
    /**
     * @var string API hostname
     * @access protected
     */
    protected $_hostname;
    
    /**
     * @var resource $conn The client connection instance to use
     * @access private
     */
    private $conn = null;
    
    /**
     * @var resource $cookie The cookie file reference
     * @access private
     */
    private $cookie = null;
    
    /**
     * Inspectlet Constructor
     *
     * This method creates a new Inspectlet object with a connection to a
     * specific account specified by username and password.
     *
     * @param string $username username
     * @param string $password password
     * @throws Inspectlet_Exception If an error occurs creating the instance
     * @return Inspectlet A unique Inspectlet instance
     */
    public function __construct($username, $password) {
        if(empty($username)) {throw new Inspectlet_Exception('Missing username.');}
        if(empty($password)) {throw new Inspectlet_Exception('Missing password.');}
        $this->_username = $username;
        $this->_password = $password;
        $this->_protocol = 'https';
        $this->_hostname = 'www.inspectlet.com';
        $this->cookie = tempnam('php://temp/curlcookie','CURLCOOKIE');
    }
    
    /**
     * Build the request URI
     * @param string $pathname API resource to access
     * @return string Constructed URI
     */
    protected function buildUri($pathname) {
        return $this->_protocol.'://'.$this->_hostname.$pathname;
    }
    
    /**
     * Get the connection
     * @return boolean
     */
    protected function getConnection() {
        $this->conn = curl_init();
        return is_resource($this->conn);
    }
    
    /**
     * Close the connection
     */
    protected function closeConnection() {
        curl_close($this->conn);
    }
    
    /**
     * Return an error
     * @param string $message Error message
     * @return array Result
     */
    protected function failure($message) {
        return array(
            'success' => false,
            'message' => $message
        );
    }
    
    /**
     * Return a success with data
     * @param string $data Payload
     * @return array Result
     */
    protected function success($data) {
        return array(
            'success' => true,
            'data'    => $data
        );
    }
    
    /**
     * Log in
     * @return boolean Success
     */
    protected function logIn() {
        // Check the connection
        if(!is_resource($this->conn)) {return false;}
        
        // Perform network request
        curl_setopt($this->conn, CURLOPT_URL, $this->buildUri('/signin/login'));
        curl_setopt($this->conn, CURLOPT_HEADER, true); //return HTTP headers
        curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($this->conn, CURLOPT_POST, true);
        curl_setopt($this->conn, CURLOPT_POSTFIELDS, 'email='.urlencode($this->_username).'&password='.urlencode($this->_password).'&submform=Sign+In');
        $content = curl_exec($this->conn);
        $response = curl_getinfo($this->conn, CURLINFO_HTTP_CODE);
        
        // Return the result
        if($content === false) {return false;}
        if($response != 200 && $response != 302) {return false;}
        return true;
    }
    
    /**
     * Log out
     * @return boolean Success
     */
    protected function logOut() {
        // Check the connection
        if(!is_resource($this->conn)) {return false;}
        
        // Perform network request
        curl_setopt($this->conn, CURLOPT_URL, $this->buildUri('/control/logout'));
        curl_setopt($this->conn, CURLOPT_HEADER, true); //return HTTP headers
        curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: text/html'));
        curl_setopt($this->conn, CURLOPT_POST, false);
        curl_setopt($this->conn, CURLOPT_POSTFIELDS, '');
        $content = curl_exec($this->conn);
        $response = curl_getinfo($this->conn, CURLINFO_HTTP_CODE);
        
        // Return the result
        if($content === false) {return false;}
        if($response != 200 && $response != 302) {return false;}
        return true;
    }
    
    /**
     * Run a user-specified network request
     * @param string $pathname API resource to access
     * @param string $http_method http method (usually GET or POST)
     * @param string $response_format format of the response data (HTML or JSON)
     * @param array $params Parameters array
     * @return array Results
     */
    protected function run($pathname, $http_method='POST', $response_format='JSON', $params=array()) {
        // Set up cURL
        if(!is_resource($this->conn)) {
            if(!$this->getConnection()) {
                return $this->failure('Cannot initialize connection.');
            }
        }
        curl_setopt($this->conn, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1');
        curl_setopt($this->conn, CURLOPT_COOKIESESSION, true);
        curl_setopt($this->conn, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($this->conn, CURLOPT_COOKIEJAR, $this->cookie);
        curl_setopt($this->conn, CURLOPT_COOKIE, session_name().'='.session_id());
        curl_setopt($this->conn, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->conn, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->conn, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->conn, CURLOPT_SSL_VERIFYPEER, false); //required for https urls
        curl_setopt($this->conn, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->conn, CURLOPT_TIMEOUT, 5);
        curl_setopt($this->conn, CURLOPT_RETURNTRANSFER, true); //return into a variable
        
        // Log in
        if($this->logIn()) {
            // Perform user-specified network request
            $request_url = $this->buildUri($pathname);
            $post_fields = (!empty($params)) ? json_encode($params) : '{}';
            curl_setopt($this->conn, CURLOPT_URL, $request_url);
            curl_setopt($this->conn, CURLOPT_HEADER, false); //don't return HTTP headers
            if($http_method == 'POST' && $response_format == 'JSON') {
                curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: application/json;charset=UTF-8'));
                curl_setopt($this->conn, CURLOPT_POST, true);
                curl_setopt($this->conn, CURLOPT_POSTFIELDS, $post_fields);
            } elseif($http_method == 'POST' && $response_format == 'HTML') {
                curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
                curl_setopt($this->conn, CURLOPT_POST, true);
                curl_setopt($this->conn, CURLOPT_POSTFIELDS, $post_fields);
            } elseif($http_method == 'GET' && $response_format == 'JSON') {
                curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: application/json;charset=UTF-8'));
                curl_setopt($this->conn, CURLOPT_POST, false);
                curl_setopt($this->conn, CURLOPT_POSTFIELDS, '');
            } else {
                curl_setopt($this->conn, CURLOPT_HTTPHEADER, array('Content-type: text/html'));
                curl_setopt($this->conn, CURLOPT_POST, false);
                curl_setopt($this->conn, CURLOPT_POSTFIELDS, '');
            }
            $content = curl_exec($this->conn);
            $response = curl_getinfo($this->conn, CURLINFO_HTTP_CODE);
            if($content === false) {$this->closeConnection(); return $this->failure('cURL Error: '.curl_error($this->conn));}
            if($response != 200 && $response != 302) {$this->closeConnection(); return $this->failure('HTTP Error ['.$response.']: '.$content);}
            
            // Log out
            $this->logOut();
            
            // Close the connection
            $this->closeConnection();
            
            // Parse the result
            if($response_format == 'JSON') {
                $parsed_result = json_decode($content, true);
                $json_error = json_last_error();
                if($parsed_result === null && $json_error !== JSON_ERROR_NONE) {return $this->failure('JSON Error ['.$json_error.']');}
                else {return $this->success($parsed_result);}
            } else {
                return $this->success($content);
            }
        } else {
            $this->closeConnection();
            return $this->failure('Failed to log in.');
        }
    }
    
    
    
    /***************************
     * Inspectlet API methods
     ***************************/
    
    /**
     * Retrieves a list of sites in a user's account.
     * @see https://www.inspectlet.com/dashboard
     * @return array Result
     */
    public function getSites() {
        $result = $this->run('/dashboard', 'GET', 'HTML', array());
        if($result['success']) {
            $sites = array();
            try {
                // Set up the DOM as an object
                $dom = new DomDocument();
                $previous_errors = libxml_use_internal_errors(true);
                $dom->loadHTML((string)$result['data']);
                libxml_clear_errors();
                libxml_use_internal_errors($previous_errors);
                // Traverse the HTML
                $xpath = new DomXPath($dom);
                foreach($xpath->query('.//div[@id="sitelist"]/div[@class="trow listcolor1"] | .//div[@id="sitelist"]/div[@class="trow listcolor2"]') as $row) {
                    $site_name = trim($xpath->query('./div[contains(@class,"cname")]', $row)->item(0)->nodeValue);
                    $site_captures = trim($xpath->query('./div[contains(@class,"crecenabled")]/a', $row)->item(0)->getAttribute('href'));
                    $site_heatmaps = trim($xpath->query('./div[contains(@class,"cheatmaps")]/a', $row)->item(0)->getAttribute('href'));
                    $site_forms = trim($xpath->query('./div[contains(@class,"cformanalytics")]/a', $row)->item(0)->getAttribute('href'));
                    $site_status = trim($xpath->query('./div[contains(@class,"cstatus")]/img', $row)->item(0)->getAttribute('src'));
                    $site_id = str_ireplace('/dashboard/captures/', '', $site_captures);
                    $sites[] = array(
                        'id'       => $site_id,
                        'name'     => $site_name,
                        'captures' => $site_captures,
                        'heatmaps' => $site_heatmaps,
                        'forms'    => $site_forms,
                        'status'   => $site_status
                    );
                }
            } catch(Exception $e) {}
            // Save the results
            $result['html'] = $result['data'];
            $result['data'] = $sites;
        }
        return $result;
    }
    
    /**
     * Retrieves a list of captures in a user's account.
     * @see https://www.inspectlet.com/dashboard/captures/:site_id
     * @see https://www.inspectlet.com/dashboard/captureapi/:side_id
     * @param string $site_id required Site ID
     * @param array $params optional request array
     * @return array Result
     */
    public function getCaptures($site_id, $params=array()) {
        return $this->run('/dashboard/captureapi/'.$site_id, 'POST', 'JSON', $params);
    }
}




/**
 * Inspectlet Exception
 * @author Jordan Patton <jordanpatton@gmail.com>
 * @package default
 */
class Inspectlet_Exception extends Exception {}

?>