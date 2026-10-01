<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Master\ProblemCategory;
use App\Models\Master\Approval;
use App\Models\Master\Assignment;
use App\Models\Master\Priority;
use App\Models\Master\Status;
use App\Models\Master\Impact;
use App\Models\Master\Urgency;
use App\Models\Master\Department;
use App\Models\Master\Comment;
use App\Models\Master\TicketHistory;
use App\Models\Master\TicketCategory;
use App\Models\Master\Attachment;

class Ticket extends Model
{
    use HasFactory;
    protected $table = "requests";

    protected $fillable = [
        'ticket_no',
        'requester_id',
        'department_id',
        'category_id',
        'title',
        'description',
        'priority_id',
        'impact_id',
        'urgency_id',
        'status_id',
        'payload',
        'resolved_at',
        'closed_at',
        'ticket_category_id',
        'assigned_user_id',
        'assigned_department_id',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProblemCategory::class, 'category_id');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'request_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(Impact::class, 'impact_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(Urgency::class, 'urgency_id');
    }

    public function histories()
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'request_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'request_id', 'id');
    }

    public function categoryticket()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    protected $casts = [
        'payload' => 'array',
    ];

    public function getRouteKey(): mixed
    {
        return $this->encodeRouteKey((string) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        $id = $this->decodeRouteKey((string) $value) ?? (is_numeric($value) ? (int) $value : null);

        return $id ? $this->whereKey($id)->first() : null;
    }

    private function encodeRouteKey(string $id): string
    {
        $key = $this->routeKeySecret();
        $ciphertext = openssl_encrypt($id, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
        $mac = substr(hash_hmac('sha256', $ciphertext, $key, true), 0, 16);

        return $this->base64UrlEncode($ciphertext.$mac);
    }

    private function decodeRouteKey(string $value): ?int
    {
        $decoded = $this->base64UrlDecode($value);
        if ($decoded === null || strlen($decoded) <= 16) {
            return null;
        }

        $ciphertext = substr($decoded, 0, -16);
        $mac = substr($decoded, -16);
        $key = $this->routeKeySecret();

        if (! hash_equals($mac, substr(hash_hmac('sha256', $ciphertext, $key, true), 0, 16))) {
            return null;
        }

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);

        return is_numeric($decrypted) ? (int) $decrypted : null;
    }

    private function routeKeySecret(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return hash('sha256', $decoded, true);
            }
        }

        return hash('sha256', $key, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    public function customFields()
    {
        return $this->hasMany(Ticket::class);
    }

}
