<?php

namespace App\Events;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnRequestStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}
}
