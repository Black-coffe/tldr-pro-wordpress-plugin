<?php
/**
 * PO to MO Compiler for TL;DR Pro
 * 
 * Converts .po translation files to binary .mo format
 * Supports proper UTF-8 encoding and plural forms
 */

class POtoMOCompiler {
    
    private $entries = [];
    private $headers = [];
    private $plural_forms = '';
    
    /**
     * Convert PO file to MO file
     */
    public function compile($po_file, $mo_file = null) {
        if (!file_exists($po_file)) {
            echo "Error: PO file not found: $po_file\n";
            return false;
        }
        
        // Determine output file
        if ($mo_file === null) {
            $mo_file = str_replace('.po', '.mo', $po_file);
        }
        
        echo "Compiling: " . basename($po_file) . " -> " . basename($mo_file) . "\n";
        
        // Parse PO file
        if (!$this->parsePO($po_file)) {
            echo "Error: Failed to parse PO file\n";
            return false;
        }
        
        // Write MO file
        if (!$this->writeMO($mo_file)) {
            echo "Error: Failed to write MO file\n";
            return false;
        }
        
        $size = filesize($mo_file);
        echo "Success: Created {$mo_file} ({$size} bytes)\n";
        echo "Entries compiled: " . count($this->entries) . "\n\n";
        
        return true;
    }
    
    /**
     * Parse PO file
     */
    private function parsePO($filename) {
        $this->entries = [];
        $this->headers = [];
        
        $content = file_get_contents($filename);
        if ($content === false) {
            return false;
        }
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        }
        
        $lines = explode("\n", $content);
        $current_msgid = '';
        $current_msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
        $is_header = true;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') {
                if ($in_msgstr && !empty($current_msgid)) {
                    // Save previous entry
                    if ($is_header && empty($current_msgid)) {
                        // Parse headers from first empty msgid entry
                        $this->parseHeaders($current_msgstr);
                        $is_header = false;
                    } else {
                        $this->entries[$current_msgid] = $current_msgstr;
                    }
                    $current_msgid = '';
                    $current_msgstr = '';
                    $in_msgid = false;
                    $in_msgstr = false;
                }
                continue;
            }
            
            // Handle msgid
            if (strpos($line, 'msgid "') === 0) {
                if ($in_msgstr && !empty($current_msgid)) {
                    // Save previous entry
                    $this->entries[$current_msgid] = $current_msgstr;
                }
                $current_msgid = $this->extractString($line, 'msgid');
                $current_msgstr = '';
                $in_msgid = true;
                $in_msgstr = false;
            }
            // Handle msgstr
            elseif (strpos($line, 'msgstr "') === 0) {
                $current_msgstr = $this->extractString($line, 'msgstr');
                $in_msgid = false;
                $in_msgstr = true;
            }
            // Handle continued strings
            elseif ($line[0] === '"') {
                $value = substr($line, 1, -1);
                $value = $this->unescapeString($value);
                
                if ($in_msgid) {
                    $current_msgid .= $value;
                } elseif ($in_msgstr) {
                    $current_msgstr .= $value;
                }
            }
        }
        
        // Save last entry if exists
        if ($in_msgstr && !empty($current_msgid)) {
            $this->entries[$current_msgid] = $current_msgstr;
        }
        
        return true;
    }
    
    /**
     * Extract string from msgid/msgstr line
     */
    private function extractString($line, $prefix) {
        $start = strlen($prefix) + 2; // +2 for space and opening quote
        $end = strrpos($line, '"');
        if ($end === false || $end <= $start) {
            return '';
        }
        $value = substr($line, $start, $end - $start);
        return $this->unescapeString($value);
    }
    
    /**
     * Unescape PO string
     */
    private function unescapeString($str) {
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\"' => '"',
            '\\\\' => '\\'
        ];
        return strtr($str, $replacements);
    }
    
    /**
     * Parse headers from first empty msgid
     */
    private function parseHeaders($header_str) {
        $lines = explode("\n", $header_str);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $this->headers[trim($key)] = trim($value);
                
                if (trim($key) === 'Plural-Forms') {
                    $this->plural_forms = trim($value);
                }
            }
        }
    }
    
    /**
     * Write MO file
     */
    private function writeMO($filename) {
        $mo = fopen($filename, 'wb');
        if (!$mo) {
            return false;
        }
        
        // Sort entries by key for binary search
        ksort($this->entries);
        
        $offsets = [];
        $ids = '';
        $strs = '';
        
        // Build strings
        foreach ($this->entries as $id => $str) {
            $offsets[] = [
                strlen($ids), strlen($id),
                strlen($strs), strlen($str)
            ];
            $ids .= $id . "\0";
            $strs .= $str . "\0";
        }
        
        $count = count($this->entries);
        $table_offset = 28; // Header size
        $ids_offset = $table_offset + $count * 8 * 2;
        $strs_offset = $ids_offset + strlen($ids);
        
        // Write header
        fwrite($mo, pack('L', 0x950412de)); // Magic number
        fwrite($mo, pack('L', 0)); // Version
        fwrite($mo, pack('L', $count)); // Number of strings
        fwrite($mo, pack('L', $table_offset)); // Offset of table with original strings
        fwrite($mo, pack('L', $table_offset + $count * 8)); // Offset of table with translation strings
        fwrite($mo, pack('L', 0)); // Size of hashing table
        fwrite($mo, pack('L', $ids_offset + strlen($ids) + strlen($strs))); // Offset of hashing table
        
        // Write original string table
        foreach ($offsets as $offset) {
            fwrite($mo, pack('L', $offset[1])); // Length
            fwrite($mo, pack('L', $ids_offset + $offset[0])); // Offset
        }
        
        // Write translation string table
        foreach ($offsets as $offset) {
            fwrite($mo, pack('L', $offset[3])); // Length
            fwrite($mo, pack('L', $strs_offset + $offset[2])); // Offset
        }
        
        // Write strings
        fwrite($mo, $ids);
        fwrite($mo, $strs);
        
        fclose($mo);
        return true;
    }
}

// Run compiler
echo "===========================================\n";
echo "   TL;DR Pro - PO to MO Compiler\n";
echo "===========================================\n\n";

$compiler = new POtoMOCompiler();
$languages_dir = dirname(__FILE__);

// Compile all PO files in the directory
$po_files = glob($languages_dir . '/*.po');

if (empty($po_files)) {
    echo "No PO files found in: $languages_dir\n";
    exit(1);
}

$success_count = 0;
$fail_count = 0;

foreach ($po_files as $po_file) {
    if ($compiler->compile($po_file)) {
        $success_count++;
    } else {
        $fail_count++;
    }
}

echo "===========================================\n";
echo "Compilation complete!\n";
echo "Success: $success_count files\n";
if ($fail_count > 0) {
    echo "Failed: $fail_count files\n";
}
echo "===========================================\n";