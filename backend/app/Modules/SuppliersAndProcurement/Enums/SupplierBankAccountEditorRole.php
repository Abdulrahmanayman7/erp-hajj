<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum SupplierBankAccountEditorRole: string
{
    case Creator = 'creator';
    case Editor = 'editor';
}
