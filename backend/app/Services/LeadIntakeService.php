<?php

namespace App\Services;

use App\Jobs\DeliverLeadJob;
use App\Models\Lead;

class LeadIntakeService
{
    /**
     * @var array<int, string>
     */
    private const TRIMMABLE_FIELDS = [
        'name',
        'email',
        'company',
        'company_registration',
        'phone',
        'sector',
        'location',
        'quantity',
        'message',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Lead
    {
        $lead = Lead::create($this->normalize($attributes));

        if ($this->deliveryIsConfigured()) {
            DeliverLeadJob::dispatch($lead)->afterCommit();
        }

        return $lead;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function deliveryIsConfigured(): bool
    {
        return (bool) config('lead-delivery.enabled', false)
        && (string) config('lead-delivery.driver', 'null')
            !== 'null';
    }

    private function normalize(array $attributes): array
    {
        foreach (self::TRIMMABLE_FIELDS as $field) {
            if (
                isset($attributes[$field])
                && is_string($attributes[$field])
            ) {
                $attributes[$field] = trim($attributes[$field]);
            }
        }

        if (
            isset($attributes['email'])
            && is_string($attributes['email'])
        ) {
            $attributes['email'] = mb_strtolower(
                $attributes['email']
            );
        }

        return $attributes;
    }
}
