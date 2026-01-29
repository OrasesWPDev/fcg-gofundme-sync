<?php
/**
 * GoFundMe Pro API Client
 * 
 * Handles OAuth2 authentication and API requests.
 * 
 * @package FCG_GoFundMe_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class FCG_GFM_API_Client {
    
    /**
     * API Base URL
     */
    private const API_BASE = 'https://api.classy.org/2.0';
    
    /**
     * OAuth2 Token URL
     */
    private const TOKEN_URL = 'https://api.classy.org/oauth2/auth';
    
    /**
     * Transient key for access token
     */
    private const TOKEN_TRANSIENT = 'gofundme_access_token';
    
    /**
     * Client ID
     * 
     * @var string
     */
    private $client_id;
    
    /**
     * Client Secret
     * 
     * @var string
     */
    private $client_secret;
    
    /**
     * Organization ID
     * 
     * @var string
     */
    private $org_id;
    
    /**
     * Constructor
     *
     * Credentials are loaded with priority:
     * 1. Environment variables (recommended for WP Engine)
     * 2. PHP constants in wp-config.php (fallback)
     */
    public function __construct() {
        $this->client_id = $this->get_credential('GOFUNDME_CLIENT_ID');
        $this->client_secret = $this->get_credential('GOFUNDME_CLIENT_SECRET');
        $this->org_id = $this->get_credential('GOFUNDME_ORG_ID');
    }

    /**
     * Get credential from environment variable or constant
     *
     * @param string $name Credential name
     * @return string Credential value or empty string
     */
    private function get_credential(string $name): string {
        // Priority 1: Environment variable (WP Engine User Portal)
        $env_value = getenv($name);
        if ($env_value !== false && $env_value !== '') {
            return $env_value;
        }

        // Priority 2: PHP constant (wp-config.php)
        if (defined($name)) {
            return constant($name);
        }

        return '';
    }
    
    /**
     * Check if client is configured
     * 
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->client_id) && !empty($this->client_secret) && !empty($this->org_id);
    }
    
    /**
     * Get Organization ID
     * 
     * @return string
     */
    public function get_org_id(): string {
        return $this->org_id;
    }
    
    /**
     * Get OAuth2 access token
     * 
     * @return string|false Access token or false on failure
     */
    private function get_access_token() {
        // Check transient cache first
        $cached_token = get_transient(self::TOKEN_TRANSIENT);
        if ($cached_token) {
            return $cached_token;
        }
        
        // Request new token
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            $this->log_error('Token request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            $this->log_error('No access token in response: ' . wp_json_encode($body));
            return false;
        }
        
        $access_token = $body['access_token'];
        $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;
        
        // Cache token (expire 5 minutes early for safety)
        set_transient(self::TOKEN_TRANSIENT, $access_token, max(60, $expires_in - 300));
        
        return $access_token;
    }
    
    /**
     * Make API request
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., /designations/123)
     * @param array|null $data Request body data
     * @return array Response data or error array
     */
    public function request(string $method, string $endpoint, ?array $data = null): array {
        $token = $this->get_access_token();
        if (!$token) {
            return [
                'success' => false,
                'error'   => 'Failed to obtain access token',
            ];
        }
        
        $url = self::API_BASE . $endpoint;
        
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error("API {$method} {$endpoint} failed: " . $response->get_error_message());
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle HTTP errors
        if ($code >= 400) {
            $error_message = $body['error'] ?? $body['message'] ?? "HTTP {$code}";
            $this->log_error("API {$method} {$endpoint} returned {$code}: {$error_message}");
            return [
                'success'   => false,
                'error'     => $error_message,
                'http_code' => $code,
                'response'  => $body,
            ];
        }
        
        // Handle 204 No Content (successful DELETE)
        if ($code === 204) {
            return [
                'success' => true,
                'data'    => null,
            ];
        }
        
        return [
            'success' => true,
            'data'    => $body,
        ];
    }
    
    /**
     * Create a designation
     * 
     * @param array $data Designation data
     * @return array Response
     */
    public function create_designation(array $data): array {
        return $this->request('POST', "/organizations/{$this->org_id}/designations", $data);
    }
    
    /**
     * Update a designation
     * 
     * @param int|string $designation_id Designation ID
     * @param array $data Updated data
     * @return array Response
     */
    public function update_designation($designation_id, array $data): array {
        return $this->request('PUT', "/designations/{$designation_id}", $data);
    }
    
    /**
     * Delete a designation
     * 
     * @param int|string $designation_id Designation ID
     * @return array Response
     */
    public function delete_designation($designation_id): array {
        return $this->request('DELETE', "/designations/{$designation_id}");
    }
    
    /**
     * Get a designation
     * 
     * @param int|string $designation_id Designation ID
     * @return array Response
     */
    public function get_designation($designation_id): array {
        return $this->request('GET', "/designations/{$designation_id}");
    }

    /**
     * Get all designations for the organization with pagination.
     *
     * @param int $per_page Results per page (default 100, max 100)
     * @return array {success: bool, data: array|null, error: string|null, total: int}
     */
    public function get_all_designations(int $per_page = 100): array {
        $all_designations = [];
        $page = 1;

        do {
            $endpoint = "/organizations/{$this->org_id}/designations?page={$page}&per_page={$per_page}";
            $result = $this->request('GET', $endpoint);

            if (!$result['success']) {
                return $result; // Return error immediately
            }

            $data = $result['data'];
            $all_designations = array_merge($all_designations, $data['data'] ?? []);

            $has_more = $page < ($data['last_page'] ?? 1);
            $page++;

        } while ($has_more);

        return [
            'success' => true,
            'data' => $all_designations,
            'total' => count($all_designations),
        ];
    }

    /**
     * Create a campaign
     *
     * @param array $data Campaign data (name, goal, type, etc.)
     * @return array Response
     */
    public function create_campaign(array $data): array {
        return $this->request('POST', "/organizations/{$this->org_id}/campaigns", $data);
    }

    /**
     * Update a campaign
     *
     * @param int|string $campaign_id Campaign ID
     * @param array $data Updated data
     * @return array Response
     */
    public function update_campaign($campaign_id, array $data): array {
        return $this->request('PUT', "/campaigns/{$campaign_id}", $data);
    }

    /**
     * Get a campaign
     *
     * @param int|string $campaign_id Campaign ID
     * @return array Response
     */
    public function get_campaign($campaign_id): array {
        return $this->request('GET', "/campaigns/{$campaign_id}");
    }

    /**
     * Get campaign overview with donation totals
     *
     * Returns aggregated donation data including total raised, donor count, etc.
     * Note: Amounts are returned as strings (e.g., "8850.00") - cast to float as needed.
     *
     * @param int|string $campaign_id Campaign ID
     * @return array Response with overview data
     */
    public function get_campaign_overview($campaign_id): array {
        return $this->request('GET', "/campaigns/{$campaign_id}/overview");
    }

    /**
     * Get all campaigns for the organization with pagination.
     *
     * @param int $per_page Results per page (default 100, max 100)
     * @return array {success: bool, data: array|null, error: string|null, total: int}
     */
    public function get_all_campaigns(int $per_page = 100): array {
        $all_campaigns = [];
        $page = 1;

        do {
            $endpoint = "/organizations/{$this->org_id}/campaigns?page={$page}&per_page={$per_page}";
            $result = $this->request('GET', $endpoint);

            if (!$result['success']) {
                return $result;
            }

            $data = $result['data'];
            $all_campaigns = array_merge($all_campaigns, $data['data'] ?? []);

            $has_more = $page < ($data['last_page'] ?? 1);
            $page++;

        } while ($has_more);

        return [
            'success' => true,
            'data' => $all_campaigns,
            'total' => count($all_campaigns),
        ];
    }

    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FCG GoFundMe Sync] ' . $message);
        }
    }
}
