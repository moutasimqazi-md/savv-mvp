<?php

namespace Savv\Enums;

enum Provider: string
{
    case AmazonIn = 'amazon_in';
    case Flipkart = 'flipkart';

    public function label(): string
    {
        return match ($this) {
            self::AmazonIn => 'Amazon India',
            self::Flipkart => 'Flipkart',
        };
    }
}
