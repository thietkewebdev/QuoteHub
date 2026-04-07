<?php

namespace App\Support\SupplierExtraction;

enum SupplierProfileApplicationMode: string
{
    case None = 'none';
    case Inferred = 'inferred';
    case Confirmed = 'confirmed';
}
