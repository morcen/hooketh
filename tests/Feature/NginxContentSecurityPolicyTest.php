<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression coverage for a fix to docker/nginx.conf where the
 * Content-Security-Policy header whitelisted the bare `http:`/`https:`
 * schemes (which match *any* origin on that scheme) and set
 * 'unsafe-inline' on default-src, so it permitted inline scripts.
 * Together that made the header block essentially nothing an XSS
 * payload would need.
 *
 * nginx isn't running under the test suite, so this parses the
 * committed config and asserts the shipped CSP is actually
 * restrictive, catching a regression without needing a live server.
 */
class NginxContentSecurityPolicyTest extends TestCase
{
    public function test_csp_header_does_not_whitelist_bare_http_or_https_schemes(): void
    {
        $csp = $this->cspDirectives();

        foreach ($csp as $directive => $sources) {
            $this->assertNotContains('http:', $sources, "{$directive} must not whitelist the bare http: scheme (matches any origin)");
            $this->assertNotContains('https:', $sources, "{$directive} must not whitelist the bare https: scheme (matches any origin)");
        }
    }

    public function test_script_src_does_not_allow_unsafe_inline_or_unsafe_eval(): void
    {
        $csp = $this->cspDirectives();

        $this->assertArrayHasKey('script-src', $csp, 'CSP must define an explicit script-src directive');
        $this->assertNotContains("'unsafe-inline'", $csp['script-src']);
        $this->assertNotContains("'unsafe-eval'", $csp['script-src']);
    }

    public function test_default_src_is_restricted_to_self(): void
    {
        $csp = $this->cspDirectives();

        $this->assertSame(["'self'"], $csp['default-src'] ?? null);
    }

    /**
     * @return array<string, string[]>
     */
    private function cspDirectives(): array
    {
        $config = file_get_contents(base_path('docker/nginx.conf'));

        $this->assertNotFalse($config, 'Could not read docker/nginx.conf');

        preg_match('/Content-Security-Policy\s+"([^"]+)"/', $config, $matches);

        $this->assertNotEmpty($matches, 'docker/nginx.conf must define a Content-Security-Policy header');

        $directives = [];

        foreach (explode(';', $matches[1]) as $directive) {
            $parts = preg_split('/\s+/', trim($directive));
            $name = array_shift($parts);

            if ($name === null || $name === '') {
                continue;
            }

            $directives[$name] = $parts;
        }

        return $directives;
    }
}
