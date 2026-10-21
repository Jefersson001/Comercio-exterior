<?php

namespace App\Enums;

enum MscShipmentStatus: string
{
    case Draft = 'Borrador';
    case Navegando = 'NAVEGANDO';
    case Puerto = 'PUERTO';
    case Registro = 'REGISTRO';
    case InBond = 'IN BOND';
    case PorDespacho = 'POR DESPACHO';
    case Recepcion = 'RECEPCION';
    case Reconocimiento = 'RECONOCIMIENTO';

    /** @return list<self> */
    public static function options(): array
    {
        return self::cases();
    }
}
