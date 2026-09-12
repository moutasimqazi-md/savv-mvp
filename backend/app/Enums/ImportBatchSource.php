<?php

namespace Savv\Enums;

enum ImportBatchSource: string
{
    case Runner = 'runner';
    case Manual = 'manual';
    case Csv = 'csv';
}
