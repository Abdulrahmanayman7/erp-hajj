<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=erp_hajj_testing');
putenv('DB_URL=');
$_ENV['DB_DATABASE'] = 'erp_hajj_testing';
$_ENV['DB_CONNECTION'] = 'mysql';

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$db = (string) DB::selectOne('select database() as db')->db;
echo "DB={$db}\n";
if ($db !== 'erp_hajj_testing') {
    exit(2);
}

$ids = DB::table('tenants')->where('tenant_code', 'like', 'prstress-%')->pluck('id')->all();
echo 'tenants='.implode(',', $ids)."\n";

foreach ($ids as $tid) {
    DB::table('purchase_request_version_lines')->where('tenant_id', $tid)->delete();
    DB::table('purchase_request_versions')->where('tenant_id', $tid)->delete();
    DB::table('purchase_request_financial_reviews')->where('tenant_id', $tid)->delete();
    DB::table('purchase_request_lines')->where('tenant_id', $tid)->delete();
    DB::table('purchase_requests')->where('tenant_id', $tid)->delete();
    DB::table('purchase_request_number_sequences')->where('tenant_id', $tid)->delete();
    DB::table('audit_logs')->where('tenant_id', $tid)->delete();
    $rids = DB::table('roles')->where('tenant_id', $tid)->pluck('id')->all();
    if ($rids !== []) {
        DB::table('role_permissions')->whereIn('role_id', $rids)->delete();
        DB::table('user_roles')->whereIn('role_id', $rids)->delete();
        DB::table('roles')->whereIn('id', $rids)->delete();
    }
    $uids = DB::table('users')->where('tenant_id', $tid)->pluck('id')->all();
    if ($uids !== []) {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $uids)
            ->delete();
        DB::table('user_roles')->whereIn('user_id', $uids)->delete();
    }
    DB::table('organization_units')->where('tenant_id', $tid)->delete();
    DB::table('users')->where('tenant_id', $tid)->delete();
    DB::table('tenants')->where('id', $tid)->where('tenant_code', 'like', 'prstress-%')->delete();
}

echo "cleaned\n";
