<?php
/**
 * Enhanced Logger for Transfer Engine
 * Provides structured logging with multiple levels and outputs
 */

declare(strict_types=1);

class TransferLogger {
    private $session_id;
    private $log_file;
    private $debug_mode;
    private $logs = [];
    
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';
    
    public function __construct($session_id = null, $debug_mode = false) {
        $this->session_id = $session_id ?? 'LOG_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        $this->debug_mode = $debug_mode;
        $this->log_file = __DIR__ . '/logs/transfer_' . date('Y-m-d') . '.log';
        
        // Ensure logs directory exists
        $logs_dir = dirname($this->log_file);
        if (!is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }
        
        $this->log(self::LEVEL_INFO, "Logger initialized", [
            'session_id' => $this->session_id,
            'debug_mode' => $this->debug_mode
        ]);
    }
    
    public function debug($message, $context = []) {
        if ($this->debug_mode) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }
    
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $memory = memory_get_usage(true);
        
        $log_entry = [
            'timestamp' => $timestamp,
            'session_id' => $this->session_id,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_mb' => round($memory / 1024 / 1024, 2)
        ];
        
        // Store in memory
        $this->logs[] = $log_entry;
        
        // Format for file output
        $file_line = "[{$timestamp}] [{$level}] [{$this->session_id}] {$message}";
        if (!empty($context)) {
            $file_line .= ' | Context: ' . json_encode($context);
        }
        $file_line .= " | Memory: {$log_entry['memory_mb']}MB\n";
        
        // Write to file
        file_put_contents($this->log_file, $file_line, FILE_APPEND | LOCK_EX);
        
        // Console output for debug mode
        if ($this->debug_mode) {
            $console_line = "[{$level}] {$message}";
            if (!empty($context)) {
                $console_line .= ' | ' . json_encode($context);
            }
            echo $console_line . "\n";
        }
    }
    
    public function getLogs() {
        return $this->logs;
    }
    
    public function getLogsByLevel($level) {
        return array_filter($this->logs, function($entry) use ($level) {
            return $entry['level'] === $level;
        });
    }
    
    public function getSessionId() {
        return $this->session_id;
    }
    
    public function exportLogs() {
        return [
            'session_id' => $this->session_id,
            'total_logs' => count($this->logs),
            'levels' => $this->getLogLevelCounts(),
            'logs' => $this->logs
        ];
    }
    
    private function getLogLevelCounts() {
        $counts = [];
        foreach ($this->logs as $log) {
            $level = $log['level'];
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }
        return $counts;
    }
}
?>
