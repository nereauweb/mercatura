<?php

namespace App\Contracts;

interface TransactionalMailer
{
    public function to(string $email): self;

    public function attribute(string $key, mixed $value): self;

    public function send(string $templateKey): void;

    public function reset(): self;
}
