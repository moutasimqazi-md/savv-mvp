<?php

namespace Savv\Enums;

enum ProviderConnectionStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
