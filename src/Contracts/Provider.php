<?php

declare(strict_types=1);

namespace PayamBridge\Contracts;

enum Provider: string
{
    case Bale = 'bale';
    case Rubika = 'rubika';
    case Eitaa = 'eitaa';
}
