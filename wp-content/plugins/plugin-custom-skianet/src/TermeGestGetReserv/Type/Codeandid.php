<?php

declare(strict_types=1);

namespace TermeGestGetReserv\Type;

class Codeandid
{
    private mixed $any;

    private Schema $schema;

    public function getAny(): mixed
    {
        return $this->any;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function withAny(mixed $any): static
    {
        $new = clone $this;
        $new->any = $any;

        return $new;
    }

    public function withSchema(Schema $schema): static
    {
        $new = clone $this;
        $new->schema = $schema;

        return $new;
    }
}
