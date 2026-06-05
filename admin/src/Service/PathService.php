<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

class PathService
{
    public function makeSafeFolder($path): string
    {
        $path = $this->normalizePathSeparators((string) $path);
        $path = preg_replace('#[^A-Za-z0-9\.:_/\-]#', '_', $path) ?? $path;

        $segments = explode('/', $path);
        $safeSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                continue;
            }

            $segment = ltrim($segment, '.');

            if ($segment === '') {
                continue;
            }

            $safeSegments[] = $segment;
        }

        $rebuilt = implode('/', $safeSegments);

        if (strpos($path, '/') === 0) {
            return '/' . $rebuilt;
        }

        if (preg_match('#^[A-Za-z]:/#', $path)) {
            $drive = substr($path, 0, 2);

            return $drive . '/' . preg_replace('#^[A-Za-z]:/#', '', $rebuilt);
        }

        return $rebuilt;
    }

    private function normalizePathSeparators(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_replace('#/+#', '/', $path) ?? $path;
    }
}
