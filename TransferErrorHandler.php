<?php
/**
 * Enhanced Error Handler for Transfer Engine
 * Provides structured error handling with recovery mechanisms
 */

declare(strict_types=1);

class TransferErrorHandler {
    private $logger;
    private $error_counts = [];
    private $recovery_attempts = [];
    
    public function __construct(TransferLogger $logger) {
        $this->logger = $logger;
        
        // Set error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }
    
    public function handleError($severity, $message, $file, $line) {
        $error_type = $this->getSeverityName($severity);
        
        $this->error_counts[$error_type] = ($this->error_counts[$error_type] ?? 0) + 1;
        
        $context = [
            'severity' => $severity,
            'file' => basename($file),
            'line' => $line,
            'error_count' => $this->error_counts[$error_type]
        ];
        
        // Log based on severity
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $this->logger->critical("PHP Error: {$message}", $context);
                break;
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $this->logger->warning("PHP Warning: {$message}", $context);
                break;
                
            case E_NOTICE:
            case E_USER_NOTICE:
                $this->logger->info("PHP Notice: {$message}", $context);
                break;
                
            default:
                $this->logger->debug("PHP {$error_type}: {$message}", $context);
        }
        
        // Don't execute PHP's internal error handler
        return true;
    }
    
    public function handleException(Throwable $exception) {
        $context = [
            'type' => get_class($exception),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $this->formatStackTrace($exception->getTrace())
        ];
        
        $this->logger->critical("Uncaught Exception: {$exception->getMessage()}", $context);
        
        // Try to provide a user-friendly error response
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'System error occurred',
                'error_id' => $this->logger->getSessionId(),
                'timestamp' => date('c')
            ]);
        }
        
        exit(1);
    }
    
    public function wrapDatabaseOperation(callable $operation, $operation_name = 'database operation') {
        $max_retries = 3;
        $retry_delay = 1; // seconds
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            try {
                $this->logger->debug("Attempting {$operation_name}", ['attempt' => $attempt]);
                
                $result = $operation();
                
                if ($attempt > 1) {
                    $this->logger->info("Database operation succeeded after retry", [
                        'operation' => $operation_name,
                        'attempt' => $attempt
                    ]);
                }
                
                return $result;
                
            } catch (Exception $e) {
                $this->logger->warning("Database operation failed", [
                    'operation' => $operation_name,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt === $max_retries) {
                    $this->logger->error("Database operation failed after all retries", [
                        'operation' => $operation_name,
                        'max_retries' => $max_retries,
                        'final_error' => $e->getMessage()
                    ]);
                    throw $e;
                }
                
                // Wait before retry
                sleep($retry_delay * $attempt);
            }
        }
    }
    
    public function validateInput($data, $rules, $operation_name = 'input validation') {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "Field {$field} is required";
                continue;
            }
            
            // Type check
            if (!empty($value) && isset($rule['type'])) {
                if (!$this->validateType($value, $rule['type'])) {
                    $errors[$field] = "Field {$field} must be of type {$rule['type']}";
                }
            }
            
            // Length check
            if (!empty($value) && isset($rule['max_length'])) {
                if (strlen((string)$value) > $rule['max_length']) {
                    $errors[$field] = "Field {$field} exceeds maximum length of {$rule['max_length']}";
                }
            }
            
            // Custom validation
            if (!empty($value) && isset($rule['validator']) && is_callable($rule['validator'])) {
                $validation_result = $rule['validator']($value);
                if ($validation_result !== true) {
                    $errors[$field] = $validation_result;
                }
            }
        }
        
        if (!empty($errors)) {
            $this->logger->warning("Input validation failed", [
                'operation' => $operation_name,
                'errors' => $errors,
                'data_keys' => array_keys($data)
            ]);
            
            throw new InvalidArgumentException('Input validation failed: ' . implode(', ', $errors));
        }
        
        $this->logger->debug("Input validation passed", [
            'operation' => $operation_name,
            'fields_validated' => array_keys($rules)
        ]);
        
        return true;
    }
    
    private function validateType($value, $expected_type) {
        switch ($expected_type) {
            case 'string':
                return is_string($value);
            case 'int':
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
            case 'double':
                return is_float($value) || is_numeric($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || in_array($value, ['0', '1', 'true', 'false'], true);
            case 'array':
                return is_array($value);
            case 'uuid':
                return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
            default:
                return true;
        }
    }
    
    private function getSeverityName($severity) {
        $levels = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $levels[$severity] ?? 'UNKNOWN';
    }
    
    private function formatStackTrace($trace) {
        $formatted = [];
        foreach (array_slice($trace, 0, 5) as $frame) { // Limit to top 5 frames
            $formatted[] = sprintf(
                "%s%s%s() in %s:%d",
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '',
                basename($frame['file'] ?? ''),
                $frame['line'] ?? 0
            );
        }
        return $formatted;
    }
    
    public function getErrorCounts() {
        return $this->error_counts;
    }
}
?>
