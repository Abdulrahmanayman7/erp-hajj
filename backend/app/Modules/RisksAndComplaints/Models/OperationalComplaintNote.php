<?php

namespace App\Modules\RisksAndComplaints\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Database\Factories\OperationalComplaintNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only complaint follow-up note.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $complaint_id
 * @property string $body
 * @property int $created_by
 */
class OperationalComplaintNote extends Model implements TenantOwned
{
    /** @use HasFactory<OperationalComplaintNoteFactory> */
    use HasFactory;

    use UsesTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'complaint_id',
        'body',
        'created_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): OperationalComplaintNoteFactory
    {
        return OperationalComplaintNoteFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (OperationalComplaintNote $note): void {
            if ($note->getAttribute('created_at') === null) {
                $note->setAttribute('created_at', now());
            }
        });
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(OperationalComplaint::class, 'complaint_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
