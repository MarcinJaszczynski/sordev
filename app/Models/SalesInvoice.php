<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    public const TYPE_PROFORMA = 'proforma';

    public const TYPE_ADVANCE = 'advance';

    public const TYPE_FINAL = 'final';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED_LOCAL = 'issued_local';

    public const PROCEDURE_VAT_MARGIN = 'vat_margin';

    protected $fillable = [
        'event_id',
        'client_invoice_request_id',
        'number',
        'type',
        'procedure',
        'status',
        'buyer_name',
        'buyer_nip',
        'buyer_address',
        'revenue_pln',
        'cost_pln',
        'margin_gross_pln',
        'margin_net_pln',
        'vat_on_margin_pln',
        'currency',
            'ksef_number',
        'fakturownia_id',
        'fakturownia_url',
        'fakturownia_synced_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'revenue_pln' => 'decimal:2',
        'cost_pln' => 'decimal:2',
        'margin_gross_pln' => 'decimal:2',
        'margin_net_pln' => 'decimal:2',
        'vat_on_margin_pln' => 'decimal:2',
        'fakturownia_synced_at' => 'datetime',
    ];

    public static array $types = [
        self::TYPE_PROFORMA => 'Proforma',
        self::TYPE_ADVANCE => 'Zaliczkowa',
        self::TYPE_FINAL => 'Końcowa',
    ];

    public static array $statuses = [
        self::STATUS_DRAFT => 'Szkic',
        self::STATUS_ISSUED_LOCAL => 'Wystawiona lokalnie',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function clientInvoiceRequest(): BelongsTo
    {
        return $this->belongsTo(ClientInvoiceRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('sort_order');
    }
}
