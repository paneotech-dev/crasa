<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Apache serves css, js and images without the application, so the
 * headers the SecurityHeaders middleware sends must also be set in
 * public/.htaccess (ZAP baseline, 30 October).
 */
class StaticFileHeadersTest extends TestCase
{
    public function test_static_files_get_nosniff_and_the_same_permissions_policy_as_pages(): void
    {
        $htaccess = File::get(public_path('.htaccess'));

        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $htaccess);

        $page = $this->get('/en')->headers->get('Permissions-Policy');
        $this->assertNotEmpty($page);
        $this->assertStringContainsString('Header always set Permissions-Policy "'.$page.'"', $htaccess);
    }
}
