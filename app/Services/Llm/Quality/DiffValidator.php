<?php

namespace App\Services\Llm\Quality;

/**
 * Validates unified diff format output.
 */
class DiffValidator
{
    /**
     * Check if content is valid unified diff
     */
    public function isValidDiff(string $content): bool
    {
        if (empty($content)) {
            return false;
        }
        
        // Must have file markers
        if (!str_contains($content, '---') || !str_contains($content, '+++')) {
            return false;
        }
        
        // Must have at least one hunk header
        if (!preg_match('/@@\s*-\d+,?\d*\s*\+\d+,?\d*\s*@@/', $content)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Extract file paths from diff
     */
    public function extractFilePaths(string $diff): array
    {
        $paths = [];
        
        // Match --- a/path/to/file patterns
        preg_match_all('/^---\s+[ab]\/(.+)$/m', $diff, $oldMatches);
        preg_match_all('/^\+\+\+\s+[ab]\/(.+)$/m', $diff, $newMatches);
        
        foreach ($oldMatches[1] as $path) {
            if ($path !== '/dev/null') {
                $paths[] = $path;
            }
        }
        
        foreach ($newMatches[1] as $path) {
            if ($path !== '/dev/null' && !in_array($path, $paths)) {
                $paths[] = $path;
            }
        }
        
        return $paths;
    }
    
    /**
     * Check if diff contains full file rewrites (bad practice)
     */
    public function hasFullFileRewrite(string $diff): bool
    {
        // Count total lines changed
        $additions = substr_count($diff, "\n+");
        $deletions = substr_count($diff, "\n-");
        
        // If more than 200 lines changed in a single hunk, likely full rewrite
        $hunks = preg_split('/@@\s*-\d+,?\d*\s*\+\d+,?\d*\s*@@/', $diff);
        
        foreach ($hunks as $hunk) {
            $hunkAdditions = substr_count($hunk, "\n+");
            $hunkDeletions = substr_count($hunk, "\n-");
            
            if ($hunkAdditions > 200 || $hunkDeletions > 200) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get validation issues
     */
    public function getIssues(string $content): array
    {
        $issues = [];
        
        if (empty($content)) {
            $issues[] = 'Output is empty';
            return $issues;
        }
        
        if (!str_contains($content, '---')) {
            $issues[] = 'Missing --- file marker';
        }
        
        if (!str_contains($content, '+++')) {
            $issues[] = 'Missing +++ file marker';
        }
        
        if (!preg_match('/@@\s*-\d+,?\d*\s*\+\d+,?\d*\s*@@/', $content)) {
            $issues[] = 'Missing @@ hunk header';
        }
        
        if ($this->hasFullFileRewrite($content)) {
            $issues[] = 'Contains full file rewrite - should be minimal patch';
        }
        
        // Check for prose/explanation outside diff
        $lines = explode("\n", $content);
        $proseLines = 0;
        foreach ($lines as $line) {
            if (!empty($line) && 
                !str_starts_with($line, '---') &&
                !str_starts_with($line, '+++') &&
                !str_starts_with($line, '@@') &&
                !str_starts_with($line, '+') &&
                !str_starts_with($line, '-') &&
                !str_starts_with($line, ' ') &&
                !str_starts_with($line, 'diff ')) {
                $proseLines++;
            }
        }
        
        if ($proseLines > 5) {
            $issues[] = 'Contains excessive prose/explanation text';
        }
        
        return $issues;
    }
}

