<?php

namespace App\Entity\Enum;

enum Assignment: string
{
    case MAIL = 'Área de correspondencia';
    case TRANSACT = 'Trámite';
    case LIAISON = 'Enlace';
    case ASSISTANT = 'Auxiliar';
}
