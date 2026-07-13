<?php

namespace App\Support;

readonly class PneduProvisionLiveAccessContext
{
    public function __construct(
        public bool $showLiveSection = false,
        public ?string $platformLabel = null,
        public ?string $joinUrl = null,
        public ?string $token = null,
        public ?string $password = null,
        public bool $showSpamNote = false,
        public bool $showPostEventSection = true,
    ) {}

    public function hasPassword(): bool
    {
        return $this->password !== null && trim($this->password) !== '';
    }
}
