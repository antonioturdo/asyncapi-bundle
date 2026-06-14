<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Discovery;

use Composer\Autoload\ClassLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Resolves the project's own source directories from the Composer PSR-4 map.
 *
 * Excludes vendor and anything outside the project. Used as the default scan
 * scope when no explicit paths are configured. Note: this only decides *where*
 * to look — the FQCNs themselves are read from the source tokens by
 * {@see ClassFinder}, so PSR-4 layout is not assumed.
 */
final class Psr4RootsLocator
{
    /**
     * @return list<string>
     */
    public function liveRoots(string $projectDir): array
    {
        $loader = $this->composerClassLoader();

        return $loader === null ? [] : $this->rootsFrom($loader, $projectDir);
    }

    /**
     * @return list<string>
     */
    public function rootsFrom(ClassLoader $loader, string $projectDir): array
    {
        $project = realpath($projectDir);

        if ($project === false) {
            return [];
        }

        $vendor = $project . \DIRECTORY_SEPARATOR . 'vendor';

        $roots = [];

        foreach ($loader->getPrefixesPsr4() as $paths) {
            foreach ($paths as $path) {
                $real = realpath($path);

                if ($real === false || !is_dir($real)) {
                    continue;
                }

                if (str_starts_with($real, $vendor) || !str_starts_with($real, $project)) {
                    continue;
                }

                $roots[$real] = true;
            }
        }

        $result = array_keys($roots);
        sort($result);

        return $result;
    }

    private function composerClassLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $function) {
            $candidate = \is_array($function) ? $function[0] : $function;

            if ($candidate instanceof DebugClassLoader) {
                $inner = $candidate->getClassLoader();
                $candidate = \is_array($inner) ? $inner[0] : $inner;
            }

            if ($candidate instanceof ClassLoader) {
                return $candidate;
            }
        }

        return null;
    }
}
