<?php

return [
    /*
    | Optional email of an existing tenant user to receive tenant_owner on seed.
    | Never invent passwords. Leave null when no suitable user exists.
    */
    'initial_owner_email' => env('RBAC_INITIAL_OWNER_EMAIL'),
];
