<?php

namespace App\Enums;

enum EstadoBicicleta: string
{
    case Activa = 'Activa';
    case Robada = 'Robada';
    case Vendida = 'Vendida';
}
