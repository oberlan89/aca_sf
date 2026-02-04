<?php

namespace App\Entity\Enum;

enum Gender: string
{
    case Male = 'masculino';
    case Female = 'femenino';
    case Other = 'otro';

    public function label(): string
    {
        return $this->value;
    }


}


