<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Discovery;

use Symfony\Component\Finder\Finder;

/**
 * Enumerates the fully-qualified class names declared under the given paths.
 *
 * This is the "find the candidate classes" half of discovery — deliberately
 * unaware of any specific attribute (the name to look for is passed in) and of
 * how the attribute is read. The FQCN is extracted from the source tokens
 * rather than derived from the path, so classes that do not follow PSR-4
 * (file name ≠ class name) are still found correctly.
 */
final class ClassFinder
{
    /**
     * @param list<string> $paths         Directories to scan recursively.
     * @param list<string> $excludeDirs   Directory names to skip (e.g. "tests").
     * @param string|null  $attributeName Optional attribute short name to pre-filter files.
     * @return list<string>               Declared FQCNs, de-duplicated and sorted.
     */
    public function find(array $paths, array $excludeDirs = [], ?string $attributeName = null): array
    {
        $dirs = array_values(array_filter($paths, 'is_dir'));

        if ($dirs === []) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($dirs)->name('*.php');

        foreach ($excludeDirs as $dir) {
            $finder->exclude($dir);
        }

        $needle = $attributeName === null ? null : $this->shortName($attributeName);

        $found = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            if ($needle !== null && !str_contains($contents, $needle)) {
                continue;
            }

            $fqcn = $this->extractFqcn($contents);

            if ($fqcn !== null) {
                $found[] = $fqcn;
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    private function extractFqcn(string $contents): ?string
    {
        $tokens = \PhpToken::tokenize($contents);
        $count = \count($tokens);
        $namespace = '';

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE)) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $name = $tokens[$j];
                    if ($name->text === ';' || $name->text === '{') {
                        break;
                    }
                    if ($name->is([T_STRING, T_NAME_QUALIFIED])) {
                        $namespace = $name->text;
                        break;
                    }
                }

                continue;
            }

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && !$this->isReferenceNotDeclaration($tokens, $i)) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->is(T_STRING)) {
                        return $namespace !== '' ? $namespace . '\\' . $tokens[$j]->text : $tokens[$j]->text;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Tells apart a type declaration from `Foo::class` and `new class {...}`.
     *
     * @param array<int, \PhpToken> $tokens
     */
    private function isReferenceNotDeclaration(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            if ($tokens[$j]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                continue;
            }

            return $tokens[$j]->is([T_DOUBLE_COLON, T_NEW]);
        }

        return false;
    }

    private function shortName(string $name): string
    {
        $name = ltrim($name, '\\');
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }
}
