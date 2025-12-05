<?php
if (!defined('ABSPATH')) exit;

class GSC_Controller_Log extends GSC_Controller_Base {
    
    private $log_dir;
    private $log_enabled = true;
    
    protected function __construct() {
        $this->log_dir = GSC_PATH . 'logs/';
        $this->init_log_dir();
        $this->log_enabled = apply_filters('gsc_log_enabled', true);
    }
    
    private function init_log_dir() {
        if (!file_exists($this->log_dir)) {
            if (!wp_mkdir_p($this->log_dir)) {
                $this->log_enabled = false;
                error_log('GSC Log: Cannot create log directory: ' . $this->log_dir);
                return;
            }
            
            @chmod($this->log_dir, 0755);
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            $index_content = "<?php\n// Silence is golden";
            
            @file_put_contents($this->log_dir . '.htaccess', $htaccess_content);
            @file_put_contents($this->log_dir . 'index.php', $index_content);
            
            if (!is_writable($this->log_dir)) {
                $this->log_enabled = false;
                error_log('GSC Log: Log directory not writable: ' . $this->log_dir);
            }
        }
    }
    
    public function log_message($message, $type = 'info', $context = []) {
        if (!$this->log_enabled) {
            return false;
        }
        
        if (!file_exists($this->log_dir)) {
            if (!wp_mkdir_p($this->log_dir)) {
                error_log('GSC: Cannot create log directory: ' . $this->log_dir);
                return false;
            }
            @file_put_contents($this->log_dir . '.htaccess', "Order Deny,Allow\nDeny from all");
            @file_put_contents($this->log_dir . 'index.php', "<?php\n// Silence is golden");
        }
        
        if (!is_writable($this->log_dir)) {
            error_log('GSC: Log directory not writable: ' . $this->log_dir);
            return false;
        }
        
        $date = current_time('Y-m-d');
        $log_file = $this->log_dir . $date . '.txt';
        
        $timestamp = current_time('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_id = get_current_user_id();
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        $log_entry = sprintf(
            "[%s] [%s] [IP: %s] [UserID: %d] [URI: %s] %s",
            $timestamp,
            strtoupper($type),
            $ip_address,
            $user_id,
            $request_uri,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= " [Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "]";
        }
        
        $log_entry .= PHP_EOL;

        if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) {
            $backup_file = $this->log_dir . $date . '_' . time() . '.txt';
            @rename($log_file, $backup_file);
        }
        
        $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GSC Log [' . strtoupper($type) . ']: ' . $message);
            if (!empty($context)) {
                error_log('GSC Log Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE));
            }
        }
        
        if ($result === false) {
            $this->log_enabled = false;
            error_log('GSC: Failed to write log: ' . $log_file);
            return false;
        }
        
        return true;
    }
    
    public function read($date = null, $limit = 100) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $log_file = $this->log_dir . $date . '.txt';
        
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (empty($lines)) {
            return [];
        }
        
        $logs = [];
        $lines = array_reverse($lines);
        
        $count = 0;
        foreach ($lines as $line) {
            if ($count >= $limit) break;
            
            $log_entry = $this->parse_log_line($line);
            if ($log_entry) {
                $logs[] = $log_entry;
                $count++;
            }
        }
        
        return $logs;
    }
    
    private function parse_log_line($line) {
        $pattern = '/\[(?P<timestamp>[^\]]+)\] \[(?P<type>[^\]]+)\] \[IP: (?P<ip>[^\]]+)\] \[UserID: (?P<user_id>\d+)\] \[URI: (?P<uri>[^\]]+)\] (?P<message>.+?)(?: \[Context: (?P<context>.+)\])?$/';
        
        if (preg_match($pattern, $line, $matches)) {
            $entry = [
                'timestamp' => $matches['timestamp'],
                'type' => $matches['type'],
                'ip' => $matches['ip'],
                'user_id' => intval($matches['user_id']),
                'uri' => $matches['uri'],
                'message' => $matches['message'],
                'raw' => $line
            ];
            
            if (!empty($matches['context'])) {
                $context = json_decode($matches['context'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entry['context'] = $context;
                }
            }
            
            return $entry;
        }

        return [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'type' => 'UNKNOWN',
            'ip' => '0.0.0.0',
            'user_id' => 0,
            'uri' => '',
            'message' => $line,
            'raw' => $line
        ];
    }
    
    public function get_dates() {
        $dates = [];
        
        if (!file_exists($this->log_dir) || !is_readable($this->log_dir)) {
            return $dates;
        }
        
        $files = @glob($this->log_dir . '*.txt');
        
        if (!$files) {
            return $dates;
        }
        
        foreach ($files as $file) {
            $filename = basename($file, '.txt');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filename)) {
                $dates[] = $filename;
            }
        }
        
        rsort($dates);
        
        return $dates;
    }
    
    public function clear_old_logs($days = 30) {
        if (!file_exists($this->log_dir) || !is_writable($this->log_dir)) {
            return 0;
        }
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        $files = @glob($this->log_dir . '*.txt');
        
        if (!$files) {
            return 0;
        }
        
        $deleted = 0;
        
        foreach ($files as $file) {
            $filename = basename($file, '.txt');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filename) && $filename < $cutoff_date) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    public function get_stats() {
        $stats = [
            'total_logs' => 0,
            'today_logs' => 0,
            'log_files' => 0,
            'oldest_log' => null,
            'newest_log' => null
        ];
        
        if (!file_exists($this->log_dir) || !is_readable($this->log_dir)) {
            return $stats;
        }
        
        $files = @glob($this->log_dir . '*.txt');
        
        if (!$files) {
            return $stats;
        }
        
        $stats['log_files'] = count($files);
        
        $dates = $this->get_dates();
        if (!empty($dates)) {
            $stats['oldest_log'] = end($dates);
            $stats['newest_log'] = reset($dates);
            
            $today_file = $this->log_dir . current_time('Y-m-d') . '.txt';
            if (file_exists($today_file) && is_readable($today_file)) {
                $lines = file($today_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $stats['today_logs'] = $lines ? count($lines) : 0;
            }
            
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $stats['total_logs'] += $lines ? count($lines) : 0;
                }
            }
        }
        
        return $stats;
    }
    
    public static function info($message, $context = []) {
        return self::instance()->log_message($message, 'info', $context);
    }
    
    public static function error($message, $context = []) {
        return self::instance()->log_message($message, 'error', $context);
    }
    
    public static function warning($message, $context = []) {
        return self::instance()->log_message($message, 'warning', $context);
    }
    
    public static function success($message, $context = []) {
        return self::instance()->log_message($message, 'success', $context);
    }
    
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return self::instance()->log_message($message, 'debug', $context);
        }
        return false;
    }
    
    public static function db($message, $context = []) {
        return self::instance()->log_message($message, 'database', $context);
    }
    
    public static function payment($message, $context = []) {
        return self::instance()->log_message($message, 'payment', $context);
    }
    
    public static function sync($message, $context = []) {
        return self::instance()->log_message($message, 'sync', $context);
    }
}
?>
