<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| All Feature tests extend the Laravel TestCase so the application is
| booted for HTTP tests. Unit tests stay framework-free by default.
|
*/

pest()->extend(TestCase::class)->in('Feature');
