<?php
namespace local_linkedinbadge;

defined('MOODLE_INTERNAL') || die();

class logger {
    /**
     * Log a message
     */
    public static function log($message, $data = null) {
        global $CFG;
        
        try {
            // Create log directory if it doesn't exist
            $log_dir = $CFG->dataroot . '/linkedin_logs';
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0777, true);
            }
            
            $log_file = $log_dir . '/linkedin.log';
            
            // Create log entry
            $log_entry = date('Y-m-d H:i:s') . " | ";
            $log_entry .= $message;
            
            if ($data !== null) {
                if (is_array($data) || is_object($data)) {
                    $log_entry .= "\nData: " . print_r($data, true);
                } else {
                    $log_entry .= "\nData: " . strval($data);
                }
            }
            
            $log_entry .= "\n----------------------------------------\n";
            
            // Write to log file
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
        } catch (\Exception $e) {
            debugging('Logger error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
