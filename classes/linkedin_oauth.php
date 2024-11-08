<?php
namespace local_linkedinbadge;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

class linkedin_oauth {
    private $client_id;
    private $client_secret;
    private $redirect_uri;

    public function __construct() {
        global $CFG;
        $this->client_id = get_config('local_linkedinbadge', 'linkedin_client_id');
        $this->client_secret = get_config('local_linkedinbadge', 'linkedin_client_secret');
        $this->redirect_uri = $CFG->wwwroot . '/local/linkedinbadge/linkedin_callback.php';
    }

    public function get_auth_url() {
        global $SESSION;

        // Generate state parameter
        $state = md5(uniqid(rand(), true));
        $SESSION->linkedin_state = $state;

        // Required scopes for OIDC and posting
        $scope = 'openid profile email w_member_social';

        // Build authorization URL
        $params = array(
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $scope,
            'state' => $state
        );

        \local_linkedinbadge\logger::log('Authorization Parameters', [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $scope,
            'state' => $state
        ]);

        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
    }

    public function handle_callback($code) {
        global $DB, $USER;

        \local_linkedinbadge\logger::log('Received Callback', [
            'code_exists' => !empty($code),
            'code_length' => strlen($code),
            'code' => $code
        ]);

        // Exchange code for token
        $token_url = 'https://www.linkedin.com/oauth/v2/accessToken';

        $post_fields = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri
        );

        // Log request details (excluding secret)
        $log_fields = $post_fields;
        unset($log_fields['client_secret']);
        \local_linkedinbadge\logger::log('Token Request', $log_fields);

        // Initialize cURL
        $ch = curl_init($token_url);

        // Set cURL options
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_fields),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_VERBOSE => true
        ));

        // Enable verbose debugging
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Get verbose information
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        fclose($verbose);

        // Log full response details
        \local_linkedinbadge\logger::log('Token Response', [
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => curl_error($ch),
            'verbose_log' => $verbose_log
        ]);

        if (curl_errno($ch)) {
            $error_message = get_string('error:curl', 'local_linkedinbadge', curl_error($ch));
            \local_linkedinbadge\logger::log('cURL Error', [
                'error' => curl_error($ch),
                'errno' => curl_errno($ch)
            ]);
            curl_close($ch);
            throw new moodle_exception('error:curl', 'local_linkedinbadge', '', $error_message);
        }

        curl_close($ch);

        // Parse response
        $token_data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = get_string('error:json_parse', 'local_linkedinbadge', json_last_error_msg());
            \local_linkedinbadge\logger::log('JSON Parse Error', [
                'error' => json_last_error_msg(),
                'raw_response' => $response
            ]);
            throw new moodle_exception('error:json_parse', 'local_linkedinbadge', '', $error_message);
        }

        if (isset($token_data['error'])) {
            $error_message = get_string('error:linkedin_api', 'local_linkedinbadge', $token_data['error']);
            \local_linkedinbadge\logger::log('LinkedIn Error', $token_data);
            throw new moodle_exception('error:linkedin_api', 'local_linkedinbadge', '', $error_message);
        }

        if (isset($token_data['access_token']) && isset($token_data['id_token'])) {
            try {
                // Store access token
                $DB->delete_records('user_preferences', array(
                    'userid' => $USER->id,
                    'name' => 'local_linkedinbadge_linkedin_token'
                ));

                $preference = new \stdClass();
                $preference->userid = $USER->id;
                $preference->name = 'local_linkedinbadge_linkedin_token';
                $preference->value = $token_data['access_token'];

                $DB->insert_record('user_preferences', $preference);

                // Store ID token
                $DB->delete_records('user_preferences', array(
                    'userid' => $USER->id,
                    'name' => 'local_linkedinbadge_linkedin_id_token'
                ));

                $preference = new \stdClass();
                $preference->userid = $USER->id;
                $preference->name = 'local_linkedinbadge_linkedin_id_token';
                $preference->value = $token_data['id_token'];

                $DB->insert_record('user_preferences', $preference);

                \local_linkedinbadge\logger::log('Tokens Stored', [
                    'user_id' => $USER->id,
                    'success' => true
                ]);

                return true;

            } catch (\Exception $e) {
                $error_message = get_string('error:storage', 'local_linkedinbadge', $e->getMessage());
                \local_linkedinbadge\logger::log('Storage Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new moodle_exception('error:storage', 'local_linkedinbadge', '', $error_message);
            }
        }

        $error_message = get_string('error:token_missing', 'local_linkedinbadge', json_encode($token_data));
        \local_linkedinbadge\logger::log('Token Missing', [
            'response_keys' => array_keys($token_data)
        ]);
        throw new moodle_exception('error:token_missing', 'local_linkedinbadge', '', $error_message);
    }
}

