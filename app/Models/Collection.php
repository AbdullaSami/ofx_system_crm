<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'client_id',
        'amount_due',
        'amount_collected',
        'due_date',
        'collection_date',
        'status',
        'is_written_off',
        'written_off_date',
        'payment_method',
        'reference_number',
        'notes',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_collection_pivot', 'collection_id', 'service_id');
    }

    public static function exceedsContractAmount(
        int $contractId,
        float $amount,
        string $column = 'amount_due',
        ?int $ignoreCollectionId = null
    ): bool {
        $query = self::where('contract_id', $contractId);

        // Ignore current collection when updating
        if ($ignoreCollectionId) {
            $query->where('id', '!=', $ignoreCollectionId);
        }

        $total = $query->sum($column);

        $contract = Contract::findOrFail($contractId);

        return ($total + $amount) > $contract->amount;
    }
}
