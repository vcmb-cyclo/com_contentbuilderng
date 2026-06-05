<?php declare(strict_types=1);
/**
 * Prune Securimage Audio Directory Script
 *
 * This script recursively deletes all files and subdirectories within the
 * securimage audio directory to remove all audio files for securimage.
 * 
 * Usage: Run from the command line in the Joomla administrator/scripts directory.
 * 
 * WARNING: This operation is destructive and cannot be undone.
 */

$audioPath = dirname(__DIR__) . '/vendor/bgli100/securimage/audio';
if (!is_dir($audioPath)) {
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($audioPath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $item) {
    if ($item->isDir()) {
        // CHILD_FIRST ensures directory contents are deleted before rmdir is called
        rmdir($item->getPathname());
        continue;
    }
    if (!unlink($item->getPathname())) {
        // Handle the error, e.g., log or display a message
        error_log('Failed to delete file: ' . $item->getPathname());
    }
}

if (!rmdir($audioPath)) {
    fwrite(STDERR, "Failed to remove directory: $audioPath\n");
}
