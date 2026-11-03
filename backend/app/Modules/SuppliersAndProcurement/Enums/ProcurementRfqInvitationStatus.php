<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum ProcurementRfqInvitationStatus: string
{
    case Invited = 'invited';
    case Responded = 'responded';
    case Declined = 'declined';
    case Excluded = 'excluded';
}
