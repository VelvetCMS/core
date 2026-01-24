<?php

declare(strict_types=1);

namespace VelvetCMS\Core\Tenancy;

final class TenantContext
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $host = null,
        private readonly ?string $pathPrefix = null,
        private readonly ?string $urlPrefix = null,
        private readonly array $metadata = []
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function host(): ?string
    {
        return $this->host;
    }

    public function pathPrefix(): ?string
    {
        return $this->pathPrefix;
    }

    public function urlPrefix(): ?string
    {
        return $this->urlPrefix;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
