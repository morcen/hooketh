<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression coverage for issue #141: .dockerignore excluded composer.lock
 * and package-lock.json from the Docker build context, so `composer
 * install` and `npm install` in the Dockerfile had no lockfile to install
 * from and resolved dependency versions fresh against version constraints
 * at build time instead of the exact, tested versions committed to the
 * repo - defeating the point of a lockfile and risking supply-chain drift
 * between what's tested and what's deployed.
 *
 * Docker isn't available under the test suite, so this parses the
 * committed .dockerignore/Dockerfile and asserts the lockfiles are part of
 * the build context and actually used, catching a regression without
 * needing to build an image.
 */
class DockerBuildLockfileReproducibilityTest extends TestCase
{
    public function test_dockerignore_does_not_exclude_lockfiles(): void
    {
        $dockerignore = file_get_contents(base_path('.dockerignore'));

        $this->assertNotFalse($dockerignore, 'Could not read .dockerignore');

        foreach (['composer.lock', 'package-lock.json', 'yarn.lock'] as $lockfile) {
            $this->assertDoesNotMatchRegularExpression(
                '/^'.preg_quote($lockfile, '/').'$/m',
                $dockerignore,
                "{$lockfile} must be part of the Docker build context so installs are reproducible"
            );
        }
    }

    public function test_node_build_stage_copies_package_lock_and_uses_npm_ci(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertNotFalse($dockerfile, 'Could not read Dockerfile');

        $nodeStage = $this->extractStage($dockerfile, 'node-build');

        $this->assertMatchesRegularExpression(
            '/^COPY .*package-lock\.json.*$/m',
            $nodeStage,
            'The node-build stage must copy package-lock.json into the build context'
        );

        $this->assertMatchesRegularExpression(
            '/npm ci\b/',
            $nodeStage,
            'The node-build stage must use `npm ci`, which requires and honors package-lock.json, instead of the unlocked `npm install`'
        );
    }

    private function extractStage(string $dockerfile, string $stageName): string
    {
        $pattern = '/FROM \S+ AS '.preg_quote($stageName, '/').'\b(.*?)(?=\nFROM |\z)/s';

        $this->assertMatchesRegularExpression($pattern, $dockerfile, "Could not find the {$stageName} stage in the Dockerfile");

        preg_match($pattern, $dockerfile, $matches);

        return $matches[0];
    }
}
