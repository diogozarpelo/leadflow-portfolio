<?php

namespace App\Data;

use App\Models\Lead;

final readonly class LeadDeliveryPayload
{
    public function __construct(
        public int $internalId,
        public string $type,
        public string $name,
        public string $email,
        public string $company,
        public ?string $companyRegistration,
        public string $phone,
        public ?string $sector,
        public ?string $location,
        public ?string $quantity,
        public ?string $message,
        public string $language,
        public string $sourcePage,
    ) {}

    public static function fromLead(Lead $lead): self
    {
        return new self(
            internalId: (int) $lead->getKey(),
            type: $lead->type,
            name: $lead->name,
            email: $lead->email,
            company: $lead->company,
            companyRegistration: $lead->company_registration,
            phone: $lead->phone,
            sector: $lead->sector,
            location: $lead->location,
            quantity: $lead->quantity,
            message: $lead->message,
            language: $lead->language,
            sourcePage: $lead->source_page,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'internal_id' => $this->internalId,
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company,
            'company_registration' => $this->companyRegistration,
            'phone' => $this->phone,
            'sector' => $this->sector,
            'location' => $this->location,
            'quantity' => $this->quantity,
            'message' => $this->message,
            'language' => $this->language,
            'source_page' => $this->sourcePage,
        ];
    }
}
