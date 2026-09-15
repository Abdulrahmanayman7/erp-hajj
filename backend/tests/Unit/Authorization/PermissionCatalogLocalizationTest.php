<?php

use App\Core\Authorization\PermissionCatalog;

test('permission catalog descriptions are Arabic for UI display', function (): void {
    foreach (PermissionCatalog::definitions() as $definition) {
        $description = $definition['description'] ?? null;
        expect($description)->not->toBeNull();
        expect($description)->toMatch('/\p{Arabic}/u');
        expect($description)->not->toMatch('/\b(List|Create|Update|Delete|View|Manage)\b/');
    }
});
