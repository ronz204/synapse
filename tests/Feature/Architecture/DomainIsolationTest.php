<?php

declare(strict_types=1);

/**
 * Structural budget S-06 — Principle I of the constitution, enforced as a test
 * rather than as a code-review habit.
 *
 * Performance work is exactly when this boundary is most likely to be crossed
 * by accident: reaching for a Cache facade inside a domain service, or letting
 * an Eloquent Collection leak into an entity, both look like reasonable
 * optimisations in the moment. Deleting the framework must still leave the
 * domain compiling.
 */
function domainPhpFiles(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('src'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());

        if (str_contains($path, '/Domain/')) {
            $files[] = $path;
        }
    }

    sort($files);

    return $files;
}

it('finds domain files to check', function (): void {
    // A guard that silently checks nothing is worse than no guard: if the
    // directory layout changes, this fails loudly instead of passing empty.
    expect(domainPhpFiles())->not->toBeEmpty();
});

it('has no framework imports anywhere under src/**/Domain', function (): void {
    $violations = [];

    foreach (domainPhpFiles() as $path) {
        $contents = file_get_contents($path);

        preg_match_all('/^\s*use\s+(Illuminate|Livewire)\\\\[^;]+;/m', $contents, $matches);

        foreach ($matches[0] as $match) {
            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).' → '.trim($match);
        }
    }

    expect($violations)->toBe([], "Framework imports found under src/**/Domain:\n".implode("\n", $violations));
});

it('has no cache usage in the equivalency module', function (): void {
    // Principle II and research decision D-06. Caching activeGraph() between
    // requests is the optimisation everyone proposes in good faith; a stale
    // graph lets a cycle through, and the failure is silent. Within-request
    // memoisation is fine — a Cache facade call is not.
    $offenders = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('src/Curriculum/Equivalency'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (preg_match('/Cache::|cache\(\)|Cache\\\\Repository/', $contents) === 1) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([], "Cross-request caching found in the Equivalency module:\n".implode("\n", $offenders));
});
