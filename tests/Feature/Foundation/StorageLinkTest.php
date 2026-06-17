<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class StorageLinkTest extends TestCase
{
    public function test_storage_symlink_exists(): void
    {
        $path = public_path('storage');
        $exists = file_exists($path);
        $this->assertTrue(
            $exists,
            'public/storage path must exist (run `php artisan storage:link` if missing). Path: '.$path
        );
    }
}
