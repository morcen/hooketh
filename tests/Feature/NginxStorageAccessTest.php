<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression coverage for a fix to docker/nginx.conf where the
 * `location ^~ /storage { deny all; }` block used the `^~` prefix
 * modifier. That modifier tells nginx to stop searching once this
 * prefix location matches and to skip evaluating any regex locations,
 * which permanently shadowed the `location ~* /storage/....(jpg|...)$`
 * allow-list below it: every request under /storage, including public
 * files like Jetstream profile photos, was denied with a 403.
 *
 * nginx isn't running under the test suite, so this parses the
 * committed config and asserts the storage deny rule no longer uses
 * `^~`, catching a regression without needing a live server.
 */
class NginxStorageAccessTest extends TestCase
{
    public function test_storage_deny_rule_does_not_shadow_the_public_file_allow_list(): void
    {
        $config = file_get_contents(base_path('docker/nginx.conf'));

        $this->assertNotFalse($config, 'Could not read docker/nginx.conf');

        $this->assertDoesNotMatchRegularExpression(
            '/location\s+\^~\s+\/storage\b/',
            $config,
            'The /storage deny-all location must not use the `^~` prefix modifier, '.
            'or it will shadow the public-file allow-list regex location below it '.
            'and 403 every request under /storage, including public profile photos.'
        );

        $this->assertMatchesRegularExpression(
            '/location\s+\/storage\s*\{\s*deny all;/',
            $config,
            'docker/nginx.conf must still deny /storage by default for paths that are not public files'
        );

        $this->assertMatchesRegularExpression(
            '/location\s+~\*\s+\/storage\/.*\\\\\.\(jpg\|jpeg\|png\|gif\|ico\|svg\|pdf\)\$/',
            $config,
            'docker/nginx.conf must still allow public storage files by extension'
        );
    }
}
